<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\TrendingArticlesCommand;
use App\Repository\ArticleRepository;
use App\Service\PerformanceService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class TrendingArticlesCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $perf = $this->createStub(PerformanceService::class);
        $repo = $this->createStub(ArticleRepository::class);
        $command = new TrendingArticlesCommand($perf, $repo);
        $this->assertSame('app:stats:trending', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $perf = $this->createStub(PerformanceService::class);
        $repo = $this->createStub(ArticleRepository::class);
        $command = new TrendingArticlesCommand($perf, $repo);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $perf = $this->createStub(PerformanceService::class);
        $repo = $this->createStub(ArticleRepository::class);
        $command = new TrendingArticlesCommand($perf, $repo);
        $this->assertTrue($command->getDefinition()->hasOption('hours'));
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
    }

    public function testExecuteWithNoTrendingArticles(): void
    {
        $perf = $this->createStub(PerformanceService::class);
        $perf->method('getTrendingArticles')->willReturn([]);

        $repo = $this->createStub(ArticleRepository::class);

        $command = new TrendingArticlesCommand($perf, $repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No trending articles', $tester->getDisplay());
    }

    public function testExecuteWithTrendingArticles(): void
    {
        $article = $this->createStub(\App\Entity\Article::class);
        $article->method('getId')->willReturn(42);
        $article->method('getTitle')->willReturn('Big Story');
        $article->method('getCategory')->willReturn(null);

        $perf = $this->createStub(PerformanceService::class);
        $perf->method('getTrendingArticles')->willReturn([
            ['article_id' => 42, 'views' => 1500],
        ]);

        $repo = $this->createStub(ArticleRepository::class);
        $repo->method('find')->willReturn($article);

        $command = new TrendingArticlesCommand($perf, $repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithCustomHoursAndLimit(): void
    {
        $perf = $this->createStub(PerformanceService::class);
        $perf->method('getTrendingArticles')->willReturn([]);

        $repo = $this->createStub(ArticleRepository::class);

        $command = new TrendingArticlesCommand($perf, $repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--hours' => '12', '--limit' => '5']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('12', $tester->getDisplay());
    }

    public function testExecuteWithArticleHavingCategory(): void
    {
        // Category entity has no __toString, so we create an anonymous subclass
        $category = new class extends \App\Entity\Category {
            public function __toString(): string
            {
                return 'Politica';
            }
        };

        $article = $this->createStub(\App\Entity\Article::class);
        $article->method('getId')->willReturn(10);
        $article->method('getTitle')->willReturn('Article With Category');
        $article->method('getCategory')->willReturn($category);

        $perf = $this->createStub(PerformanceService::class);
        $perf->method('getTrendingArticles')->willReturn([
            ['article_id' => 10, 'views' => 500],
        ]);

        $repo = $this->createStub(ArticleRepository::class);
        $repo->method('find')->willReturn($article);

        $command = new TrendingArticlesCommand($perf, $repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('successfully', $tester->getDisplay());
    }

    public function testExecuteWithArticleNotFoundInRepository(): void
    {
        $perf = $this->createStub(PerformanceService::class);
        $perf->method('getTrendingArticles')->willReturn([
            ['article_id' => 999, 'views' => 100],
        ]);

        $repo = $this->createStub(ArticleRepository::class);
        $repo->method('find')->willReturn(null);

        $command = new TrendingArticlesCommand($perf, $repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('successfully', $tester->getDisplay());
    }

    public function testExecuteWithCategoryThrowingException(): void
    {
        // Category that throws Exception when cast to string
        $category = new class extends \App\Entity\Category {
            public function __toString(): string
            {
                throw new \RuntimeException('Proxy not initialized');
            }
        };

        $article = $this->createStub(\App\Entity\Article::class);
        $article->method('getId')->willReturn(7);
        $article->method('getTitle')->willReturn('Short');
        $article->method('getCategory')->willReturn($category);

        $perf = $this->createStub(PerformanceService::class);
        $perf->method('getTrendingArticles')->willReturn([
            ['article_id' => 7, 'views' => 200],
        ]);

        $repo = $this->createStub(ArticleRepository::class);
        $repo->method('find')->willReturn($article);

        $command = new TrendingArticlesCommand($perf, $repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        // Category name should fall back to 'N/A' due to exception
        $this->assertStringContainsString('N/A', $tester->getDisplay());
    }

    public function testExecuteWithLongArticleTitle(): void
    {
        $article = $this->createStub(\App\Entity\Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn(str_repeat('A', 100));
        $article->method('getCategory')->willReturn(null);

        $perf = $this->createStub(PerformanceService::class);
        $perf->method('getTrendingArticles')->willReturn([
            ['article_id' => 1, 'views' => 300],
        ]);

        $repo = $this->createStub(ArticleRepository::class);
        $repo->method('find')->willReturn($article);

        $command = new TrendingArticlesCommand($perf, $repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        // Title should be truncated to 50 chars + '...'
        $this->assertStringContainsString('...', $tester->getDisplay());
    }
}
