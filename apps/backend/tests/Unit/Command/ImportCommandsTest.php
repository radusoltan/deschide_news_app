<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\Import\ImportArticlesCommand;
use App\Command\Import\ImportAuthorsCommand;
use App\Command\Import\ImportCategoriesCommand;
use App\Service\Import\ImportTokenService;
use App\Service\Import\MigrationLoggerService;
use App\Service\Import\NewscoopConnectionService;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportCommandsTest extends TestCase
{
    // ── ImportArticlesCommand ────────────────────────────────────────────────

    public function testImportArticlesCommandName(): void
    {
        $command = new ImportArticlesCommand(
            $this->createStub(NewscoopConnectionService::class),
            $this->createStub(MigrationLoggerService::class),
            $this->createStub(ImportTokenService::class),
            $this->createStub(HttpClientInterface::class),
        );
        $this->assertSame('app:import:articles', $command->getName());
    }

    public function testImportArticlesCommandHasOptions(): void
    {
        $command = new ImportArticlesCommand(
            $this->createStub(NewscoopConnectionService::class),
            $this->createStub(MigrationLoggerService::class),
            $this->createStub(ImportTokenService::class),
            $this->createStub(HttpClientInterface::class),
        );
        $this->assertTrue($command->getDefinition()->hasOption('locale'));
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    public function testImportArticlesDryRunExitsWhenJwtFails(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willThrowException(new Exception('JWT error'));

        $command = new ImportArticlesCommand(
            $this->createStub(NewscoopConnectionService::class),
            $this->createStub(MigrationLoggerService::class),
            $tokenService,
            $this->createStub(HttpClientInterface::class),
        );
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('JWT error', $tester->getDisplay());
    }

    public function testImportArticlesDryRunSucceeds(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('fake.jwt.token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchArticles')->willReturn([]);

        $command = new ImportArticlesCommand(
            $newscoopService,
            $this->createStub(MigrationLoggerService::class),
            $tokenService,
            $this->createStub(HttpClientInterface::class),
        );
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('DRY RUN', $tester->getDisplay());
    }

    // ── ImportAuthorsCommand ─────────────────────────────────────────────────

    public function testImportAuthorsCommandName(): void
    {
        $command = new ImportAuthorsCommand(
            $this->createStub(NewscoopConnectionService::class),
            $this->createStub(MigrationLoggerService::class),
            $this->createStub(ImportTokenService::class),
            $this->createStub(HttpClientInterface::class),
        );
        $this->assertSame('app:import:authors', $command->getName());
    }

    public function testImportAuthorsCommandHasOptions(): void
    {
        $command = new ImportAuthorsCommand(
            $this->createStub(NewscoopConnectionService::class),
            $this->createStub(MigrationLoggerService::class),
            $this->createStub(ImportTokenService::class),
            $this->createStub(HttpClientInterface::class),
        );
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    public function testImportAuthorsFailsOnJwtError(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willThrowException(new Exception('No JWT key'));

        $command = new ImportAuthorsCommand(
            $this->createStub(NewscoopConnectionService::class),
            $this->createStub(MigrationLoggerService::class),
            $tokenService,
            $this->createStub(HttpClientInterface::class),
        );
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function testImportAuthorsDryRunWithNoAuthors(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchAuthors')->willReturn([]);

        $command = new ImportAuthorsCommand(
            $newscoopService,
            $this->createStub(MigrationLoggerService::class),
            $tokenService,
            $this->createStub(HttpClientInterface::class),
        );
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('DRY RUN', $tester->getDisplay());
    }
}
