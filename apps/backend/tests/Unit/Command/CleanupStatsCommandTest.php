<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CleanupStatsCommand;
use App\Entity\Article;
use App\Entity\PageView;
use App\Repository\PageViewRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupStatsCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:stats:cleanup', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('days'));
        $this->assertTrue($definition->hasOption('force'));
        $this->assertTrue($definition->hasOption('archive'));
    }

    public function testDefaultDaysOptionIs90(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('days');
        $this->assertSame('90', $option->getDefault());
    }

    public function testForceOptionHasShortcutF(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('force');
        $this->assertSame('f', $option->getShortcut());
    }

    public function testExecuteWithZeroOldViewsReturnsSuccessEarly(): void
    {
        $pageViewRepo = $this->createMock(PageViewRepository::class);
        $pageViewRepo->expects($this->once())
            ->method('countViewsOlderThan')
            ->willReturn(0);
        $pageViewRepo->expects($this->never())
            ->method('deleteOlderThan');

        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $tester = $this->createTester(new CleanupStatsCommand($pageViewRepo, $em, $logger));
        $tester->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('No old records to cleanup', $tester->getDisplay());
    }

    public function testExecuteWithForceDeletesRecords(): void
    {
        $pageViewRepo = $this->createMock(PageViewRepository::class);
        $pageViewRepo->method('countViewsOlderThan')->willReturn(500);
        $pageViewRepo->expects($this->once())
            ->method('deleteOlderThan')
            ->willReturn(500);

        $em = $this->createStub(EntityManagerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Page views cleaned up',
                $this->callback(function (array $context): bool {
                    return $context['deleted_count'] === 500
                        && $context['archived'] === false;
                })
            );

        $tester = $this->createTester(new CleanupStatsCommand($pageViewRepo, $em, $logger));
        $tester->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Deleted 500 old page_view records', $tester->getDisplay());
    }

    public function testExecuteWithoutForcePromptsForConfirmation(): void
    {
        $pageViewRepo = $this->createMock(PageViewRepository::class);
        $pageViewRepo->method('countViewsOlderThan')->willReturn(100);
        $pageViewRepo->expects($this->never())
            ->method('deleteOlderThan');

        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $tester = $this->createTester(new CleanupStatsCommand($pageViewRepo, $em, $logger));
        // Provide 'no' input to confirmation prompt
        $tester->setInputs(['no']);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Operation cancelled', $tester->getDisplay());
    }

    public function testExecuteWithForceAndArchiveCreatesCSVBeforeDeleting(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(42);

        $viewedAt = new DateTime('2025-01-15 10:30:00');

        $pageView = $this->createStub(PageView::class);
        $pageView->method('getId')->willReturn(1);
        $pageView->method('getArticle')->willReturn($article);
        $pageView->method('getVisitorId')->willReturn('visitor-abc');
        $pageView->method('getIpAddress')->willReturn('192.168.1.1');
        $pageView->method('getUserAgent')->willReturn('Mozilla/5.0');
        $pageView->method('getReferrer')->willReturn('https://example.com');
        $pageView->method('getViewedAt')->willReturn($viewedAt);
        $pageView->method('getSessionDuration')->willReturn(120);

        $pageViewRepo = $this->createStub(PageViewRepository::class);
        $pageViewRepo->method('countViewsOlderThan')->willReturn(1);
        $pageViewRepo->method('findViewsOlderThan')->willReturn([$pageView]);
        $pageViewRepo->method('deleteOlderThan')->willReturn(1);

        $em = $this->createStub(EntityManagerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Page views cleaned up',
                $this->callback(fn (array $ctx): bool => $ctx['archived'] === true)
            );

        $tester = $this->createTester(new CleanupStatsCommand($pageViewRepo, $em, $logger));
        $tester->execute(['--force' => true, '--archive' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Archiving data', $display);
        $this->assertStringContainsString('Archived 1 records to:', $display);
        $this->assertStringContainsString('Deleted 1 old page_view records', $display);
        $this->assertStringContainsString('Aggregated stats', $display);

        // Clean up the generated CSV file
        if (preg_match('/Archived 1 records to: (.+\.csv)/', $display, $matches)) {
            $csvPath = trim($matches[1]);
            if (file_exists($csvPath)) {
                unlink($csvPath);
            }
        }
    }

    public function testExecuteWithCustomDays(): void
    {
        $pageViewRepo = $this->createMock(PageViewRepository::class);
        $pageViewRepo->expects($this->once())
            ->method('countViewsOlderThan')
            ->with($this->callback(function (DateTime $date): bool {
                // 30 days ago should be approximately today - 30 days
                $expected = new DateTime('-30 days');
                $diff = abs($expected->getTimestamp() - $date->getTimestamp());
                return $diff < 5; // within 5 seconds tolerance
            }))
            ->willReturn(0);

        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $tester = $this->createTester(new CleanupStatsCommand($pageViewRepo, $em, $logger));
        $tester->execute(['--days' => '30', '--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testExecuteOutputsWarningWhenRecordsFound(): void
    {
        $pageViewRepo = $this->createStub(PageViewRepository::class);
        $pageViewRepo->method('countViewsOlderThan')->willReturn(250);
        $pageViewRepo->method('deleteOlderThan')->willReturn(250);

        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $tester = $this->createTester(new CleanupStatsCommand($pageViewRepo, $em, $logger));
        $tester->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Found 250 records to delete', $tester->getDisplay());
    }

    public function testExecuteOutputsPreservedStatsNote(): void
    {
        $pageViewRepo = $this->createStub(PageViewRepository::class);
        $pageViewRepo->method('countViewsOlderThan')->willReturn(10);
        $pageViewRepo->method('deleteOlderThan')->willReturn(10);

        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $tester = $this->createTester(new CleanupStatsCommand($pageViewRepo, $em, $logger));
        $tester->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Aggregated stats', $tester->getDisplay());
    }

    public function testExecuteArchiveWithNullArticle(): void
    {
        $viewedAt = new DateTime('2025-06-01 08:00:00');

        $pageView = $this->createStub(PageView::class);
        $pageView->method('getId')->willReturn(99);
        $pageView->method('getArticle')->willReturn(null);
        $pageView->method('getVisitorId')->willReturn('vis-xyz');
        $pageView->method('getIpAddress')->willReturn(null);
        $pageView->method('getUserAgent')->willReturn(null);
        $pageView->method('getReferrer')->willReturn(null);
        $pageView->method('getViewedAt')->willReturn($viewedAt);
        $pageView->method('getSessionDuration')->willReturn(null);

        $pageViewRepo = $this->createStub(PageViewRepository::class);
        $pageViewRepo->method('countViewsOlderThan')->willReturn(1);
        $pageViewRepo->method('findViewsOlderThan')->willReturn([$pageView]);
        $pageViewRepo->method('deleteOlderThan')->willReturn(1);

        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $tester = $this->createTester(new CleanupStatsCommand($pageViewRepo, $em, $logger));
        $tester->execute(['--force' => true, '--archive' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Archived 1 records', $tester->getDisplay());

        // Clean up
        $display = $tester->getDisplay();
        if (preg_match('/Archived 1 records to: (.+\.csv)/', $display, $matches)) {
            $csvPath = trim($matches[1]);
            if (file_exists($csvPath)) {
                unlink($csvPath);
            }
        }
    }

    public function testExecuteWithConfirmationAnsweredYes(): void
    {
        $pageViewRepo = $this->createMock(PageViewRepository::class);
        $pageViewRepo->method('countViewsOlderThan')->willReturn(50);
        $pageViewRepo->expects($this->once())
            ->method('deleteOlderThan')
            ->willReturn(50);

        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $tester = $this->createTester(new CleanupStatsCommand($pageViewRepo, $em, $logger));
        $tester->setInputs(['yes']);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Deleted 50', $tester->getDisplay());
    }

    public function testArchiveOptionIsValueNone(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('archive');
        $this->assertFalse($option->acceptValue());
        $this->assertFalse($option->getDefault());
    }

    public function testExecuteArchiveWithMultiplePageViews(): void
    {
        $article1 = $this->createStub(Article::class);
        $article1->method('getId')->willReturn(10);

        $article2 = $this->createStub(Article::class);
        $article2->method('getId')->willReturn(20);

        $viewedAt1 = new DateTime('2025-02-01 09:00:00');
        $viewedAt2 = new DateTime('2025-02-02 14:30:00');

        $pageView1 = $this->createStub(PageView::class);
        $pageView1->method('getId')->willReturn(1);
        $pageView1->method('getArticle')->willReturn($article1);
        $pageView1->method('getVisitorId')->willReturn('visitor-1');
        $pageView1->method('getIpAddress')->willReturn('10.0.0.1');
        $pageView1->method('getUserAgent')->willReturn('Chrome');
        $pageView1->method('getReferrer')->willReturn('https://google.com');
        $pageView1->method('getViewedAt')->willReturn($viewedAt1);
        $pageView1->method('getSessionDuration')->willReturn(60);

        $pageView2 = $this->createStub(PageView::class);
        $pageView2->method('getId')->willReturn(2);
        $pageView2->method('getArticle')->willReturn($article2);
        $pageView2->method('getVisitorId')->willReturn('visitor-2');
        $pageView2->method('getIpAddress')->willReturn('10.0.0.2');
        $pageView2->method('getUserAgent')->willReturn('Firefox');
        $pageView2->method('getReferrer')->willReturn(null);
        $pageView2->method('getViewedAt')->willReturn($viewedAt2);
        $pageView2->method('getSessionDuration')->willReturn(300);

        $pageViewRepo = $this->createStub(PageViewRepository::class);
        $pageViewRepo->method('countViewsOlderThan')->willReturn(2);
        $pageViewRepo->method('findViewsOlderThan')->willReturn([$pageView1, $pageView2]);
        $pageViewRepo->method('deleteOlderThan')->willReturn(2);

        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $tester = $this->createTester(new CleanupStatsCommand($pageViewRepo, $em, $logger));
        $tester->execute(['--force' => true, '--archive' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Archived 2 records to:', $display);
        $this->assertStringContainsString('Deleted 2 old page_view records', $display);

        // Clean up the generated CSV file
        if (preg_match('/Archived 2 records to: (.+\.csv)/', $display, $matches)) {
            $csvPath = trim($matches[1]);
            if (file_exists($csvPath)) {
                unlink($csvPath);
            }
        }
    }

    public function testExecuteOutputContainsCutoffDate(): void
    {
        $pageViewRepo = $this->createStub(PageViewRepository::class);
        $pageViewRepo->method('countViewsOlderThan')->willReturn(0);

        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $tester = $this->createTester(new CleanupStatsCommand($pageViewRepo, $em, $logger));
        $tester->execute(['--days' => '90', '--force' => true]);

        $display = $tester->getDisplay();
        $expectedDate = (new DateTime('-90 days'))->format('Y-m-d');
        $this->assertStringContainsString($expectedDate, $display);
    }

    public function testLoggerContextIncludesCutoffDate(): void
    {
        $pageViewRepo = $this->createStub(PageViewRepository::class);
        $pageViewRepo->method('countViewsOlderThan')->willReturn(5);
        $pageViewRepo->method('deleteOlderThan')->willReturn(5);

        $em = $this->createStub(EntityManagerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Page views cleaned up',
                $this->callback(function (array $ctx): bool {
                    return isset($ctx['cutoff_date'])
                        && isset($ctx['deleted_count'])
                        && isset($ctx['archived'])
                        && $ctx['deleted_count'] === 5;
                })
            );

        $tester = $this->createTester(new CleanupStatsCommand($pageViewRepo, $em, $logger));
        $tester->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testExecuteWithoutForceAndArchiveArchivesBeforeDeleting(): void
    {
        $viewedAt = new DateTime('2025-03-01 12:00:00');

        $pageView = $this->createStub(PageView::class);
        $pageView->method('getId')->willReturn(5);
        $pageView->method('getArticle')->willReturn(null);
        $pageView->method('getVisitorId')->willReturn('vis-test');
        $pageView->method('getIpAddress')->willReturn('1.2.3.4');
        $pageView->method('getUserAgent')->willReturn('Test UA');
        $pageView->method('getReferrer')->willReturn(null);
        $pageView->method('getViewedAt')->willReturn($viewedAt);
        $pageView->method('getSessionDuration')->willReturn(10);

        $pageViewRepo = $this->createMock(PageViewRepository::class);
        $pageViewRepo->method('countViewsOlderThan')->willReturn(1);
        $pageViewRepo->method('findViewsOlderThan')->willReturn([$pageView]);
        $pageViewRepo->expects($this->once())
            ->method('deleteOlderThan')
            ->willReturn(1);

        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $tester = $this->createTester(new CleanupStatsCommand($pageViewRepo, $em, $logger));
        $tester->setInputs(['yes']);
        $tester->execute(['--archive' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Archiving data', $display);
        $this->assertStringContainsString('Deleted 1', $display);

        // Clean up
        if (preg_match('/Archived 1 records to: (.+\.csv)/', $display, $matches)) {
            $csvPath = trim($matches[1]);
            if (file_exists($csvPath)) {
                unlink($csvPath);
            }
        }
    }

    public function testDaysOptionIsOptionalWithDefault(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('days');
        $this->assertTrue($option->isValueOptional());
    }

    private function buildCommand(): CleanupStatsCommand
    {
        return new CleanupStatsCommand(
            $this->createStub(PageViewRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function createTester(CleanupStatsCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:stats:cleanup'));
    }
}
