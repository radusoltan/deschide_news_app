<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Entity\GeneratedContent;
use App\Enum\ArticleStatus;
use App\Service\NotebookLM\NotebookLMService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class DailyBriefingService
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
     * Save the daily briefing as a GeneratedContent entity.
     */
    public function saveBriefing(
        string $briefingContent,
        \DateTimeImmutable $date,
        int $articleCount,
    ): GeneratedContent {
        $gc = new GeneratedContent();
        $gc->setType('daily_briefing');
        $gc->setTitle('Briefing zilnic: ' . $date->format('d.m.Y'));
        $gc->setContent($briefingContent);
        $gc->setMetadata(['article_count' => $articleCount]);
        $this->em->persist($gc);
        $this->em->flush();

        return $gc;
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
            $category = $article->getCategory()?->getTitle() ?? '';
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
