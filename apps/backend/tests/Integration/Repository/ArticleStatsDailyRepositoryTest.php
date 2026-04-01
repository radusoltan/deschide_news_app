<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Article;
use App\Entity\ArticleStatsDaily;
use App\Repository\ArticleStatsDailyRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for ArticleStatsDailyRepository.
 */
class ArticleStatsDailyRepositoryTest extends KernelTestCase
{
    private ArticleStatsDailyRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(ArticleStatsDailyRepository::class);
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // =====================================================================
    // findByArticleAndDateRange
    // =====================================================================

    public function testFindByArticleAndDateRangeReturnsStats(): void
    {
        [$article, $stats] = $this->createStatsFixture(new DateTime('2026-03-15'), 100);

        $results = $this->repository->findByArticleAndDateRange(
            $article->getId(),
            new DateTime('2026-03-14'),
            new DateTime('2026-03-16'),
        );

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    public function testFindByArticleAndDateRangeReturnsEmptyForNoMatch(): void
    {
        $results = $this->repository->findByArticleAndDateRange(
            99999999,
            new DateTime('2020-01-01'),
            new DateTime('2020-01-02'),
        );

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // =====================================================================
    // findTopArticlesByViews
    // =====================================================================

    public function testFindTopArticlesByViewsReturnsOrderedResults(): void
    {
        $date = new DateTime('2026-03-16');

        [$article1, $stats1] = $this->createStatsFixture($date, 500);
        [$article2, $stats2] = $this->createStatsFixture($date, 100);

        $results = $this->repository->findTopArticlesByViews(100, $date);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);

        // Verify ordering: views should be descending
        for ($i = 0; $i < count($results) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $results[$i + 1]->getViews(),
                $results[$i]->getViews(),
            );
        }
    }

    // =====================================================================
    // findOneByArticleAndDate
    // =====================================================================

    public function testFindOneByArticleAndDateReturnsStats(): void
    {
        $date = new DateTime('2026-03-17');
        [$article, $stats] = $this->createStatsFixture($date, 50);

        $found = $this->repository->findOneByArticleAndDate($article->getId(), $date);

        $this->assertNotNull($found);
        $this->assertSame(50, $found->getViews());
    }

    public function testFindOneByArticleAndDateReturnsNullForNoMatch(): void
    {
        $found = $this->repository->findOneByArticleAndDate(99999999, new DateTime('2020-01-01'));

        $this->assertNull($found);
    }

    // =====================================================================
    // getTotalViewsByArticle
    // =====================================================================

    public function testGetTotalViewsByArticleReturnsSumOfViews(): void
    {
        $article = $this->createArticle();

        $stats1 = new ArticleStatsDaily();
        $stats1->setArticle($article);
        $stats1->setDate(new DateTime('2026-03-18'));
        $stats1->setViews(100);
        $this->em->persist($stats1);

        $stats2 = new ArticleStatsDaily();
        $stats2->setArticle($article);
        $stats2->setDate(new DateTime('2026-03-19'));
        $stats2->setViews(200);
        $this->em->persist($stats2);

        $this->em->flush();

        $total = $this->repository->getTotalViewsByArticle($article->getId());

        $this->assertGreaterThanOrEqual(300, $total);
    }

    public function testGetTotalViewsByArticleReturnsZeroForNoStats(): void
    {
        $total = $this->repository->getTotalViewsByArticle(99999999);

        $this->assertSame(0, $total);
    }

    // =====================================================================
    // getTrendingArticles
    // =====================================================================

    public function testGetTrendingArticlesReturnsArrayWithExpectedFormat(): void
    {
        $date = new DateTime('2026-03-20');
        [$article, $stats] = $this->createStatsFixture($date, 999);

        $results = $this->repository->getTrendingArticles(
            100,
            new DateTime('2026-03-19'),
            new DateTime('2026-03-21'),
        );

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);

        // Each result should have article_id and total_views
        $firstResult = $results[0];
        $this->assertArrayHasKey('article_id', $firstResult);
        $this->assertArrayHasKey('total_views', $firstResult);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function createArticle(): Article
    {
        $suffix = uniqid('asd-', true);
        $article = new Article();
        $article->setTitle('ASD Test Article ' . $suffix);
        $article->setSlug('asd-test-' . $suffix);
        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }

    /**
     * @return array{0: Article, 1: ArticleStatsDaily}
     */
    private function createStatsFixture(DateTime $date, int $views): array
    {
        $article = $this->createArticle();

        $stats = new ArticleStatsDaily();
        $stats->setArticle($article);
        $stats->setDate($date);
        $stats->setViews($views);
        $stats->setUniqueVisitors((int) ($views * 0.7));
        $this->em->persist($stats);
        $this->em->flush();

        return [$article, $stats];
    }
}
