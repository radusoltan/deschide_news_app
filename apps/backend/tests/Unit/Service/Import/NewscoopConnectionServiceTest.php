<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Import;

use App\Service\Import\NewscoopConnectionService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class NewscoopConnectionServiceTest extends TestCase
{
    private Connection $connection;
    private NewscoopConnectionService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->service = new NewscoopConnectionService($this->connection);
    }

    // ── fetchArticles ──────────────────────────────────────────────────────────

    #[Test]
    public function itFetchesArticlesForRomanianLocale(): void
    {
        $rows = [
            ['Number' => 1, 'Name' => 'Test Article', 'IdLanguage' => 2],
        ];
        $this->connection->method('fetchAllAssociative')->willReturn($rows);

        $result = $this->service->fetchArticles('ro');

        $this->assertSame($rows, $result);
    }

    #[Test]
    public function itFetchesArticlesForEnglishLocale(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchArticles('en');

        $this->assertSame([], $result);
    }

    #[Test]
    public function itFetchesArticlesForRussianLocale(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchArticles('ru');

        $this->assertSame([], $result);
    }

    #[Test]
    public function itUsesRomanianLocaleAsDefaultForUnknownLocale(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchArticles('fr'); // unknown locale

        $this->assertSame([], $result);
    }

    #[Test]
    public function itFetchesArticlesWithLimitAndOffset(): void
    {
        $rows = [['Number' => 1]];
        $this->connection->method('fetchAllAssociative')->willReturn($rows);

        $result = $this->service->fetchArticles('ro', true, 10, 20);

        $this->assertSame($rows, $result);
    }

    #[Test]
    public function itFetchesUnpublishedArticlesWhenRequested(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchArticles('ro', false);

        $this->assertSame([], $result);
    }

    // ── fetchCategories ────────────────────────────────────────────────────────

    #[Test]
    public function itFetchesCategoriesForLocale(): void
    {
        $rows = [
            ['Number' => 5, 'Name' => 'Politica'],
        ];
        $this->connection->method('fetchAllAssociative')->willReturn($rows);

        $result = $this->service->fetchCategories('ro');

        $this->assertSame($rows, $result);
    }

    #[Test]
    public function itFetchesCategoriesWithLimit(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchCategories('ro', 5);

        $this->assertSame([], $result);
    }

    // ── fetchAuthors ───────────────────────────────────────────────────────────

    #[Test]
    public function itFetchesAllAuthors(): void
    {
        $rows = [
            ['id' => 1, 'first_name' => 'Ion', 'last_name' => 'Ionescu'],
        ];
        $this->connection->method('fetchAllAssociative')->willReturn($rows);

        $result = $this->service->fetchAuthors();

        $this->assertSame($rows, $result);
    }

    #[Test]
    public function itFetchesAuthorsWithLimit(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchAuthors(5);

        $this->assertSame([], $result);
    }

    // ── fetchImages ────────────────────────────────────────────────────────────

    #[Test]
    public function itFetchesImages(): void
    {
        $rows = [
            ['Id' => 1, 'ImageFileName' => 'image.jpg'],
        ];
        $this->connection->method('fetchAllAssociative')->willReturn($rows);

        $result = $this->service->fetchImages();

        $this->assertSame($rows, $result);
    }

    #[Test]
    public function itFetchesImagesWithLimitAndOffset(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchImages(100, 200);

        $this->assertSame([], $result);
    }

    #[Test]
    public function itFetchesImagesWithArticleNumberRange(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchImages(null, 0, 1000, 2000);

        $this->assertSame([], $result);
    }

    // ── fetchTranslations ──────────────────────────────────────────────────────

    #[Test]
    public function itFetchesTranslationsForArticle(): void
    {
        $rows = [
            ['Number' => 42, 'IdLanguage' => 1, 'locale' => 'en'],
        ];
        $this->connection->method('fetchAllAssociative')->willReturn($rows);

        $result = $this->service->fetchTranslations(42, [1, 15]);

        $this->assertSame($rows, $result);
    }

    #[Test]
    public function itFetchesTranslationsForSingleLanguage(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchTranslations(100, [1]);

        $this->assertSame([], $result);
    }

    // ── testConnection ─────────────────────────────────────────────────────────

    #[Test]
    public function itReturnsTrueWhenConnectionSucceeds(): void
    {
        $this->connection->method('fetchOne')->willReturn(1);

        $result = $this->service->testConnection();

        $this->assertTrue($result);
    }

    #[Test]
    public function itReturnsTrueWhenConnectionReturnsStringOne(): void
    {
        $this->connection->method('fetchOne')->willReturn('1');

        $result = $this->service->testConnection();

        $this->assertTrue($result);
    }

    #[Test]
    public function itReturnsFalseWhenConnectionFails(): void
    {
        $this->connection->method('fetchOne')
            ->willThrowException(new \Exception('Connection refused'));

        $result = $this->service->testConnection();

        $this->assertFalse($result);
    }

    #[Test]
    public function itReturnsFalseWhenQueryReturnsNull(): void
    {
        $this->connection->method('fetchOne')->willReturn(null);

        $result = $this->service->testConnection();

        $this->assertFalse($result);
    }

    // ── getStatistics ─────────────────────────────────────────────────────────

    #[Test]
    public function itReturnsStatisticsArray(): void
    {
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                500,   // articles_total
                1000,  // images_total
                800,   // images_used
                18,    // categories_total
                50     // authors_total
            );

        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                ['IdLanguage' => 2, 'count' => 300],
                ['IdLanguage' => 15, 'count' => 100],
                ['IdLanguage' => 1, 'count' => 100],
            ]);

        $stats = $this->service->getStatistics();

        $this->assertSame(500, $stats['articles_total']);
        $this->assertSame(300, $stats['articles_ro']);
        $this->assertSame(100, $stats['articles_ru']);
        $this->assertSame(100, $stats['articles_en']);
        $this->assertSame(1000, $stats['images_total']);
        $this->assertSame(800, $stats['images_used']);
        $this->assertSame(18, $stats['categories_total']);
        $this->assertSame(50, $stats['authors_total']);
    }

    #[Test]
    public function itHandlesUnknownLanguageIdInStatistics(): void
    {
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(100, 50, 40, 5, 10);

        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                ['IdLanguage' => 99, 'count' => 50], // unknown language
            ]);

        $stats = $this->service->getStatistics();

        $this->assertSame(100, $stats['articles_total']);
        $this->assertArrayNotHasKey('articles_ro', $stats);
        $this->assertArrayNotHasKey('articles_en', $stats);
        $this->assertArrayNotHasKey('articles_ru', $stats);
    }

    #[Test]
    public function itReturnsStatisticsWithEmptyResults(): void
    {
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(0, 0, 0, 0, 0);

        $this->connection->method('fetchAllAssociative')
            ->willReturn([]);

        $stats = $this->service->getStatistics();

        $this->assertSame(0, $stats['articles_total']);
        $this->assertSame(0, $stats['images_total']);
        $this->assertSame(0, $stats['images_used']);
        $this->assertSame(0, $stats['categories_total']);
        $this->assertSame(0, $stats['authors_total']);
    }

    // ── fetchImages with range filter ─────────────────────────────────────────

    #[Test]
    public function itFetchesImagesWithLimitAndArticleRange(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchImages(50, 10, 1000, 2000);

        $this->assertSame([], $result);
    }

    #[Test]
    public function itFetchesImagesWithoutLimit(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchImages(null, 0, null, null);

        $this->assertSame([], $result);
    }

    // ── fetchCategories edge cases ────────────────────────────────────────────

    #[Test]
    public function itFetchesCategoriesWithoutLimit(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchCategories('en', null);

        $this->assertSame([], $result);
    }

    // ── fetchArticles edge cases ──────────────────────────────────────────────

    #[Test]
    public function itFetchesArticlesWithoutLimit(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchArticles('ro', true, null, 0);

        $this->assertSame([], $result);
    }

    // ── fetchAuthors edge cases ───────────────────────────────────────────────

    #[Test]
    public function itFetchesAuthorsWithoutLimit(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->fetchAuthors(null);

        $this->assertSame([], $result);
    }
}
