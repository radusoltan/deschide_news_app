<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\FixArticleCategoriesCommand;
use App\Command\Import\ImportCompleteCategoriesCommand;
use App\Command\Import\ImportArticlesByCategoryCommand;
use App\Repository\CategoryRepository;
use App\Service\Import\ImportTokenService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class FixAndCompleteCommandsTest extends TestCase
{
    // ── FixArticleCategoriesCommand ─────────────────────────────────────────

    public function testFixArticleCategoriesCommandName(): void
    {
        $command = $this->buildFixArticleCategoriesCommand();
        $this->assertSame('app:import:fix-article-categories', $command->getName());
    }

    public function testFixArticleCategoriesCommandHasDryRunOption(): void
    {
        $command = $this->buildFixArticleCategoriesCommand();
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    public function testFixArticleCategoriesDryRun(): void
    {
        $defaultConn = $this->createMock(Connection::class);
        $defaultConn->method('fetchAllAssociative')->willReturn([]);

        $newscoopConn = $this->createMock(Connection::class);

        $command = $this->buildFixArticleCategoriesCommand(
            newscoopConnection: $newscoopConn,
            defaultConnection: $defaultConn,
        );
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('DRY RUN', $tester->getDisplay());
    }

    // ── ImportCompleteCategoriesCommand ────────────────────────────────────

    public function testImportCompleteCategoriesCommandName(): void
    {
        $command = $this->buildImportCompleteCategoriesCommand();
        $this->assertSame('app:import:categories-complete', $command->getName());
    }

    public function testImportCompleteCategoriesCommandHasOptions(): void
    {
        $command = $this->buildImportCompleteCategoriesCommand();
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('skip-translations'));
        $this->assertTrue($command->getDefinition()->hasOption('force'));
    }

    public function testImportCompleteCategoriesDryRunWhenFileNotFound(): void
    {
        $command = $this->buildImportCompleteCategoriesCommand();
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        // Will fail because JSON file doesn't exist in test env, but should handle gracefully
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Opțiunea A', $output);
    }

    private function buildFixArticleCategoriesCommand(
        ?Connection $newscoopConnection = null,
        ?Connection $defaultConnection = null,
        ?LoggerInterface $logger = null,
    ): FixArticleCategoriesCommand {
        return new FixArticleCategoriesCommand(
            $newscoopConnection ?? $this->createStub(Connection::class),
            $defaultConnection ?? $this->createStub(Connection::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    private function buildImportCompleteCategoriesCommand(
        ?EntityManagerInterface $em = null,
        ?ImportTokenService $tokenService = null,
    ): ImportCompleteCategoriesCommand {
        return new ImportCompleteCategoriesCommand(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $tokenService ?? $this->createStub(ImportTokenService::class),
        );
    }
}
