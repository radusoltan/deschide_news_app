<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\ImportTranslationsCommand;
use App\Service\Import\ImportTokenService;
use App\Service\Import\MigrationLoggerService;
use App\Service\Import\NewscoopConnectionService;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportTranslationsCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:import:translations', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('offset'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    public function testExecuteFailsWhenJwtThrows(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willThrowException(new Exception('No JWT'));

        $command = $this->buildCommand(tokenService: $tokenService);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('No JWT', $tester->getDisplay());
    }

    public function testExecuteDryRunShowsCorrectTitle(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('tok');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchTranslations')->willReturn([]);

        $command = $this->buildCommand(
            newscoopConnection: $newscoopService,
            tokenService: $tokenService,
        );
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('FAZA 5', $output);
        $this->assertStringContainsString('DRY RUN', $output);
    }

    public function testExecuteDryRunWithOffsetOption(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('tok');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchTranslations')->willReturn([]);

        $command = $this->buildCommand(
            newscoopConnection: $newscoopService,
            tokenService: $tokenService,
        );
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true, '--offset' => '100']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('100', $tester->getDisplay());
    }

    private function buildCommand(
        ?NewscoopConnectionService $newscoopConnection = null,
        ?MigrationLoggerService $migrationLogger = null,
        ?ImportTokenService $tokenService = null,
        ?HttpClientInterface $httpClient = null,
    ): ImportTranslationsCommand {
        return new ImportTranslationsCommand(
            $newscoopConnection ?? $this->createStub(NewscoopConnectionService::class),
            $migrationLogger ?? $this->createStub(MigrationLoggerService::class),
            $tokenService ?? $this->createStub(ImportTokenService::class),
            $httpClient ?? $this->createStub(HttpClientInterface::class),
        );
    }
}
