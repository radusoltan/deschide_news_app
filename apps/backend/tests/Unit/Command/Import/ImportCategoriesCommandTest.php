<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

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
class ImportCategoriesCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:import:categories', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('locale'));
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    public function testExecuteFailsWhenJwtThrows(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willThrowException(new Exception('No key'));

        $command = $this->buildCommand(tokenService: $tokenService);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('No key', $tester->getDisplay());
    }

    public function testExecuteDryRunOutputsCorrectInfo(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('tok');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchCategories')->willReturn([]);

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
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('FAZA 1', $output);
    }

    public function testExecuteDryRunWithLocaleOption(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('tok');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchCategories')->willReturn([]);

        $command = $this->buildCommand(
            newscoopConnection: $newscoopService,
            tokenService: $tokenService,
        );
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true, '--locale' => 'en']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('en', $tester->getDisplay());
    }

    private function buildCommand(
        ?NewscoopConnectionService $newscoopConnection = null,
        ?MigrationLoggerService $migrationLogger = null,
        ?ImportTokenService $tokenService = null,
        ?HttpClientInterface $httpClient = null,
    ): ImportCategoriesCommand {
        return new ImportCategoriesCommand(
            $newscoopConnection ?? $this->createStub(NewscoopConnectionService::class),
            $migrationLogger ?? $this->createStub(MigrationLoggerService::class),
            $tokenService ?? $this->createStub(ImportTokenService::class),
            $httpClient ?? $this->createStub(HttpClientInterface::class),
        );
    }
}
