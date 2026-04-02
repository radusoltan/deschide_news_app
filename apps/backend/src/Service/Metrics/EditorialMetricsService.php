<?php

declare(strict_types=1);

namespace App\Service\Metrics;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Service\Search\ElasticsearchIndexManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class EditorialMetricsService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ElasticsearchIndexManager $esManager,
        private LoggerInterface $logger,
        private ?string $vaultPath = null,
    ) {}

    /**
     * Collect all editorial metrics for the given period.
     *
     * @return array<string, mixed>
     */
    public function collectMetrics(
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array {
        $to ??= new \DateTimeImmutable('now');
        $from ??= $to->modify('-30 days');

        $metrics = [
            'period' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'days' => (int) $from->diff($to)->days,
            ],
            'volume' => $this->collectVolumeMetrics($from, $to),
            'translations' => $this->collectTranslationMetrics($from, $to),
            'quality' => $this->collectQualityMetrics($from, $to),
            'elasticsearch' => $this->collectElasticsearchMetrics(),
            'pipeline' => $this->collectPipelineMetrics(),
        ];

        // Vault metrics (if path is configured)
        if ($this->vaultPath !== null && $this->vaultPath !== '' && is_dir($this->vaultPath)) {
            $metrics['vault'] = $this->collectVaultMetrics();
        }

        return $metrics;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectVolumeMetrics(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->em->getConnection();

        // Total articles
        $total = (int) $conn->fetchOne('SELECT COUNT(*) FROM articles');

        // Articles in period
        $inPeriod = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM articles WHERE created_at >= :from AND created_at <= :to',
            ['from' => $from->format('Y-m-d H:i:s'), 'to' => $to->format('Y-m-d H:i:s')],
        );

        $days = max(1, (int) $from->diff($to)->days);
        $perDay = round($inPeriod / $days, 1);

        // By category (top 10)
        $byCategory = $conn->fetchAllAssociative(
            'SELECT c.slug, COUNT(a.id) as cnt
             FROM articles a
             JOIN categories c ON a.category_id = c.id
             WHERE a.created_at >= :from AND a.created_at <= :to
             GROUP BY c.slug ORDER BY cnt DESC LIMIT 10',
            ['from' => $from->format('Y-m-d H:i:s'), 'to' => $to->format('Y-m-d H:i:s')],
        );

        // By status
        $byStatus = $conn->fetchAllAssociative(
            'SELECT status, COUNT(*) as cnt FROM articles GROUP BY status ORDER BY cnt DESC',
        );

        return [
            'total' => $total,
            'in_period' => $inPeriod,
            'per_day' => $perDay,
            'by_category' => $byCategory,
            'by_status' => array_column($byStatus, 'cnt', 'status'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectTranslationMetrics(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->em->getConnection();

        // Total articles with translations
        $withTranslations = (int) $conn->fetchOne(
            "SELECT COUNT(DISTINCT foreign_key) FROM ext_translations
             WHERE object_class = :class AND field = 'content' AND content IS NOT NULL",
            ['class' => Article::class],
        );

        // Articles with both EN and RU translations (trilingual complete)
        $trilingualComplete = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM (
                SELECT foreign_key FROM ext_translations
                WHERE object_class = :class AND field = 'content' AND content IS NOT NULL
                GROUP BY foreign_key
                HAVING COUNT(DISTINCT locale) >= 2
             ) sub",
            ['class' => Article::class],
        );

        $total = (int) $conn->fetchOne('SELECT COUNT(*) FROM articles');
        $trilingualPct = $total > 0 ? round(($trilingualComplete / $total) * 100, 1) : 0.0;

        // Translation status breakdown
        $statusBreakdown = $conn->fetchAllAssociative(
            'SELECT translation_status, COUNT(*) as cnt FROM articles
             WHERE translation_status IS NOT NULL GROUP BY translation_status',
        );

        // Needs review count
        $needsReview = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM articles WHERE translation_status = 'needs_review'",
        );

        return [
            'with_translations' => $withTranslations,
            'trilingual_complete' => $trilingualComplete,
            'trilingual_pct' => $trilingualPct,
            'needs_review' => $needsReview,
            'status_breakdown' => array_column($statusBreakdown, 'cnt', 'translation_status'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectQualityMetrics(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->em->getConnection();

        // AI-generated articles (those with translated_by = 'gemini-agent')
        $autoGenerated = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM articles WHERE translated_by = 'gemini-agent'",
        );

        // Reviewed (translation_status = 'complete')
        $reviewed = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM articles WHERE translation_status = 'complete'",
        );

        $total = (int) $conn->fetchOne('SELECT COUNT(*) FROM articles WHERE translated_by IS NOT NULL');
        $reviewedPct = $total > 0 ? round(($reviewed / $total) * 100, 1) : 0.0;

        return [
            'auto_generated' => $autoGenerated,
            'reviewed' => $reviewed,
            'reviewed_pct' => $reviewedPct,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectElasticsearchMetrics(): array
    {
        if (!$this->esManager->isEnabled()) {
            return ['enabled' => false, 'indexed' => 0];
        }

        try {
            $client = $this->esManager->getClient();
            $indexName = $this->esManager->getIndexName();

            $stats = $client->indices()->stats(['index' => $indexName])->asArray();
            $count = $stats['_all']['primaries']['docs']['count'] ?? 0;
            $sizeBytes = $stats['_all']['primaries']['store']['size_in_bytes'] ?? 0;

            // Simple latency test
            $start = microtime(true);
            $client->search([
                'index' => $indexName,
                'body' => ['size' => 1, 'query' => ['match_all' => (object) []]],
            ]);
            $latencyMs = round((microtime(true) - $start) * 1000);

            return [
                'enabled' => true,
                'indexed' => $count,
                'size_mb' => round($sizeBytes / 1024 / 1024, 1),
                'latency_ms' => $latencyMs,
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('EditorialMetrics: ES stats failed', ['error' => $e->getMessage()]);

            return ['enabled' => true, 'indexed' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function collectPipelineMetrics(): array
    {
        $conn = $this->em->getConnection();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        // Articles created today
        $createdToday = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM articles WHERE created_at::date = :today",
            ['today' => $today],
        );

        // Translated today
        $translatedToday = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM articles WHERE translated_at::date = :today",
            ['today' => $today],
        );

        // Failed messages (Doctrine transport)
        $failedToday = 0;

        try {
            $failedToday = (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed' AND created_at::date = :today",
                ['today' => $today],
            );
        } catch (\Throwable) {
            // Table may not exist
        }

        // Deduplicated (same content_hash, more than 1 article)
        $duplicatesTotal = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM (
                SELECT content_hash FROM articles
                WHERE content_hash IS NOT NULL
                GROUP BY content_hash HAVING COUNT(*) > 1
             ) sub",
        );

        return [
            'created_today' => $createdToday,
            'translated_today' => $translatedToday,
            'failed_today' => $failedToday,
            'duplicates_total' => $duplicatesTotal,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectVaultMetrics(): array
    {
        $totalNotes = 0;
        $byType = [];
        $orphans = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->vaultPath, \FilesystemIterator::SKIP_DOTS),
        );

        $allLinks = [];
        $allFiles = [];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'md') {
                continue;
            }

            $totalNotes++;
            $relativePath = str_replace($this->vaultPath . '/', '', $file->getPathname());
            $allFiles[] = $relativePath;

            // Detect type from frontmatter
            $content = file_get_contents($file->getPathname());
            if ($content !== false && preg_match('/^---\s*\n.*?type:\s*(\S+)/s', $content, $m)) {
                $type = $m[1];
                $byType[$type] = ($byType[$type] ?? 0) + 1;
            }

            // Collect wikilinks
            if ($content !== false && preg_match_all('/\[\[([^\]|]+)/', $content, $links)) {
                foreach ($links[1] as $link) {
                    $allLinks[] = $link;
                }
            }
        }

        // Orphan detection: notes not linked from any other note
        $linkedFiles = array_unique($allLinks);
        foreach ($allFiles as $filePath) {
            $basename = pathinfo($filePath, PATHINFO_FILENAME);
            $isLinked = false;
            foreach ($linkedFiles as $link) {
                if (str_contains($link, $basename)) {
                    $isLinked = true;
                    break;
                }
            }
            if (!$isLinked) {
                $orphans++;
            }
        }

        $orphanPct = $totalNotes > 0 ? round(($orphans / $totalNotes) * 100, 1) : 0.0;

        return [
            'total_notes' => $totalNotes,
            'by_type' => $byType,
            'orphans' => $orphans,
            'orphan_pct' => $orphanPct,
        ];
    }

    /**
     * Write a metrics snapshot to the vault dashboards folder.
     */
    public function writeVaultSnapshot(array $metrics): void
    {
        if ($this->vaultPath === null || $this->vaultPath === '') {
            return;
        }

        $dashboardDir = $this->vaultPath . '/dashboards';
        if (!is_dir($dashboardDir) && !mkdir($dashboardDir, 0o755, true)) {
            return;
        }

        $date = date('Y-m-d H:i');
        $vol = $metrics['volume'] ?? [];
        $trans = $metrics['translations'] ?? [];
        $qual = $metrics['quality'] ?? [];
        $es = $metrics['elasticsearch'] ?? [];
        $pipe = $metrics['pipeline'] ?? [];
        $vault = $metrics['vault'] ?? [];

        $content = <<<MD
---
type: dashboard
title: "Metrici editoriale — Snapshot"
date_modified: {$date}
---

# Metrici editoriale — {$date}

## Volum
- **Total articole**: {$vol['total']}
- **În perioadă**: {$vol['in_period']} ({$vol['per_day']}/zi)

## Traduceri
- **Trilingve complete**: {$trans['trilingual_pct']}%
- **Necesită revizie**: {$trans['needs_review']}

## Calitate AI
- **Auto-generate**: {$qual['auto_generated']}
- **Revizuite**: {$qual['reviewed']} ({$qual['reviewed_pct']}%)

## Elasticsearch
- **Indexate**: {$es['indexed']}
- **Latență**: {$es['latency_ms']}ms

## Pipeline azi
- **Create**: {$pipe['created_today']}
- **Traduse**: {$pipe['translated_today']}
- **Erori**: {$pipe['failed_today']}

MD;

        if ($vault !== []) {
            $content .= <<<MD

## Vault
- **Total note**: {$vault['total_notes']}
- **Orfane**: {$vault['orphans']} ({$vault['orphan_pct']}%)

MD;
        }

        file_put_contents($dashboardDir . '/metrics-summary.md', $content);
    }
}
