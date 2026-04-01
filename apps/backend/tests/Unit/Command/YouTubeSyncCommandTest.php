<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\YouTubeSyncCommand;
use App\Repository\VideoShowRepository;
use App\Service\YouTubeSyncService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class YouTubeSyncCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $service = $this->createStub(YouTubeSyncService::class);
        $repo = $this->createStub(VideoShowRepository::class);
        $command = new YouTubeSyncCommand($service, $repo);
        $this->assertSame('app:youtube:sync', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $service = $this->createStub(YouTubeSyncService::class);
        $repo = $this->createStub(VideoShowRepository::class);
        $command = new YouTubeSyncCommand($service, $repo);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasSourceArgument(): void
    {
        $service = $this->createStub(YouTubeSyncService::class);
        $repo = $this->createStub(VideoShowRepository::class);
        $command = new YouTubeSyncCommand($service, $repo);
        $this->assertTrue($command->getDefinition()->hasArgument('source'));
        $this->assertTrue($command->getDefinition()->hasOption('playlist'));
        $this->assertTrue($command->getDefinition()->hasOption('show'));
        $this->assertTrue($command->getDefinition()->hasOption('max'));
        $this->assertTrue($command->getDefinition()->hasOption('stats-only'));
    }

    public function testExecuteStatsOnlyMode(): void
    {
        $service = $this->createMock(YouTubeSyncService::class);
        $service->method('updateVideoStats')->willReturn(['updated' => 5, 'errors' => 0]);

        $repo = $this->createStub(VideoShowRepository::class);

        $command = new YouTubeSyncCommand($service, $repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['source' => 'dummy', '--stats-only' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Stats update completed', $tester->getDisplay());
    }

    public function testExecuteStatsOnlyModeWithErrors(): void
    {
        $service = $this->createMock(YouTubeSyncService::class);
        $service->method('updateVideoStats')->willReturn(['updated' => 2, 'errors' => 3]);

        $repo = $this->createStub(VideoShowRepository::class);

        $command = new YouTubeSyncCommand($service, $repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['source' => 'dummy', '--stats-only' => true]);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function testExecuteWithShowNotFound(): void
    {
        $service = $this->createStub(YouTubeSyncService::class);

        $repo = $this->createMock(VideoShowRepository::class);
        $repo->method('findBySlug')->willReturn(null);

        $command = new YouTubeSyncCommand($service, $repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['source' => 'UCtest123', '--show' => 'nonexistent-show']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }

    public function testExecuteWithUnresolvableChannelFails(): void
    {
        $service = $this->createMock(YouTubeSyncService::class);
        $service->method('resolveChannelId')->willReturn(null);

        $repo = $this->createStub(VideoShowRepository::class);

        $command = new YouTubeSyncCommand($service, $repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['source' => 'invalid-channel-source']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Could not resolve', $tester->getDisplay());
    }

    public function testExecuteFromChannelSucceeds(): void
    {
        $service = $this->createMock(YouTubeSyncService::class);
        $service->method('resolveChannelId')->willReturn('UCactualId');
        $service->method('syncFromChannel')->willReturn(['new' => 10, 'updated' => 2, 'errors' => 0]);

        $repo = $this->createStub(VideoShowRepository::class);

        $command = new YouTubeSyncCommand($service, $repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['source' => '@channel-handle']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Sync completed', $tester->getDisplay());
    }

    public function testExecuteFromPlaylistSucceeds(): void
    {
        $service = $this->createMock(YouTubeSyncService::class);
        $service->method('syncFromPlaylist')->willReturn(['new' => 5, 'updated' => 1, 'errors' => 0]);

        $repo = $this->createStub(VideoShowRepository::class);

        $command = new YouTubeSyncCommand($service, $repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['source' => 'PLplaylistId', '--playlist' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Sync completed', $tester->getDisplay());
    }
}
