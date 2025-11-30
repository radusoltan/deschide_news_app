<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Article;
use App\Entity\Category;
use App\Enum\ArchiveReason;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Service\ArticleArchiveService;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ArticleArchiveService.
 *
 * Tests business logic for article archiving operations, including:
 * - Individual article archiving/unarchiving
 * - Bulk archiving of old articles
 * - Archive statistics generation
 * - Available years and categories retrieval
 */
class ArticleArchiveServiceTest extends TestCase
{
    private ArticleArchiveService $service;

    private EntityManagerInterface $entityManager;

    private ArticleRepository $articleRepository;

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ArticleArchiveService(
            $this->entityManager,
            $this->articleRepository,
            $this->logger
        );
    }

    // ======================
    // archiveArticle Tests
    // ======================

    #[Test]
    public function itArchivesArticleWithCorrectReason(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Test Article');

        // Expect archive method to be called with the reason
        $article->expects($this->once())
            ->method('archive')
            ->with(ArchiveReason::OLD_CONTENT);

        // Expect flush to persist changes
        $this->entityManager->expects($this->once())
            ->method('flush');

        // Expect logging calls
        $this->logger->expects($this->exactly(2))
            ->method('info');

        $this->service->archiveArticle($article, ArchiveReason::OLD_CONTENT);
    }

    #[Test]
    public function itArchivesArticleWithDifferentReasons(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(2);
        $article->method('getTitle')->willReturn('Duplicate Article');

        $article->expects($this->once())
            ->method('archive')
            ->with(ArchiveReason::DUPLICATE);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->archiveArticle($article, ArchiveReason::DUPLICATE);
    }

    // ======================
    // unarchiveArticle Tests
    // ======================

    #[Test]
    public function itUnarchivesArticleSuccessfully(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Archived Article');
        $article->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);

        // Expect unarchive method to be called
        $article->expects($this->once())
            ->method('unarchive');

        // Expect flush to persist changes
        $this->entityManager->expects($this->once())
            ->method('flush');

        // Expect logging
        $this->logger->expects($this->exactly(2))
            ->method('info');

        $this->service->unarchiveArticle($article);
    }

    // ======================
    // archiveOldArticles Tests
    // ======================

    #[Test]
    public function itArchivesOldArticlesInBatches(): void
    {
        $yearsOld = 4;
        $batchSize = 2;

        // Create mock articles for two batches
        $article1 = $this->createMock(Article::class);
        $article2 = $this->createMock(Article::class);
        $article3 = $this->createMock(Article::class);

        $article1->expects($this->once())->method('archive')->with(ArchiveReason::OLD_CONTENT);
        $article2->expects($this->once())->method('archive')->with(ArchiveReason::OLD_CONTENT);
        $article3->expects($this->once())->method('archive')->with(ArchiveReason::OLD_CONTENT);

        // Setup query builder mock
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->articleRepository->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        // Return two batches, then empty
        $query->method('getResult')->willReturnOnConsecutiveCalls(
            [$article1, $article2],  // First batch
            [$article3],              // Second batch
            []                        // Empty to break loop
        );

        // Expect transaction management for each batch
        $this->entityManager->expects($this->exactly(3))
            ->method('beginTransaction');

        $this->entityManager->expects($this->exactly(2))
            ->method('commit');

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        $this->entityManager->expects($this->exactly(2))
            ->method('clear');

        // One rollback for the empty batch
        $this->entityManager->expects($this->exactly(1))
            ->method('rollback');

        $totalArchived = $this->service->archiveOldArticles($yearsOld, $batchSize);

        $this->assertEquals(3, $totalArchived);
    }

    #[Test]
    public function itHandlesEmptyResultWhenNoOldArticles(): void
    {
        $yearsOld = 4;
        $batchSize = 100;

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->articleRepository->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        // Return empty array immediately
        $query->method('getResult')->willReturn([]);

        // Should start transaction and rollback
        $this->entityManager->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager->expects($this->once())
            ->method('rollback');

        // Should not flush or commit
        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->entityManager->expects($this->never())
            ->method('commit');

        $totalArchived = $this->service->archiveOldArticles($yearsOld, $batchSize);

        $this->assertEquals(0, $totalArchived);
    }

    // ======================
    // getArchiveStats Tests
    // ======================

    #[Test]
    public function itReturnsArchiveStatsWithCorrectStructure(): void
    {
        // Mock for total count
        $totalQueryBuilder = $this->createMock(QueryBuilder::class);
        $totalQuery = $this->createMock(Query::class);

        $totalQueryBuilder->method('select')->willReturnSelf();
        $totalQueryBuilder->method('where')->willReturnSelf();
        $totalQueryBuilder->method('setParameter')->willReturnSelf();
        $totalQueryBuilder->method('getQuery')->willReturn($totalQuery);
        $totalQuery->method('getSingleScalarResult')->willReturn(150);

        // Mock for byCategory query
        $byCategoryQueryBuilder = $this->createMock(QueryBuilder::class);
        $byCategoryQuery = $this->createMock(Query::class);

        $byCategoryQueryBuilder->method('select')->willReturnSelf();
        $byCategoryQueryBuilder->method('leftJoin')->willReturnSelf();
        $byCategoryQueryBuilder->method('where')->willReturnSelf();
        $byCategoryQueryBuilder->method('setParameter')->willReturnSelf();
        $byCategoryQueryBuilder->method('groupBy')->willReturnSelf();
        $byCategoryQueryBuilder->method('orderBy')->willReturnSelf();
        $byCategoryQueryBuilder->method('getQuery')->willReturn($byCategoryQuery);

        $byCategoryQuery->method('setHint')->willReturnSelf();
        $byCategoryQuery->method('getResult')->willReturn([
            ['category_id' => 1, 'category_title' => 'Politics', 'count' => 50],
            ['category_id' => 2, 'category_title' => 'Sports', 'count' => 30],
        ]);

        // Setup repository to return different query builders
        $this->articleRepository->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($totalQueryBuilder, $byCategoryQueryBuilder);

        // Mock connection for byYear SQL query
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);

        $this->entityManager->method('getConnection')->willReturn($connection);
        $connection->method('executeQuery')->willReturn($result);
        $result->method('fetchAllAssociative')->willReturn([
            ['year' => 2023, 'count' => 80],
            ['year' => 2022, 'count' => 70],
        ]);

        $stats = $this->service->getArchiveStats('ro');

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('byYear', $stats);
        $this->assertArrayHasKey('byCategory', $stats);

        $this->assertEquals(150, $stats['total']);
        $this->assertCount(2, $stats['byYear']);
        $this->assertCount(2, $stats['byCategory']);

        // Verify byYear structure
        $this->assertEquals(2023, $stats['byYear'][0]['year']);
        $this->assertEquals(80, $stats['byYear'][0]['count']);

        // Verify byCategory structure
        $this->assertEquals(1, $stats['byCategory'][0]['id']);
        $this->assertEquals('Politics', $stats['byCategory'][0]['name']);
        $this->assertEquals(50, $stats['byCategory'][0]['count']);
    }

    // ======================
    // getAvailableYears Tests
    // ======================

    #[Test]
    public function itReturnsAvailableYearsInDescendingOrder(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);

        $this->entityManager->method('getConnection')->willReturn($connection);
        $connection->method('executeQuery')->willReturn($result);
        $result->method('fetchAllAssociative')->willReturn([
            ['year' => 2024, 'count' => 15],
            ['year' => 2023, 'count' => 45],
            ['year' => 2022, 'count' => 30],
            ['year' => 2021, 'count' => 10],
        ]);

        $years = $this->service->getAvailableYears('ro');

        $this->assertIsArray($years);
        $this->assertCount(4, $years);

        // Verify structure
        $this->assertArrayHasKey('year', $years[0]);
        $this->assertArrayHasKey('count', $years[0]);

        // Verify descending order
        $this->assertEquals(2024, $years[0]['year']);
        $this->assertEquals(15, $years[0]['count']);
        $this->assertEquals(2021, $years[3]['year']);
        $this->assertEquals(10, $years[3]['count']);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoArchivedArticles(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);

        $this->entityManager->method('getConnection')->willReturn($connection);
        $connection->method('executeQuery')->willReturn($result);
        $result->method('fetchAllAssociative')->willReturn([]);

        $years = $this->service->getAvailableYears('ro');

        $this->assertIsArray($years);
        $this->assertEmpty($years);
    }

    // ======================
    // getCategoriesWithArchivedArticles Tests
    // ======================

    #[Test]
    public function itReturnsCategoriesWithArchivedArticles(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->articleRepository->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $query->method('setHint')->willReturnSelf();
        $query->method('getResult')->willReturn([
            ['id' => 1, 'title' => 'Politics', 'slug' => 'politics', 'count' => 50],
            ['id' => 2, 'title' => 'Sports', 'slug' => 'sports', 'count' => 30],
            ['id' => 3, 'title' => 'Technology', 'slug' => 'technology', 'count' => 20],
        ]);

        $categories = $this->service->getCategoriesWithArchivedArticles('ro');

        $this->assertIsArray($categories);
        $this->assertCount(3, $categories);

        // Verify structure
        $this->assertArrayHasKey('id', $categories[0]);
        $this->assertArrayHasKey('name', $categories[0]);
        $this->assertArrayHasKey('slug', $categories[0]);
        $this->assertArrayHasKey('count', $categories[0]);

        // Verify data
        $this->assertEquals(1, $categories[0]['id']);
        $this->assertEquals('Politics', $categories[0]['name']);
        $this->assertEquals('politics', $categories[0]['slug']);
        $this->assertEquals(50, $categories[0]['count']);
    }

    // ======================
    // bulkArchiveOldArticles Tests
    // ======================

    #[Test]
    public function itReturnsBulkArchiveResultWithCorrectStructure(): void
    {
        $yearsOld = 5;
        $batchSize = 100;

        // Mock the internal archiveOldArticles method
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->articleRepository->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        // Return empty to simulate no articles to archive
        $query->method('getResult')->willReturn([]);

        $this->entityManager->method('beginTransaction');
        $this->entityManager->method('rollback');

        $result = $this->service->bulkArchiveOldArticles($yearsOld, $batchSize);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_archived', $result);
        $this->assertArrayHasKey('cutoff_date', $result);

        $this->assertEquals(0, $result['total_archived']);
        $this->assertInstanceOf(DateTimeImmutable::class, $result['cutoff_date']);

        // Verify cutoff date is approximately 5 years ago
        $expectedDate = new DateTimeImmutable('-5 years');
        $actualDate = $result['cutoff_date'];
        $this->assertEquals($expectedDate->format('Y-m-d'), $actualDate->format('Y-m-d'));
    }

    // ======================
    // getArchiveStatistics Tests
    // ======================

    #[Test]
    public function itReturnsCompleteArchiveStatistics(): void
    {
        // Mock query builders for different queries
        $qb1 = $this->createQueryBuilder();  // Total articles
        $qb2 = $this->createQueryBuilder();  // Archived articles
        $qb3 = $this->createQueryBuilder();  // By reason
        $qb4 = $this->createQueryBuilder();  // This month
        $qb5 = $this->createQueryBuilder();  // This year
        $qb6 = $this->createQueryBuilder();  // Oldest
        $qb7 = $this->createQueryBuilder();  // Most recent

        $this->articleRepository->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($qb1, $qb2, $qb3, $qb4, $qb5, $qb6, $qb7);

        // Setup queries to return mock data
        $qb1->method('getQuery')->willReturn($this->mockScalarQuery(1000));   // Total: 1000
        $qb2->method('getQuery')->willReturn($this->mockScalarQuery(250));    // Archived: 250
        $qb3->method('getQuery')->willReturn($this->mockResultQuery([
            ['reason' => ArchiveReason::OLD_CONTENT, 'count' => 200],
            ['reason' => ArchiveReason::OUTDATED_INFO, 'count' => 30],
            ['reason' => ArchiveReason::DUPLICATE, 'count' => 20],
        ]));
        $qb4->method('getQuery')->willReturn($this->mockScalarQuery(15));     // This month: 15
        $qb5->method('getQuery')->willReturn($this->mockScalarQuery(80));     // This year: 80

        // Mock oldest archived article
        $oldestArticle = $this->createMock(Article::class);
        $oldestArticle->method('getId')->willReturn(10);
        $oldestArticle->method('getTitle')->willReturn('Oldest Article');
        $oldestArticle->method('getArchivedAt')->willReturn(new DateTimeImmutable('2020-01-01'));
        $oldestArticle->method('getArchiveReason')->willReturn(ArchiveReason::OLD_CONTENT);

        $qb6->method('getQuery')->willReturn($this->mockSingleResultQuery($oldestArticle));

        // Mock most recent archived article
        $recentArticle = $this->createMock(Article::class);
        $recentArticle->method('getId')->willReturn(500);
        $recentArticle->method('getTitle')->willReturn('Recent Article');
        $recentArticle->method('getArchivedAt')->willReturn(new DateTimeImmutable('2024-11-25'));
        $recentArticle->method('getArchiveReason')->willReturn(ArchiveReason::OUTDATED_INFO);

        $qb7->method('getQuery')->willReturn($this->mockSingleResultQuery($recentArticle));

        $stats = $this->service->getArchiveStatistics();

        // Verify all expected keys are present
        $this->assertArrayHasKey('total_articles', $stats);
        $this->assertArrayHasKey('archived_articles', $stats);
        $this->assertArrayHasKey('archive_percentage', $stats);
        $this->assertArrayHasKey('by_reason', $stats);
        $this->assertArrayHasKey('archived_this_month', $stats);
        $this->assertArrayHasKey('archived_this_year', $stats);
        $this->assertArrayHasKey('oldest_archived', $stats);
        $this->assertArrayHasKey('most_recent_archived', $stats);

        // Verify values
        $this->assertEquals(1000, $stats['total_articles']);
        $this->assertEquals(250, $stats['archived_articles']);
        $this->assertEquals(25.0, $stats['archive_percentage']);
        $this->assertEquals(15, $stats['archived_this_month']);
        $this->assertEquals(80, $stats['archived_this_year']);

        // Verify by_reason structure
        $this->assertIsArray($stats['by_reason']);
        $this->assertEquals(200, $stats['by_reason']['old_content']);
        $this->assertEquals(30, $stats['by_reason']['outdated_info']);
        $this->assertEquals(20, $stats['by_reason']['duplicate']);

        // Verify oldest archived
        $this->assertIsArray($stats['oldest_archived']);
        $this->assertEquals(10, $stats['oldest_archived']['id']);
        $this->assertEquals('Oldest Article', $stats['oldest_archived']['title']);
        $this->assertEquals('old_content', $stats['oldest_archived']['reason']);

        // Verify most recent archived
        $this->assertIsArray($stats['most_recent_archived']);
        $this->assertEquals(500, $stats['most_recent_archived']['id']);
        $this->assertEquals('Recent Article', $stats['most_recent_archived']['title']);
        $this->assertEquals('outdated_info', $stats['most_recent_archived']['reason']);
    }

    #[Test]
    public function itHandlesZeroArticlesInStatistics(): void
    {
        $qb1 = $this->createQueryBuilder();
        $qb2 = $this->createQueryBuilder();
        $qb3 = $this->createQueryBuilder();
        $qb4 = $this->createQueryBuilder();
        $qb5 = $this->createQueryBuilder();
        $qb6 = $this->createQueryBuilder();
        $qb7 = $this->createQueryBuilder();

        $this->articleRepository->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($qb1, $qb2, $qb3, $qb4, $qb5, $qb6, $qb7);

        // All queries return 0 or empty
        $qb1->method('getQuery')->willReturn($this->mockScalarQuery(0));
        $qb2->method('getQuery')->willReturn($this->mockScalarQuery(0));
        $qb3->method('getQuery')->willReturn($this->mockResultQuery([]));
        $qb4->method('getQuery')->willReturn($this->mockScalarQuery(0));
        $qb5->method('getQuery')->willReturn($this->mockScalarQuery(0));
        $qb6->method('getQuery')->willReturn($this->mockNullResultQuery());
        $qb7->method('getQuery')->willReturn($this->mockNullResultQuery());

        $stats = $this->service->getArchiveStatistics();

        $this->assertEquals(0, $stats['total_articles']);
        $this->assertEquals(0, $stats['archived_articles']);
        $this->assertEquals(0, $stats['archive_percentage']);
        $this->assertEmpty($stats['by_reason']);
        $this->assertEquals(0, $stats['archived_this_month']);
        $this->assertEquals(0, $stats['archived_this_year']);
        $this->assertNull($stats['oldest_archived']);
        $this->assertNull($stats['most_recent_archived']);
    }

    // ======================
    // Helper Methods
    // ======================

    private function createQueryBuilder(): QueryBuilder
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();

        return $qb;
    }

    private function mockScalarQuery(int $result): Query
    {
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn($result);

        return $query;
    }

    private function mockResultQuery(array $result): Query
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($result);

        return $query;
    }

    private function mockSingleResultQuery(?Article $result): Query
    {
        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn($result);

        return $query;
    }

    private function mockNullResultQuery(): Query
    {
        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);

        return $query;
    }
}
