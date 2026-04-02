<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Service\NotebookLM\NotebookLMService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

final class DailyBriefingService
{
    private const GEMINI_TIMEOUT = 120;

    public function __construct(
        private readonly string $geminiCliPath,
        private readonly EntityManagerInterface $em,
        private readonly NotebookLMService $notebookLMService,
        private readonly LoggerInterface $logger,
        private readonly string $briefingNotebookId = '',
    ) {}

    /**
     * Generate a daily press briefing.
     */
    public function generateDailyBriefing(?\DateTimeImmutable $date = null): ?string
    {
        $date ??= new \DateTimeImmutable('today');
        $nextDay = $date->modify('+1 day');

        $articles = $this->getArticlesForDate($date, $nextDay);

        if ($articles === []) {
            $this->logger->info('DailyBriefing: no articles found', [
                'date' => $date->format('Y-m-d'),
            ]);

            return null;
        }

        $prompt = $this->buildBriefingPrompt($date, $articles);
        $briefing = $this->callGemini($prompt);

        if ($briefing === null) {
            $this->logger->warning('DailyBriefing: Gemini generation failed');

            return null;
        }

        $this->logger->info('DailyBriefing: generated', [
            'date' => $date->format('Y-m-d'),
            'articleCount' => \count($articles),
        ]);

        return $briefing;
    }

    /**
     * Save the daily briefing to the vault.
     */
    public function saveBriefing(
        string $briefingContent,
        \DateTimeImmutable $date,
        int $articleCount,
        string $vaultPath,
        ?string $audioPath = null,
    ): string {
        $dateStr = $date->format('Y-m-d');

        $frontmatter = "type: daily-briefing\n"
            . "date: {$dateStr}\n"
            . "article_count: {$articleCount}\n";

        if ($audioPath !== null) {
            $frontmatter .= "audio_path: {$audioPath}\n";
        }

        $frontmatter .= "ai:\n"
            . "  auto_generated: true\n"
            . "  reviewed: false\n"
            . "  processed_by: gemini-cli\n"
            . "  processed_at: {$dateStr}\n";

        $markdown = "---\n{$frontmatter}---\n\n{$briefingContent}\n";

        $dir = "{$vaultPath}/dossiers/daily";
        if (!is_dir($dir) && !mkdir($dir, 0o755, true)) {
            return '';
        }

        $filePath = "{$dir}/briefing-{$dateStr}.md";
        file_put_contents($filePath, $markdown);

        return $filePath;
    }

    /**
     * Generate audio briefing via NotebookLM.
     */
    public function generateAudioBriefing(
        \DateTimeImmutable $date,
        string $vaultPath,
    ): ?string {
        if (!$this->notebookLMService->isAvailable() || $this->briefingNotebookId === '') {
            return null;
        }

        $nextDay = $date->modify('+1 day');
        $articles = $this->getArticlesForDate($date, $nextDay);

        if ($articles === []) {
            return null;
        }

        // Feed articles to the briefing notebook
        foreach (array_slice($articles, 0, 15) as $article) {
            $this->notebookLMService->addTextSource(
                $this->briefingNotebookId,
                $article->getTitle() ?? 'Article',
                mb_substr($article->getContent() ?? '', 0, 3000),
            );
        }

        $dateStr = $date->format('d.m.Y');
        $instructions = "Briefing zilnic Deschide News {$dateStr}, rezumat scurt al principalelor știri";

        $this->notebookLMService->generateAudio(
            $this->briefingNotebookId,
            $instructions,
            'brief',
            'ro',
        );

        $audioDir = "{$vaultPath}/dossiers/audio";
        if (!is_dir($audioDir) && !mkdir($audioDir, 0o755, true)) {
            return null;
        }

        $audioPath = "{$audioDir}/daily-{$date->format('Y-m-d')}.mp3";
        $success = $this->notebookLMService->downloadAudio($this->briefingNotebookId, $audioPath);

        if (!$success) {
            return null;
        }

        $this->logger->info('DailyBriefing: audio generated', [
            'date' => $date->format('Y-m-d'),
            'path' => $audioPath,
        ]);

        return $audioPath;
    }

    /**
     * @return list<Article>
     */
    public function getArticlesForDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->em->getRepository(Article::class)->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->where('a.publishedAt >= :start')
            ->andWhere('a.publishedAt < :end')
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
    private function buildBriefingPrompt(\DateTimeImmutable $date, array $articles): string
    {
        $dateStr = $date->format('d.m.Y');
        $count = \count($articles);

        $articleList = '';
        foreach ($articles as $article) {
            $title = $article->getTitle() ?? 'Fără titlu';
            $category = $article->getCategory()?->getName() ?? '';
            $source = ''; // Could extract from sourceEmail if available

            $articleList .= "- [{$category}] {$title}\n";
        }

        return <<<PROMPT
Generează un briefing zilnic de presă pentru {$dateStr}.
{$count} articole procesate astăzi:

{$articleList}

Format:
# Briefing zilnic: {$dateStr}

(3-5 puncte principale, fiecare în 2-3 propoziții maxim. Concentrează-te pe esența fiecărei știri importante.)

Limba: română, diacritice comma-below (ș, ț).
Maxim 500 de cuvinte total.
Nu inventa informații — bazează-te strict pe titlurile furnizate.
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
            $this->logger->error('DailyBriefing: Gemini exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
