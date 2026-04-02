<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\ImportArticlesCommand;
use App\Service\Import\ImportTokenService;
use App\Service\Import\MigrationLoggerService;
use App\Service\Import\NewscoopConnectionService;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportArticlesExtendedTest extends TestCase
{
    public function testCommandName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:import:articles', $command->getName());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('locale'));
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('offset'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    public function testDryRunFailsWhenJwtFails(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willThrowException(new Exception('JWT error'));

        $command = $this->buildCommand(tokenService: $tokenService);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('JWT error', $tester->getDisplay());
    }

    public function testDryRunFailsWhenFetchFails(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('valid.jwt.token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchArticles')->willThrowException(new Exception('DB offline'));

        $command = $this->buildCommand(
            newscoopService: $newscoopService,
            tokenService: $tokenService,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('DB offline', $tester->getDisplay());
    }

    public function testDryRunWithNoArticlesWarns(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('valid.jwt.token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchArticles')->willReturn([]);

        $command = $this->buildCommand(
            newscoopService: $newscoopService,
            tokenService: $tokenService,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No articles found', $tester->getDisplay());
    }

    public function testDryRunWithArticlesThatHaveNoContent(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('valid.jwt.token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchArticles')->willReturn([
            [
                'Number' => 101,
                'FTitlu' => 'Test Article',
                'FContinut' => '',
                'Published' => 'Y',
                'OnFrontPage' => 'N',
                'NrSection' => 1,
            ],
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
        // Article with no content is skipped
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Skipped', $output);
    }

    public function testDryRunSkipsAlreadyImportedArticles(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('valid.jwt.token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchArticles')->willReturn([
            [
                'Number' => 101,
                'FTitlu' => 'Test Article',
                'FContinut' => 'Some content here',
                'Published' => 'Y',
                'OnFrontPage' => 'N',
                'NrSection' => 1,
            ],
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

    public function testDryRunWithValidArticleCountsAsSuccess(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('valid.jwt.token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchArticles')->willReturn([
            [
                'Number' => 101,
                'FTitlu' => 'Test Article',
                'FContinut' => 'Some content here',
                'Published' => 'Y',
                'OnFrontPage' => 'Y',
                'NrSection' => 1,
                'Keywords' => 'test,keywords',
                'PublishDate' => '2024-01-15 10:00:00',
                'FBREAKING_NEWS' => '0',
                'FNEWS_ALERT' => '0',
                'FFLASH' => '0',
            ],
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
        $this->assertStringContainsString('Successfully imported 1 articles', $output);
    }

    public function testCommandWithEnLocale(): void
    {
        $tokenService = $this->createMock(ImportTokenService::class);
        $tokenService->method('getImportToken')->willReturn('valid.jwt.token');

        $newscoopService = $this->createMock(NewscoopConnectionService::class);
        $newscoopService->method('fetchArticles')->willReturn([]);

        $command = $this->buildCommand(
            newscoopService: $newscoopService,
            tokenService: $tokenService,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--locale' => 'en', '--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('en', $output);
    }

    private function buildCommand(
        ?NewscoopConnectionService $newscoopService = null,
        ?MigrationLoggerService $migrationLogger = null,
        ?ImportTokenService $tokenService = null,
        ?HttpClientInterface $httpClient = null,
    ): ImportArticlesCommand {
        return new ImportArticlesCommand(
            $newscoopService ?? $this->createStub(NewscoopConnectionService::class),
            $migrationLogger ?? $this->createStub(MigrationLoggerService::class),
            $tokenService ?? $this->createStub(ImportTokenService::class),
            $httpClient ?? $this->createStub(HttpClientInterface::class),
        );
    }
}
