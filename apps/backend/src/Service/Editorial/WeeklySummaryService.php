<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Entity\GeneratedContent;
use App\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\LoggerInterface;

class WeeklySummaryService
{
    private const GEMINI_TIMEOUT = 180;

    public function __construct(
        private readonly GeminiCliService $geminiCli,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
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
     * Save the weekly summary as a GeneratedContent entity.
     */
    public function saveSummary(
        string $summaryContent,
        \DateTimeImmutable $weekStart,
        \DateTimeImmutable $weekEnd,
        int $articleCount,
    ): GeneratedContent {
        $gc = new GeneratedContent();
        $gc->setType('weekly_summary');
        $gc->setTitle('Sinteză săptămânală: ' . $weekStart->format('d.m') . ' — ' . $weekEnd->format('d.m.Y'));
        $gc->setContent($summaryContent);
        $gc->setMetadata(['week' => $weekEnd->format('Y-\WW'), 'article_count' => $articleCount]);
        $this->em->persist($gc);
        $this->em->flush();

        return $gc;
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
        try {
            return $this->geminiCli->execute($prompt, ['timeout' => self::GEMINI_TIMEOUT]);
        } catch (GeminiCliException $e) {
            $this->logger->error('WeeklySummary: Gemini exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
