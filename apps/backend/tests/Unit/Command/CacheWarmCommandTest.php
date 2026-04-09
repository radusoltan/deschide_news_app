<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CacheWarmCommand;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Service\Analytics\AnalyticsService;
use App\Service\Cache\CacheService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CacheWarmCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:cache:warm', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('popular'));
        $this->assertTrue($command->getDefinition()->hasOption('locales'));
    }

    public function testExecuteWithTrendingArticles(): void
    {
        $analytics = $this->createMock(AnalyticsService::class);
        $analytics->method('getTrendingArticles')->willReturn([
            ['article_id' => 1],
            ['article_id' => 2],
        ]);

        $cache = $this->createMock(CacheService::class);
        $cache->method('setCached')->willReturn(true);

        $article1 = $this->createMock(\App\Entity\Article::class);
        $article1->method('getId')->willReturn(1);
        $article2 = $this->createMock(\App\Entity\Article::class);
        $article2->method('getId')->willReturn(2);

        $articleRepo = $this->createMock(ArticleRepository::class);
        $articleRepo->method('find')->willReturnMap([
            [1, $article1],
            [2, $article2],
        ]);

        $categoryRepo = $this->createMock(CategoryRepository::class);
        $categoryRepo->method('findAll')->willReturn([]);

        $command = $this->buildCommand(
            cache: $cache,
            analytics: $analytics,
            articleRepository: $articleRepo,
            categoryRepository: $categoryRepo,
        );
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('completed', strtolower($tester->getDisplay()));
    }

    public function testExecuteWithNoTrendingFallsBackToLatest(): void
    {
        $analytics = $this->createMock(AnalyticsService::class);
        $analytics->method('getTrendingArticles')->willReturn([]);

        $cache = $this->createMock(CacheService::class);
        $cache->method('setCached')->willReturn(true);

        // Mock QueryBuilder chain for fallback
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $articleRepo = $this->createMock(ArticleRepository::class);
        $articleRepo->method('createQueryBuilder')->willReturn($qb);

        $categoryRepo = $this->createMock(CategoryRepository::class);
        $categoryRepo->method('findAll')->willReturn([]);

        $command = $this->buildCommand(
            cache: $cache,
            analytics: $analytics,
            articleRepository: $articleRepo,
            categoryRepository: $categoryRepo,
        );
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('No trending articles', $output);
    }

    public function testExecuteWarmsCategories(): void
    {
        $analytics = $this->createMock(AnalyticsService::class);
        $analytics->method('getTrendingArticles')->willReturn([]);

        $cache = $this->createMock(CacheService::class);
        $cache->method('setCached')->willReturn(true);

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $articleRepo = $this->createMock(ArticleRepository::class);
        $articleRepo->method('createQueryBuilder')->willReturn($qb);

        $category = $this->createMock(\App\Entity\Category::class);
        $category->method('getId')->willReturn(1);

        $categoryRepo = $this->createMock(CategoryRepository::class);
        $categoryRepo->method('findAll')->willReturn([$category]);

        $command = $this->buildCommand(
            cache: $cache,
            analytics: $analytics,
            articleRepository: $articleRepo,
            categoryRepository: $categoryRepo,
        );
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--locales' => 'ro']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('category cache', strtolower($tester->getDisplay()));
    }

    private function buildCommand(
        ?CacheService $cache = null,
        ?AnalyticsService $analytics = null,
        ?ArticleRepository $articleRepository = null,
        ?CategoryRepository $categoryRepository = null,
        ?LoggerInterface $logger = null,
    ): CacheWarmCommand {
        return new CacheWarmCommand(
            $cache ?? $this->createStub(CacheService::class),
            $analytics ?? $this->createStub(AnalyticsService::class),
            $articleRepository ?? $this->createStub(ArticleRepository::class),
            $categoryRepository ?? $this->createStub(CategoryRepository::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
