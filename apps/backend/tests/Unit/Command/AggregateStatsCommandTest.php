<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\AggregateStatsCommand;
use App\Repository\ArticleRepository;
use App\Repository\PageViewRepository;
use App\Repository\SessionRepository;
use App\Service\Analytics\AnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AggregateStatsCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:stats:aggregate', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('date'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    public function testExecuteWithInvalidDateFails(): void
    {
        $command = $this->buildCommand();
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--date' => 'not-a-date']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid date format', $tester->getDisplay());
    }

    public function testExecuteWithValidDateSucceeds(): void
    {
        $articleRepo = $this->createMock(ArticleRepository::class);
        $articleRepo->method('findAll')->willReturn([]);

        $pageViewRepo = $this->createMock(PageViewRepository::class);
        $pageViewRepo->method('countViewsByDate')->willReturn(100);
        $pageViewRepo->method('countNewVisitorsByDate')->willReturn(50);

        $sessionRepo = $this->createMock(SessionRepository::class);
        $sessionRepo->method('calculateBounceRate')->willReturn(45.5);
        $sessionRepo->method('calculateAvgDuration')->willReturn(120);

        $perf = $this->createMock(AnalyticsService::class);
        $perf->method('getUniqueVisitorCount')->willReturn(80);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(\Doctrine\ORM\EntityRepository::class));

        $redis = $this->createStub(Client::class);
        $logger = $this->createStub(LoggerInterface::class);

        $command = new AggregateStatsCommand($perf, $pageViewRepo, $sessionRepo, $articleRepo, $em, $redis, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--date' => '2025-01-01']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('aggregation completed', $tester->getDisplay());
    }

    public function testExecuteDryRunDoesNotPersist(): void
    {
        $articleRepo = $this->createMock(ArticleRepository::class);
        $articleRepo->method('findAll')->willReturn([]);

        $pageViewRepo = $this->createMock(PageViewRepository::class);
        $pageViewRepo->method('countViewsByDate')->willReturn(50);
        $pageViewRepo->method('countNewVisitorsByDate')->willReturn(20);

        $sessionRepo = $this->createMock(SessionRepository::class);
        $sessionRepo->method('calculateBounceRate')->willReturn(30.0);
        $sessionRepo->method('calculateAvgDuration')->willReturn(90);

        $perf = $this->createMock(AnalyticsService::class);
        $perf->method('getUniqueVisitorCount')->willReturn(40);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(\Doctrine\ORM\EntityRepository::class));
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $redis = $this->createStub(Client::class);
        $logger = $this->createStub(LoggerInterface::class);

        $command = new AggregateStatsCommand($perf, $pageViewRepo, $sessionRepo, $articleRepo, $em, $redis, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--date' => '2025-01-01', '--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('DRY RUN', $tester->getDisplay());
    }

    public function testExecuteDefaultDateIsYesterday(): void
    {
        $articleRepo = $this->createMock(ArticleRepository::class);
        $articleRepo->method('findAll')->willReturn([]);

        $pageViewRepo = $this->createMock(PageViewRepository::class);
        $pageViewRepo->method('countViewsByDate')->willReturn(0);
        $pageViewRepo->method('countNewVisitorsByDate')->willReturn(0);

        $sessionRepo = $this->createMock(SessionRepository::class);
        $sessionRepo->method('calculateBounceRate')->willReturn(0.0);
        $sessionRepo->method('calculateAvgDuration')->willReturn(0);

        $perf = $this->createMock(AnalyticsService::class);
        $perf->method('getUniqueVisitorCount')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(\Doctrine\ORM\EntityRepository::class));

        $redis = $this->createStub(Client::class);
        $logger = $this->createStub(LoggerInterface::class);

        $command = new AggregateStatsCommand($perf, $pageViewRepo, $sessionRepo, $articleRepo, $em, $redis, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]); // Uses default 'yesterday'

        $yesterday = (new \DateTime('yesterday'))->format('Y-m-d');
        $this->assertStringContainsString($yesterday, $tester->getDisplay());
    }

    public function testExecuteWithArticlesHavingViews(): void
    {
        $article = $this->createStub(\App\Entity\Article::class);
        $article->method('getId')->willReturn(1);

        $articleRepo = $this->createStub(ArticleRepository::class);
        $articleRepo->method('findAll')->willReturn([$article]);

        $pageViewRepo = $this->createStub(PageViewRepository::class);
        $pageViewRepo->method('countViewsByArticleAndDate')->willReturn(50);
        $pageViewRepo->method('countUniqueVisitorsByArticle')->willReturn(30);
        $pageViewRepo->method('getAvgReadingTimeByArticleAndDate')->willReturn(120);
        $pageViewRepo->method('getCompletionRateByArticleAndDate')->willReturn(0.75);
        $pageViewRepo->method('countViewsByDate')->willReturn(200);
        $pageViewRepo->method('countNewVisitorsByDate')->willReturn(80);

        $sessionRepo = $this->createStub(SessionRepository::class);
        $sessionRepo->method('calculateBounceRate')->willReturn(35.0);
        $sessionRepo->method('calculateAvgDuration')->willReturn(150);

        $perf = $this->createStub(AnalyticsService::class);
        $perf->method('getUniqueVisitorCount')->willReturn(180);

        $statsRepo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $statsRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($statsRepo);
        $em->expects($this->atLeastOnce())->method('persist');
        $em->expects($this->atLeastOnce())->method('flush');

        $redis = $this->createStub(Client::class);
        $logger = $this->createStub(LoggerInterface::class);

        $command = new AggregateStatsCommand($perf, $pageViewRepo, $sessionRepo, $articleRepo, $em, $redis, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--date' => '2025-06-15']);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('aggregation completed', $output);
        $this->assertStringContainsString('Aggregated stats for 1 articles', $output);
    }

    public function testExecuteWithArticlesHavingZeroViewsSkipsStats(): void
    {
        $article = $this->createStub(\App\Entity\Article::class);
        $article->method('getId')->willReturn(1);

        $articleRepo = $this->createStub(ArticleRepository::class);
        $articleRepo->method('findAll')->willReturn([$article]);

        $pageViewRepo = $this->createStub(PageViewRepository::class);
        $pageViewRepo->method('countViewsByArticleAndDate')->willReturn(0);
        $pageViewRepo->method('countViewsByDate')->willReturn(0);
        $pageViewRepo->method('countNewVisitorsByDate')->willReturn(0);

        $sessionRepo = $this->createStub(SessionRepository::class);
        $sessionRepo->method('calculateBounceRate')->willReturn(0.0);
        $sessionRepo->method('calculateAvgDuration')->willReturn(0);

        $perf = $this->createStub(AnalyticsService::class);
        $perf->method('getUniqueVisitorCount')->willReturn(0);

        $statsRepo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $statsRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($statsRepo);

        $redis = $this->createStub(Client::class);
        $logger = $this->createStub(LoggerInterface::class);

        $command = new AggregateStatsCommand($perf, $pageViewRepo, $sessionRepo, $articleRepo, $em, $redis, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--date' => '2025-06-15']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Aggregated stats for 0 articles', $tester->getDisplay());
    }

    public function testExecuteRedisReturnsZeroFallsBackToPageViews(): void
    {
        $articleRepo = $this->createStub(ArticleRepository::class);
        $articleRepo->method('findAll')->willReturn([]);

        $pageViewRepo = $this->createStub(PageViewRepository::class);
        $pageViewRepo->method('countViewsByDate')->willReturn(100);
        $pageViewRepo->method('countNewVisitorsByDate')->willReturn(50);

        $sessionRepo = $this->createStub(SessionRepository::class);
        $sessionRepo->method('calculateBounceRate')->willReturn(40.0);
        $sessionRepo->method('calculateAvgDuration')->willReturn(100);

        $perf = $this->createStub(AnalyticsService::class);
        $perf->method('getUniqueVisitorCount')->willReturn(0); // Redis returns 0

        $statsRepo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $statsRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($statsRepo);

        $redis = $this->createStub(Client::class);
        $logger = $this->createStub(LoggerInterface::class);

        $command = new AggregateStatsCommand($perf, $pageViewRepo, $sessionRepo, $articleRepo, $em, $redis, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--date' => '2025-06-15']);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Redis HyperLogLog returned 0', $output);
    }

    public function testExecuteUpdatesExistingSiteStats(): void
    {
        $articleRepo = $this->createStub(ArticleRepository::class);
        $articleRepo->method('findAll')->willReturn([]);

        $pageViewRepo = $this->createStub(PageViewRepository::class);
        $pageViewRepo->method('countViewsByDate')->willReturn(300);
        $pageViewRepo->method('countNewVisitorsByDate')->willReturn(100);

        $sessionRepo = $this->createStub(SessionRepository::class);
        $sessionRepo->method('calculateBounceRate')->willReturn(25.5);
        $sessionRepo->method('calculateAvgDuration')->willReturn(200);

        $perf = $this->createStub(AnalyticsService::class);
        $perf->method('getUniqueVisitorCount')->willReturn(250);

        $existingStats = new \App\Entity\SiteStatsDaily();
        $existingStats->setDate(new \DateTime('2025-06-15'));

        $statsRepo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $statsRepo->method('findOneBy')->willReturn($existingStats);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($statsRepo);

        $redis = $this->createStub(Client::class);
        $logger = $this->createStub(LoggerInterface::class);

        $command = new AggregateStatsCommand($perf, $pageViewRepo, $sessionRepo, $articleRepo, $em, $redis, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--date' => '2025-06-15']);

        $this->assertSame(0, $tester->getStatusCode());
        // Existing stats should be updated
        $this->assertSame(300, $existingStats->getTotalVisits());
        $this->assertSame(250, $existingStats->getUniqueVisitors());
    }

    public function testExecuteDryRunWithArticleViewsDoesNotPersist(): void
    {
        $article = $this->createStub(\App\Entity\Article::class);
        $article->method('getId')->willReturn(1);

        $articleRepo = $this->createStub(ArticleRepository::class);
        $articleRepo->method('findAll')->willReturn([$article]);

        $pageViewRepo = $this->createStub(PageViewRepository::class);
        $pageViewRepo->method('countViewsByArticleAndDate')->willReturn(10);
        $pageViewRepo->method('countUniqueVisitorsByArticle')->willReturn(5);
        $pageViewRepo->method('getAvgReadingTimeByArticleAndDate')->willReturn(60);
        $pageViewRepo->method('getCompletionRateByArticleAndDate')->willReturn(null);
        $pageViewRepo->method('countViewsByDate')->willReturn(50);
        $pageViewRepo->method('countNewVisitorsByDate')->willReturn(20);

        $sessionRepo = $this->createStub(SessionRepository::class);
        $sessionRepo->method('calculateBounceRate')->willReturn(30.0);
        $sessionRepo->method('calculateAvgDuration')->willReturn(90);

        $perf = $this->createStub(AnalyticsService::class);
        $perf->method('getUniqueVisitorCount')->willReturn(40);

        $statsRepo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $statsRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($statsRepo);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $redis = $this->createStub(Client::class);
        $logger = $this->createStub(LoggerInterface::class);

        $command = new AggregateStatsCommand($perf, $pageViewRepo, $sessionRepo, $articleRepo, $em, $redis, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--date' => '2025-06-15', '--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('DRY RUN', $tester->getDisplay());
    }

    public function testExecuteShowsSiteStatsTable(): void
    {
        $articleRepo = $this->createStub(ArticleRepository::class);
        $articleRepo->method('findAll')->willReturn([]);

        $pageViewRepo = $this->createStub(PageViewRepository::class);
        $pageViewRepo->method('countViewsByDate')->willReturn(500);
        $pageViewRepo->method('countNewVisitorsByDate')->willReturn(200);

        $sessionRepo = $this->createStub(SessionRepository::class);
        $sessionRepo->method('calculateBounceRate')->willReturn(45.5);
        $sessionRepo->method('calculateAvgDuration')->willReturn(180);

        $perf = $this->createStub(AnalyticsService::class);
        $perf->method('getUniqueVisitorCount')->willReturn(400);

        $statsRepo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $statsRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($statsRepo);

        $redis = $this->createStub(Client::class);
        $logger = $this->createStub(LoggerInterface::class);

        $command = new AggregateStatsCommand($perf, $pageViewRepo, $sessionRepo, $articleRepo, $em, $redis, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--date' => '2025-06-15']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total Visits', $output);
        $this->assertStringContainsString('Unique Visitors', $output);
        $this->assertStringContainsString('Bounce Rate', $output);
        $this->assertStringContainsString('Avg Session Duration', $output);
    }

    private function buildCommand(): AggregateStatsCommand
    {
        return new AggregateStatsCommand(
            $this->createStub(AnalyticsService::class),
            $this->createStub(PageViewRepository::class),
            $this->createStub(SessionRepository::class),
            $this->createStub(ArticleRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(Client::class),
            $this->createStub(LoggerInterface::class),
        );
    }
}
