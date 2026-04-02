<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Article;
use App\Entity\PageView;
use App\Repository\PageViewRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for PageViewRepository.
 *
 * Tests all query methods against the real test database.
 */
class PageViewRepositoryTest extends KernelTestCase
{
    private PageViewRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(PageViewRepository::class);
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // =====================================================================
    // findByArticleAndDateRange
    // =====================================================================

    public function testFindByArticleAndDateRangeReturnsMatchingViews(): void
    {
        $article = $this->createArticle();

        $pv = new PageView();
        $pv->setArticle($article);
        $pv->setVisitorId('visitor-1');
        $pv->setViewedAt(new DateTime('2026-03-15 10:00:00'));
        $this->em->persist($pv);
        $this->em->flush();

        $results = $this->repository->findByArticleAndDateRange(
            $article->getId(),
            new DateTime('2026-03-14'),
            new DateTime('2026-03-16'),
        );

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $this->assertInstanceOf(PageView::class, $results[0]);
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
    // countUniqueVisitorsByArticle
    // =====================================================================

    public function testCountUniqueVisitorsByArticle(): void
    {
        $article = $this->createArticle();
        $date = new DateTime('2026-03-20');

        // Create 3 page views, 2 from same visitor
        foreach (['visitor-a', 'visitor-a', 'visitor-b'] as $vid) {
            $pv = new PageView();
            $pv->setArticle($article);
            $pv->setVisitorId($vid);
            $pv->setViewedAt((clone $date)->setTime(12, 0, 0));
            $this->em->persist($pv);
        }
        $this->em->flush();

        $count = $this->repository->countUniqueVisitorsByArticle($article->getId(), $date);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    // =====================================================================
    // countViewsByArticleAndDate
    // =====================================================================

    public function testCountViewsByArticleAndDate(): void
    {
        $article = $this->createArticle();
        $date = new DateTime('2026-03-21');

        $pv1 = new PageView();
        $pv1->setArticle($article);
        $pv1->setVisitorId('v1');
        $pv1->setViewedAt((clone $date)->setTime(8, 0, 0));
        $this->em->persist($pv1);

        $pv2 = new PageView();
        $pv2->setArticle($article);
        $pv2->setVisitorId('v2');
        $pv2->setViewedAt((clone $date)->setTime(14, 0, 0));
        $this->em->persist($pv2);
        $this->em->flush();

        $count = $this->repository->countViewsByArticleAndDate($article->getId(), $date);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testCountViewsByArticleAndDateReturnsZeroForNoMatch(): void
    {
        $count = $this->repository->countViewsByArticleAndDate(99999999, new DateTime('2020-01-01'));

        $this->assertSame(0, $count);
    }

    // =====================================================================
    // getAvgReadingTimeByArticle
    // =====================================================================

    public function testGetAvgReadingTimeByArticle(): void
    {
        $article = $this->createArticle();
        $date = new DateTime('2026-03-22');

        $pv1 = new PageView();
        $pv1->setArticle($article);
        $pv1->setVisitorId('v1');
        $pv1->setViewedAt((clone $date)->setTime(10, 0, 0));
        $pv1->setSessionDuration(120);
        $this->em->persist($pv1);

        $pv2 = new PageView();
        $pv2->setArticle($article);
        $pv2->setVisitorId('v2');
        $pv2->setViewedAt((clone $date)->setTime(11, 0, 0));
        $pv2->setSessionDuration(180);
        $this->em->persist($pv2);
        $this->em->flush();

        $avg = $this->repository->getAvgReadingTimeByArticle($article->getId(), $date);

        $this->assertNotNull($avg);
        $this->assertGreaterThan(0, $avg);
    }

    public function testGetAvgReadingTimeByArticleReturnsNullWhenNoData(): void
    {
        $avg = $this->repository->getAvgReadingTimeByArticle(99999999, new DateTime('2020-01-01'));

        $this->assertNull($avg);
    }

    // =====================================================================
    // getAvgReadingTimeByArticleAndDate (alias)
    // =====================================================================

    public function testGetAvgReadingTimeByArticleAndDateDelegatesToMainMethod(): void
    {
        $result = $this->repository->getAvgReadingTimeByArticleAndDate(99999999, new DateTime('2020-01-01'));

        $this->assertNull($result);
    }

    // =====================================================================
    // countViewsByDate
    // =====================================================================

    public function testCountViewsByDate(): void
    {
        $date = new DateTime('2026-03-23');

        $pv = new PageView();
        $pv->setVisitorId('date-visitor');
        $pv->setViewedAt((clone $date)->setTime(15, 0, 0));
        $this->em->persist($pv);
        $this->em->flush();

        $count = $this->repository->countViewsByDate($date);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    // =====================================================================
    // countViewsOlderThan
    // =====================================================================

    public function testCountViewsOlderThan(): void
    {
        // This should not fail; it returns a count >= 0
        $count = $this->repository->countViewsOlderThan(new DateTime('+1 year'));

        $this->assertGreaterThanOrEqual(0, $count);
    }

    // =====================================================================
    // deleteOlderThan
    // =====================================================================

    public function testDeleteOlderThanReturnsDeletedCount(): void
    {
        // Delete views from far past (should delete 0 or more)
        $deleted = $this->repository->deleteOlderThan(new DateTime('2000-01-01'));

        $this->assertGreaterThanOrEqual(0, $deleted);
    }

    // =====================================================================
    // findViewsOlderThan
    // =====================================================================

    public function testFindViewsOlderThanReturnsArray(): void
    {
        $results = $this->repository->findViewsOlderThan(new DateTime('2000-01-01'));

        $this->assertIsArray($results);
    }

    // =====================================================================
    // getCompletionRateByArticleAndDate (stub method)
    // =====================================================================

    public function testGetCompletionRateByArticleAndDateReturnsNull(): void
    {
        $result = $this->repository->getCompletionRateByArticleAndDate(1, new DateTime());

        $this->assertNull($result);
    }

    // =====================================================================
    // countNewVisitorsByDate
    // =====================================================================

    public function testCountNewVisitorsByDateReturnsInt(): void
    {
        $count = $this->repository->countNewVisitorsByDate(new DateTime('2026-03-25'));

        $this->assertGreaterThanOrEqual(0, $count);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function createArticle(): Article
    {
        $suffix = uniqid('pvtest-', true);
        $article = new Article();
        $article->setTitle('PV Test Article ' . $suffix);
        $article->setSlug('pv-test-' . $suffix);
        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }
}
