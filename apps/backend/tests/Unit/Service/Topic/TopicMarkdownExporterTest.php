<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Topic;

use App\Entity\Article;
use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Service\Scraping\HtmlToMarkdownConverter;
use App\Service\Topic\TopicMarkdownExporter;
use App\ValueObject\DateRange;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TopicMarkdownExporterTest extends TestCase
{
    private TopicMarkdownExporter $exporter;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $converter = new HtmlToMarkdownConverter();
        $this->exporter = new TopicMarkdownExporter($this->em, $converter);
    }

    public function testExportEmptyTopicReturnsFrontmatterWithEmptySections(): void
    {
        $topic = $this->createTopic(42, 'Energie');
        $range = DateRange::lastDays(7);

        $this->mockQueries([], []);

        $result = $this->exporter->export($topic, $range);

        $this->assertStringContainsString('topic_id: 42', $result);
        $this->assertStringContainsString("topic_name: Energie", $result);
        $this->assertStringContainsString('article_count: 0', $result);
        $this->assertStringContainsString('pr_count: 0', $result);
        $this->assertStringContainsString('## Articles', $result);
        $this->assertStringContainsString('*No articles in this period.*', $result);
        $this->assertStringContainsString('## Press Releases', $result);
        $this->assertStringContainsString('*No press releases in this period.*', $result);
    }

    public function testExportWithArticles(): void
    {
        $topic = $this->createTopic(1, 'Politică');
        $article = $this->createArticle(100, 'Parlamentul votează', '<p>Text important despre vot.</p>');

        $this->mockQueries([$article], []);

        $result = $this->exporter->export($topic);

        $this->assertStringContainsString('article_count: 1', $result);
        $this->assertStringContainsString('### Parlamentul votează — ART#100', $result);
        $this->assertStringContainsString('Text important despre vot.', $result);
    }

    public function testExportWithPressReleases(): void
    {
        $topic = $this->createTopic(2, 'Economie');
        $pr = $this->createPressRelease(200, 'Prețul gazului crește', '<p>Conținut despre gaz.</p>');

        $this->mockQueries([], [$pr]);

        $result = $this->exporter->export($topic);

        $this->assertStringContainsString('pr_count: 1', $result);
        $this->assertStringContainsString('### Prețul gazului crește', $result);
        $this->assertStringContainsString('PR#200', $result);
        $this->assertStringContainsString('Conținut despre gaz.', $result);
    }

    public function testExportIncludesPeriodInFrontmatter(): void
    {
        $topic = $this->createTopic(3, 'Sport');
        $range = new DateRange(
            new \DateTimeImmutable('2026-04-01'),
            new \DateTimeImmutable('2026-04-15'),
        );

        $this->mockQueries([], []);

        $result = $this->exporter->export($topic, $range);

        $this->assertStringContainsString("period: '2026-04-01 to 2026-04-15'", $result);
    }

    public function testExportContainsGeneratedAtTimestamp(): void
    {
        $topic = $this->createTopic(4, 'Test');
        $this->mockQueries([], []);

        $result = $this->exporter->export($topic);

        $this->assertStringContainsString('generated_at:', $result);
        $this->assertMatchesRegularExpression('/generated_at: .*\d{4}-\d{2}-\d{2}/', $result);
    }

    public function testDateRangeLastDays(): void
    {
        $range = DateRange::lastDays(7);

        $this->assertGreaterThan(new \DateTimeImmutable('-8 days'), $range->from);
        $this->assertLessThanOrEqual(new \DateTimeImmutable(), $range->to);
    }

    public function testDateRangeFormat(): void
    {
        $range = new DateRange(
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-31'),
        );

        $this->assertSame('2026-01-01 to 2026-01-31', $range->format());
    }

    // -- Helpers --

    private function createTopic(int $id, string $title): Topic
    {
        $topic = new Topic();
        $topic->setTitle($title);
        $ref = new \ReflectionProperty(Topic::class, 'id');
        $ref->setValue($topic, $id);

        return $topic;
    }

    private function createArticle(int $id, string $title, string $content): Article
    {
        $article = new Article();
        $article->setTitle($title);
        $article->setContent($content);
        $ref = new \ReflectionProperty(Article::class, 'id');
        $ref->setValue($article, $id);
        $pubRef = new \ReflectionProperty(Article::class, 'publishedAt');
        $pubRef->setValue($article, new \DateTimeImmutable('2026-04-10'));

        return $article;
    }

    private function createPressRelease(int $id, string $title, string $content): PressRelease
    {
        $pr = new PressRelease();
        $pr->setTitle($title);
        $pr->setContent($content);
        $pr->setCategorySlug('general');
        $ref = new \ReflectionProperty(PressRelease::class, 'id');
        $ref->setValue($pr, $id);

        return $pr;
    }

    /**
     * @param list<Article> $articles
     * @param list<PressRelease> $pressReleases
     */
    private function mockQueries(array $articles, array $pressReleases): void
    {
        $queryMock = $this->createMock(Query::class);
        $queryMock->method('getResult')
            ->willReturnOnConsecutiveCalls($articles, $pressReleases);

        $qbMock = $this->createMock(QueryBuilder::class);
        $qbMock->method('select')->willReturnSelf();
        $qbMock->method('from')->willReturnSelf();
        $qbMock->method('join')->willReturnSelf();
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('orderBy')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);

        $this->em->method('createQueryBuilder')
            ->willReturn($qbMock);
    }
}
