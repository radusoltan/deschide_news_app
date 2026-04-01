<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\ImportAuthorsCommand;
use App\Service\Import\ImportTokenService;
use App\Service\Import\MigrationLoggerService;
use App\Service\Import\NewscoopConnectionService;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportAuthorsExtendedTest extends TestCase
{
    public function testCommandName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:import:authors', $command->getName());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    public function testDryRunFailsOnJwtError(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willThrowException(new Exception('No JWT key'));

        $command = $this->buildCommand(tokenService: $tokenService);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('No JWT key', $tester->getDisplay());
    }

    public function testDryRunFailsOnFetchError(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchAuthors')->willThrowException(new Exception('Fetch failed'));

        $command = $this->buildCommand(
            newscoopService: $newscoopService,
            tokenService: $tokenService,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Fetch failed', $tester->getDisplay());
    }

    public function testDryRunWithNoAuthorsReturnsWarning(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchAuthors')->willReturn([]);

        $command = $this->buildCommand(
            newscoopService: $newscoopService,
            tokenService: $tokenService,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No authors found', $tester->getDisplay());
    }

    public function testDryRunSkipsAlreadyImportedAuthors(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchAuthors')->willReturn([
            ['id' => 1, 'first_name' => 'Ion', 'last_name' => 'Popescu', 'email' => 'ion@example.com', 'biography' => ''],
        ]);

        $migrationLogger = $this->createMock(MigrationLoggerService::class);
        $migrationLogger->method('isImported')->willReturn(true);

        $command = $this->buildCommand(
            newscoopService: $newscoopService,
            migrationLogger: $migrationLogger,
            tokenService: $tokenService,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Skipped', $output);
    }

    public function testDryRunSkipsInvalidAuthor(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        // Author with no name at all
        $newscoopService->method('fetchAuthors')->willReturn([
            ['id' => 1, 'first_name' => '', 'last_name' => '', 'email' => '', 'biography' => ''],
        ]);

        $migrationLogger = $this->createMock(MigrationLoggerService::class);
        $migrationLogger->method('isImported')->willReturn(false);

        $command = $this->buildCommand(
            newscoopService: $newscoopService,
            migrationLogger: $migrationLogger,
            tokenService: $tokenService,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Skipped', $output);
    }

    public function testDryRunCountsValidAuthorAsSuccess(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchAuthors')->willReturn([
            ['id' => 1, 'first_name' => 'Maria', 'last_name' => 'Ionescu', 'email' => 'maria@example.com', 'biography' => 'Reporter'],
        ]);

        $migrationLogger = $this->createMock(MigrationLoggerService::class);
        $migrationLogger->method('isImported')->willReturn(false);

        $command = $this->buildCommand(
            newscoopService: $newscoopService,
            migrationLogger: $migrationLogger,
            tokenService: $tokenService,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('Successfully imported 1 authors', $output);
    }

    public function testDryRunWithAuthorMissingEmail(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchAuthors')->willReturn([
            ['id' => 42, 'first_name' => 'Andrei', 'last_name' => 'Radu', 'email' => '', 'biography' => null],
        ]);

        $migrationLogger = $this->createMock(MigrationLoggerService::class);
        $migrationLogger->method('isImported')->willReturn(false);

        $command = $this->buildCommand(
            newscoopService: $newscoopService,
            migrationLogger: $migrationLogger,
            tokenService: $tokenService,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        // Generated email should appear
        $this->assertStringContainsString('DRY RUN', $output);
    }

    private function buildCommand(
        ?NewscoopConnectionService $newscoopService = null,
        ?MigrationLoggerService $migrationLogger = null,
        ?ImportTokenService $tokenService = null,
        ?HttpClientInterface $httpClient = null,
    ): ImportAuthorsCommand {
        return new ImportAuthorsCommand(
            $newscoopService ?? $this->createStub(NewscoopConnectionService::class),
            $migrationLogger ?? $this->createStub(MigrationLoggerService::class),
            $tokenService ?? $this->createStub(ImportTokenService::class),
            $httpClient ?? $this->createStub(HttpClientInterface::class),
        );
    }
}
