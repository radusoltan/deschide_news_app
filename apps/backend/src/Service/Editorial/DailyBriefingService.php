<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Entity\GeneratedContent;
use App\Entity\PressRelease;
use App\Enum\ArticleStatus;
use App\Enum\PressReleaseStatus;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\LoggerInterface;

class DailyBriefingService
{
    private const GEMINI_TIMEOUT = 120;

    public function __construct(
        private readonly GeminiCliService $geminiCli,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
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
     * Generate a morning briefing from overnight PressReleases and published articles.
     */
    public function generateMorningBriefing(?\DateTimeImmutable $date = null): ?string
    {
        $date ??= new \DateTimeImmutable('today');

        // Overnight window: 22:00 previous day to 06:00 today
        $overnightStart = $date->modify('-1 day')->setTime(22, 0);
        $overnightEnd = $date->setTime(6, 0);

        // Get pending PressReleases with relevance score >= 3
        $pressReleases = $this->getOvernightPressReleases($overnightStart, $overnightEnd, 3.0);

        // Get articles published overnight
        $articles = $this->getArticlesForDate($overnightStart, $overnightEnd);

        $totalItems = \count($pressReleases) + \count($articles);

        if ($totalItems === 0) {
            $this->logger->info('MorningBriefing: no overnight content found', [
                'date' => $date->format('Y-m-d'),
            ]);

            return null;
        }

        $prompt = $this->buildMorningBriefingPrompt($date, $pressReleases, $articles);
        $briefing = $this->callGemini($prompt);

        if ($briefing === null) {
            $this->logger->warning('MorningBriefing: Gemini generation failed');

            return null;
        }

        $this->logger->info('MorningBriefing: generated', [
            'date' => $date->format('Y-m-d'),
            'pressReleases' => \count($pressReleases),
            'articles' => \count($articles),
        ]);

        return $briefing;
    }

    /**
     * Save the morning briefing as a GeneratedContent entity.
     */
    public function saveMorningBriefing(
        string $content,
        \DateTimeImmutable $date,
        int $itemCount,
    ): GeneratedContent {
        $gc = new GeneratedContent();
        $gc->setType('morning_briefing');
        $gc->setTitle('Briefing matinal: ' . $date->format('d.m.Y'));
        $gc->setContent($content);
        $gc->setMetadata(['item_count' => $itemCount]);
        $this->em->persist($gc);
        $this->em->flush();

        return $gc;
    }

    /**
     * @return list<PressRelease>
     */
    public function getOvernightPressReleases(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        float $minRelevanceScore = 0.0,
    ): array {
        $qb = $this->em->getRepository(PressRelease::class)->createQueryBuilder('pr')
            ->where('pr.status = :status')
            ->andWhere('pr.createdAt >= :start')
            ->andWhere('pr.createdAt < :end')
            ->setParameter('status', PressReleaseStatus::PENDING)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('pr.createdAt', 'DESC');

        if ($minRelevanceScore > 0) {
            $qb->andWhere('pr.relevanceScore >= :minScore')
                ->setParameter('minScore', $minRelevanceScore);
        }

        return $qb->getQuery()->getResult();
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

    /**
     * @param list<PressRelease> $pressReleases
     * @param list<Article>      $articles
     */
    private function buildMorningBriefingPrompt(\DateTimeImmutable $date, array $pressReleases, array $articles): string
    {
        $dateStr = $date->format('d.m.Y');
        $itemList = '';

        foreach ($pressReleases as $pr) {
            $source = $pr->getSourceName() ?? 'Necunoscut';
            $itemList .= "- [Presă internațională / {$source}] {$pr->getTitle()}\n";
        }

        foreach ($articles as $article) {
            $category = $article->getCategory()?->getTitle() ?? '';
            $itemList .= "- [{$category}] {$article->getTitle()}\n";
        }

        $n = \count($pressReleases) + \count($articles);

        return <<<PROMPT
Generează un briefing matinal pentru redacția Deschide.md. Rezumă cele mai importante {$n} articole primite overnight din surse internaționale.

Articole primite:
{$itemList}
Structură:
# Briefing matinal: {$dateStr}

- Titluri principale (3-5 puncte)
- Context scurt pentru fiecare
- Relevanță pentru Republica Moldova

Maxim 400 cuvinte, în limba română cu diacritice comma-below (ș, ț).
Nu inventa informații — bazează-te strict pe titlurile furnizate.
PROMPT;
    }

    private function callGemini(string $prompt): ?string
    {
        try {
            return $this->geminiCli->execute($prompt, ['timeout' => self::GEMINI_TIMEOUT]);
        } catch (GeminiCliException $e) {
            $this->logger->error('DailyBriefing: Gemini exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
