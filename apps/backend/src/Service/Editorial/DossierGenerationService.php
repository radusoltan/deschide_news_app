<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Service\NotebookLM\NotebookLMService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

final class DossierGenerationService
{
    private const GEMINI_TIMEOUT = 180;

    public function __construct(
        private readonly string $geminiCliPath,
        private readonly EntityManagerInterface $em,
        private readonly NotebookLMService $notebookLMService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Detect MOC files that have accumulated enough new articles for a dossier.
     *
     * @param int $threshold Minimum new articles since last dossier
     * @return list<array{moc: string, path: string, articleCount: int}>
     */
    public function detectMOCsNeedingDossier(string $vaultPath, int $threshold = 5): array
    {
        $mocsDir = "{$vaultPath}/mocs";
        if (!is_dir($mocsDir)) {
            return [];
        }

        $results = [];
        $files = glob("{$mocsDir}/MOC-*.md");

        foreach ($files as $filePath) {
            $content = file_get_contents($filePath);
            if ($content === false) {
                continue;
            }

            // Count article references in the chronology section
            $articleCount = $this->countRecentArticles($content);

            if ($articleCount >= $threshold) {
                $results[] = [
                    'moc' => basename($filePath),
                    'path' => $filePath,
                    'articleCount' => $articleCount,
                ];
            }
        }

        return $results;
    }

    /**
     * Generate a narrative dossier from a MOC and its recent articles.
     *
     * @param list<Article> $recentArticles
     */
    public function generateDossier(string $mocPath, array $recentArticles, string $vaultPath): ?string
    {
        $mocContent = file_get_contents($mocPath);
        if ($mocContent === false) {
            return null;
        }

        $mocName = basename($mocPath, '.md');
        $topicName = str_replace(['MOC-', '-'], ['', ' '], $mocName);

        // Build article summaries for the prompt
        $articleSummaries = $this->buildArticleSummaries($recentArticles);

        $prompt = $this->buildDossierPrompt($topicName, $articleSummaries);
        $dossierContent = $this->callGemini($prompt);

        if ($dossierContent === null) {
            $this->logger->warning('DossierGeneration: Gemini failed for dossier', [
                'moc' => $mocName,
            ]);

            return null;
        }

        // Enrich with NotebookLM insights if available
        $insights = $this->enrichWithNotebookLM($topicName, $recentArticles);

        // Build final dossier markdown
        $date = date('Y-m-d');
        $slug = $this->slugify($topicName);
        $frontmatter = $this->buildDossierFrontmatter($topicName, $date, $recentArticles);

        $markdown = "---\n{$frontmatter}---\n\n{$dossierContent}";

        if ($insights !== null) {
            $markdown .= "\n\n## Insight-uri NotebookLM\n\n{$insights}\n";
        }

        // Save to vault
        $dossierDir = "{$vaultPath}/dossiers";
        if (!is_dir($dossierDir) && !mkdir($dossierDir, 0o755, true)) {
            return null;
        }

        $filePath = "{$dossierDir}/{$slug}-dossier-{$date}.md";
        file_put_contents($filePath, $markdown);

        $this->logger->info('DossierGeneration: dossier created', [
            'moc' => $mocName,
            'path' => $filePath,
            'articles' => \count($recentArticles),
        ]);

        return $filePath;
    }

    /**
     * Get recent articles for a topic by searching the database.
     *
     * @return list<Article>
     */
    public function getRecentArticlesForTopic(string $topicName, int $days = 30, int $limit = 50): array
    {
        $since = new \DateTimeImmutable("-{$days} days");

        $qb = $this->em->getRepository(Article::class)->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->where('a.publishedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit);

        // Try to match category name to topic
        $normalizedTopic = mb_strtolower(str_replace([' ', '-'], ['', ''], $topicName));
        $qb->andWhere('LOWER(c.name) LIKE :topic')
            ->setParameter('topic', "%{$normalizedTopic}%");

        return $qb->getQuery()->getResult();
    }

    private function buildDossierPrompt(string $topicName, string $articleSummaries): string
    {
        return <<<PROMPT
Ești un analist editorial pentru o redacție de știri moldovenească.
Generează un dosar narativ pe tema "{$topicName}" bazat pe următoarele articole recente:

{$articleSummaries}

Structura dosarului:
# Dosar: {$topicName}

## Rezumat executiv
(3-5 propoziții care captează esența situației)

## Cronologie evenimentelor
(Lista evenimentelor cheie cu dată, în ordine cronologică)

## Actori cheie și pozițiile lor
(Persoane și instituții principale, cu pozițiile lor declarate)

## Analiză
(Tendințe observate, schimbări de direcție, implicații)

## Întrebări nerezolvate
(Subiecte care necesită investigație suplimentară, lacune de acoperire)

## Recomandări editoriale
(3-5 sugestii concrete pentru echipa de redacție)

Scrie în limba română cu diacritice comma-below (ș, ț).
Tonul: profesional, analitic, echilibrat.
Nu inventa informații — bazează-te strict pe articolele furnizate.
PROMPT;
    }

    /**
     * @param list<Article> $articles
     */
    private function buildArticleSummaries(array $articles): string
    {
        $summaries = [];

        foreach ($articles as $article) {
            $date = $article->getPublishedAt()?->format('Y-m-d') ?? 'N/A';
            $title = $article->getTitle() ?? 'Fără titlu';
            $lead = mb_substr($article->getLead() ?? $article->getContent() ?? '', 0, 200);

            $summaries[] = "- [{$date}] {$title}\n  {$lead}";
        }

        return implode("\n\n", $summaries);
    }

    /**
     * @param list<Article> $articles
     */
    private function enrichWithNotebookLM(string $topicName, array $articles): ?string
    {
        if (!$this->notebookLMService->isAvailable()) {
            return null;
        }

        $notebooks = []; // Would need to be injected — use resolveNotebookId pattern
        $notebookId = $this->notebookLMService->resolveNotebookId(
            $this->slugify($topicName),
            $notebooks,
        );

        if ($notebookId === null) {
            return null;
        }

        // Feed recent articles to notebook
        foreach (array_slice($articles, 0, 10) as $article) {
            $this->notebookLMService->addTextSource(
                $notebookId,
                $article->getTitle() ?? 'Article',
                mb_substr($article->getContent() ?? '', 0, 5000),
            );
        }

        // Ask analytical questions
        $questions = [
            'Care sunt contradicțiile între sursele încărcate?',
            'Ce subiecte apar în toate sursele dar nu au fost aprofundate?',
        ];

        $insights = [];
        foreach ($questions as $q) {
            $answer = $this->notebookLMService->ask($notebookId, $q);
            if ($answer !== null) {
                $insights[] = "**{$q}**\n{$answer}";
            }
        }

        return $insights !== [] ? implode("\n\n", $insights) : null;
    }

    /**
     * @param list<Article> $articles
     */
    private function buildDossierFrontmatter(string $topicName, string $date, array $articles): string
    {
        $articleIds = array_map(fn (Article $a) => $a->getId(), $articles);

        return "type: dossier\n"
            . "topic: {$topicName}\n"
            . "date_created: {$date}\n"
            . "article_count: " . \count($articles) . "\n"
            . "article_ids: [" . implode(', ', $articleIds) . "]\n"
            . "ai:\n"
            . "  auto_generated: true\n"
            . "  reviewed: false\n"
            . "  processed_by: gemini-cli\n"
            . "  processed_at: {$date}\n";
    }

    private function countRecentArticles(string $mocContent): int
    {
        // Count [[art-*]] references in the content
        preg_match_all('/\[\[art-[^\]]+\]\]/', $mocContent, $matches);

        return \count($matches[0] ?? []);
    }

    private function callGemini(string $prompt): ?string
    {
        $process = new Process([$this->geminiCliPath, '-p', $prompt]);
        $process->setTimeout(self::GEMINI_TIMEOUT);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                return null;
            }

            return trim($process->getOutput());
        } catch (\Throwable $e) {
            $this->logger->error('DossierGeneration: Gemini exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(
            ['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ', ' '],
            ['a', 'a', 'i', 's', 't', 's', 't', '-'],
            $text,
        );
        $text = preg_replace('/[^a-z0-9\-]/', '', $text);

        return preg_replace('/-+/', '-', trim($text, '-'));
    }
}
