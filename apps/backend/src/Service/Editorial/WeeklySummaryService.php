<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Service\NotebookLM\NotebookLMService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

final class WeeklySummaryService
{
    private const GEMINI_TIMEOUT = 180;

    public function __construct(
        private readonly string $geminiCliPath,
        private readonly EntityManagerInterface $em,
        private readonly NotebookLMService $notebookLMService,
        private readonly LoggerInterface $logger,
        private readonly string $weeklyNotebookId = '',
    ) {}

    /**
     * Generate a weekly editorial summary.
     */
    public function generateWeeklySummary(?\DateTimeImmutable $weekEnd = null): ?string
    {
        $weekEnd ??= new \DateTimeImmutable('Sunday this week');
        $weekStart = $weekEnd->modify('-6 days');

        $articles = $this->getArticlesForPeriod($weekStart, $weekEnd);

        if ($articles === []) {
            $this->logger->info('WeeklySummary: no articles found for period', [
                'start' => $weekStart->format('Y-m-d'),
                'end' => $weekEnd->format('Y-m-d'),
            ]);

            return null;
        }

        $prompt = $this->buildSummaryPrompt($weekStart, $weekEnd, $articles);
        $summary = $this->callGemini($prompt);

        if ($summary === null) {
            $this->logger->warning('WeeklySummary: Gemini generation failed');

            return null;
        }

        $this->logger->info('WeeklySummary: generated', [
            'start' => $weekStart->format('Y-m-d'),
            'end' => $weekEnd->format('Y-m-d'),
            'articleCount' => \count($articles),
        ]);

        return $summary;
    }

    /**
     * Save the weekly summary to the vault.
     */
    public function saveSummary(
        string $summaryContent,
        \DateTimeImmutable $weekEnd,
        int $articleCount,
        string $vaultPath,
        ?string $audioPath = null,
    ): string {
        $weekNumber = $weekEnd->format('Y-\WW');
        $date = $weekEnd->format('Y-m-d');

        $frontmatter = "type: weekly-summary\n"
            . "week: {$weekNumber}\n"
            . "date_created: {$date}\n"
            . "article_count: {$articleCount}\n";

        if ($audioPath !== null) {
            $frontmatter .= "audio_path: {$audioPath}\n";
        }

        $frontmatter .= "ai:\n"
            . "  auto_generated: true\n"
            . "  reviewed: false\n"
            . "  processed_by: gemini-cli\n"
            . "  processed_at: {$date}\n";

        $markdown = "---\n{$frontmatter}---\n\n{$summaryContent}\n";

        if ($vaultPath === '') {
            return '';
        }

        $dir = "{$vaultPath}/dossiers";
        if (!is_dir($dir) && !mkdir($dir, 0o755, true)) {
            return '';
        }

        $filePath = "{$dir}/weekly-summary-{$weekNumber}.md";
        file_put_contents($filePath, $markdown);

        return $filePath;
    }

    /**
     * Generate Audio Overview via NotebookLM for the weekly summary.
     */
    public function generateAudioBriefing(
        \DateTimeImmutable $weekEnd,
        string $vaultPath,
    ): ?string {
        if (!$this->notebookLMService->isAvailable() || $this->weeklyNotebookId === '') {
            return null;
        }

        $weekStart = $weekEnd->modify('-6 days');
        $articles = $this->getArticlesForPeriod($weekStart, $weekEnd);

        if ($articles === []) {
            return null;
        }

        // Feed articles to the weekly notebook
        foreach (array_slice($articles, 0, 20) as $article) {
            $this->notebookLMService->addTextSource(
                $this->weeklyNotebookId,
                $article->getTitle() ?? 'Article',
                mb_substr($article->getContent() ?? '', 0, 5000),
            );
        }

        $weekNumber = $weekEnd->format('Y-\WW');
        $instructions = "Briefing săptămânal Deschide News {$weekNumber}, rezumat concis al evenimentelor principale";

        // Generate audio
        $this->notebookLMService->generateAudio(
            $this->weeklyNotebookId,
            $instructions,
            'brief',
            'ro',
        );

        // Download audio
        $audioDir = "{$vaultPath}/dossiers/audio";
        if (!is_dir($audioDir) && !mkdir($audioDir, 0o755, true)) {
            return null;
        }

        $audioPath = "{$audioDir}/weekly-{$weekNumber}.mp3";
        $success = $this->notebookLMService->downloadAudio($this->weeklyNotebookId, $audioPath);

        if (!$success) {
            $this->logger->warning('WeeklySummary: audio download failed');

            return null;
        }

        $this->logger->info('WeeklySummary: audio generated', [
            'week' => $weekNumber,
            'path' => $audioPath,
        ]);

        return $audioPath;
    }

    /**
     * @return list<Article>
     */
    public function getArticlesForPeriod(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->em->getRepository(Article::class)->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->where('a.publishedAt >= :start')
            ->andWhere('a.publishedAt <= :end')
            ->andWhere('a.status = :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<Article> $articles
     */
    private function buildSummaryPrompt(\DateTimeImmutable $start, \DateTimeImmutable $end, array $articles): string
    {
        $startStr = $start->format('d.m.Y');
        $endStr = $end->format('d.m.Y');
        $count = \count($articles);

        $articleList = '';
        $byCategory = [];

        foreach ($articles as $article) {
            $date = $article->getPublishedAt()?->format('d.m') ?? 'N/A';
            $title = $article->getTitle() ?? 'Fără titlu';
            $category = $article->getCategory()?->getTitle() ?? 'Necategorizat';

            $articleList .= "- [{$date}] [{$category}] {$title}\n";
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
        }

        $categoryBreakdown = '';
        arsort($byCategory);
        foreach ($byCategory as $cat => $catCount) {
            $categoryBreakdown .= "  - {$cat}: {$catCount} articole\n";
        }

        return <<<PROMPT
Ești redactorul-șef al portalului Deschide News din Republica Moldova.
Generează o sinteză editorială a săptămânii {$startStr} — {$endStr}.

{$count} articole publicate:
{$categoryBreakdown}

Lista completă:
{$articleList}

Structura sintezei:

# Sinteză săptămânală: {$startStr} — {$endStr}

## Top 5 știri ale săptămânii
(Cele mai importante 5 știri, cu scurtă justificare de 2-3 propoziții fiecare)

## Breakdown pe teme
(Pentru fiecare categorie cu minim 3 articole: tendințe observate, evoluții cheie)

## Conexiuni între subiecte
(Legături identificate între articole din categorii diferite)

## Preview săptămâna viitoare
(Evenimente așteptate sau continuări ale subiectelor curente, dacă sunt menționate)

## Lacune de acoperire
(Subiecte insuficient acoperite sau întrebări rămase fără răspuns)

Scrie în limba română cu diacritice comma-below (ș, ț).
Tonul: profesional, analitic, concis.
Nu inventa informații — bazează-te strict pe articolele furnizate.
PROMPT;
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
            $this->logger->error('WeeklySummary: Gemini exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
