<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Editorial;

use App\Command\Editorial\DevResetCommand;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class DevResetCommandTest extends TestCase
{
    private string $vaultPath;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->vaultPath = sys_get_temp_dir() . '/vault_reset_test_' . uniqid();
        mkdir($this->vaultPath . '/articles', 0o755, true);

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('isOpen')->willReturn(true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->vaultPath);
    }

    public function testRefusesInProd(): void
    {
        $command = new DevResetCommand('prod', $this->vaultPath, $this->em);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('DOAR în dev/test', $tester->getDisplay());
    }

    public function testRefusesInStaging(): void
    {
        $command = new DevResetCommand('staging', $this->vaultPath, $this->em);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testAllowsDevEnvironment(): void
    {
        $command = new DevResetCommand('dev', $this->vaultPath, $this->em);
        $app = $this->createAppWithStubs($command);

        $tester = new CommandTester($command);
        $tester->execute(['--skip-fixtures' => true, '--skip-elasticsearch' => true, '--skip-import' => true], ['interactive' => false]);

        $display = $tester->getDisplay();
        $this->assertStringNotContainsString('DOAR în dev/test', $display);
    }

    public function testAllowsTestEnvironment(): void
    {
        $command = new DevResetCommand('test', $this->vaultPath, $this->em);
        $app = $this->createAppWithStubs($command);

        $tester = new CommandTester($command);
        $tester->execute(['--skip-fixtures' => true, '--skip-elasticsearch' => true, '--skip-import' => true], ['interactive' => false]);

        $display = $tester->getDisplay();
        $this->assertStringNotContainsString('DOAR în dev/test', $display);
    }

    public function testExecutesAllSteps(): void
    {
        $executedCommands = [];

        $command = new DevResetCommand('dev', $this->vaultPath, $this->em);
        $app = $this->createAppWithTrackingStubs($command, $executedCommands);

        $tester = new CommandTester($command);
        $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();

        // Verify all steps reported
        $this->assertStringContainsString('Purge vault orphans', $display);
        $this->assertStringContainsString('Clear vault articles/', $display);
        $this->assertStringContainsString('Drop database schema', $display);
        $this->assertStringContainsString('Run migrations', $display);
        $this->assertStringContainsString('Load fixtures', $display);
        $this->assertStringContainsString('Import RSS', $display);
        $this->assertStringContainsString('Sync vault from DB', $display);
        $this->assertStringContainsString('Clear cache pools', $display);
        $this->assertStringContainsString('Reindex Elasticsearch', $display);
        $this->assertStringContainsString('Verify vault consistency', $display);
        $this->assertStringContainsString('Raport final', $display);

        // Verify sub-commands were called
        $this->assertContains('app:vault:purge', $executedCommands);
        $this->assertContains('doctrine:schema:drop', $executedCommands);
        $this->assertContains('doctrine:migrations:migrate', $executedCommands);
        $this->assertContains('doctrine:fixtures:load', $executedCommands);
        $this->assertContains('app:import:rss-feed', $executedCommands);
        $this->assertContains('app:vault:sync-from-db', $executedCommands);
        $this->assertContains('cache:pool:clear', $executedCommands);
        $this->assertContains('app:elasticsearch:index-articles', $executedCommands);
        $this->assertContains('app:vault:verify', $executedCommands);
    }

    public function testSkipOptionsRespected(): void
    {
        $executedCommands = [];

        $command = new DevResetCommand('dev', $this->vaultPath, $this->em);
        $app = $this->createAppWithTrackingStubs($command, $executedCommands);

        $tester = new CommandTester($command);
        $tester->execute([
            '--skip-fixtures' => true,
            '--skip-import' => true,
            '--skip-elasticsearch' => true,
            '--skip-vault-sync' => true,
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $this->assertNotContains('doctrine:fixtures:load', $executedCommands);
        $this->assertNotContains('app:import:rss-feed', $executedCommands);
        $this->assertNotContains('app:elasticsearch:index-articles', $executedCommands);
        $this->assertNotContains('app:vault:purge', $executedCommands);
        $this->assertNotContains('app:vault:sync-from-db', $executedCommands);
        $this->assertNotContains('app:vault:verify', $executedCommands);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('SKIPPED', $display);
    }

    public function testSkipImportAloneWorks(): void
    {
        $executedCommands = [];

        $command = new DevResetCommand('dev', $this->vaultPath, $this->em);
        $app = $this->createAppWithTrackingStubs($command, $executedCommands);

        $tester = new CommandTester($command);
        $tester->execute([
            '--skip-import' => true,
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Fixtures should run, import should not
        $this->assertContains('doctrine:fixtures:load', $executedCommands);
        $this->assertNotContains('app:import:rss-feed', $executedCommands);

        // Vault sync + verify should still run
        $this->assertContains('app:vault:sync-from-db', $executedCommands);
        $this->assertContains('app:vault:verify', $executedCommands);
    }

    public function testImportSkippedWhenFixturesSkipped(): void
    {
        $executedCommands = [];

        $command = new DevResetCommand('dev', $this->vaultPath, $this->em);
        $app = $this->createAppWithTrackingStubs($command, $executedCommands);

        $tester = new CommandTester($command);
        $tester->execute([
            '--skip-fixtures' => true,
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Import should also be skipped when fixtures are skipped (no categories to map to)
        $this->assertNotContains('doctrine:fixtures:load', $executedCommands);
        $this->assertNotContains('app:import:rss-feed', $executedCommands);
    }

    public function testEnrichOptionDispatchesBatchIngest(): void
    {
        $executedCommands = [];

        $command = new DevResetCommand('dev', $this->vaultPath, $this->em);
        $app = $this->createAppWithTrackingStubs($command, $executedCommands);

        $tester = new CommandTester($command);
        $tester->execute([
            '--enrich' => true,
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertContains('app:editorial:batch-ingest', $executedCommands);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Dispatch AI ingestion', $display);
    }

    public function testEnrichSkippedByDefault(): void
    {
        $executedCommands = [];

        $command = new DevResetCommand('dev', $this->vaultPath, $this->em);
        $app = $this->createAppWithTrackingStubs($command, $executedCommands);

        $tester = new CommandTester($command);
        $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertNotContains('app:editorial:batch-ingest', $executedCommands);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('SKIPPED', $display);
    }

    public function testClearsVaultArticlesDirectory(): void
    {
        file_put_contents($this->vaultPath . '/articles/test.md', 'content');

        $command = new DevResetCommand('dev', $this->vaultPath, $this->em);
        $app = $this->createAppWithStubs($command);

        $tester = new CommandTester($command);
        $tester->execute(['--skip-fixtures' => true, '--skip-elasticsearch' => true, '--skip-import' => true], ['interactive' => false]);

        $files = glob($this->vaultPath . '/articles/*');
        $this->assertEmpty($files);
    }

    public function testImportLimitOption(): void
    {
        $executedCommands = [];
        $commandArgs = [];

        $command = new DevResetCommand('dev', $this->vaultPath, $this->em);
        $app = $this->createAppWithArgTrackingStubs($command, $executedCommands, $commandArgs);

        $tester = new CommandTester($command);
        $tester->execute(['--import-limit' => '50'], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertContains('app:import:rss-feed', $executedCommands);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Import RSS (50 articles)', $display);
    }

    private function createAppWithStubs(DevResetCommand $command): Application
    {
        $executedCommands = [];

        return $this->createAppWithTrackingStubs($command, $executedCommands);
    }

    /**
     * @param list<string> $executedCommands
     */
    private function createAppWithTrackingStubs(DevResetCommand $command, array &$executedCommands): Application
    {
        $app = new Application();
        $app->addCommand($command);

        $stubNames = [
            'app:vault:purge',
            'doctrine:schema:drop',
            'doctrine:migrations:migrate',
            'doctrine:fixtures:load',
            'app:import:rss-feed',
            'app:vault:sync-from-db',
            'cache:pool:clear',
            'app:elasticsearch:index-articles',
            'app:editorial:batch-ingest',
            'app:vault:verify',
        ];

        foreach ($stubNames as $name) {
            $stub = new class($name, $executedCommands) extends Command {
                /** @param list<string> $tracker */
                public function __construct(string $name, private array &$tracker)
                {
                    parent::__construct($name);
                    $this->ignoreValidationErrors();
                }

                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    $this->tracker[] = $this->getName();

                    return Command::SUCCESS;
                }
            };
            $app->addCommand($stub);
        }

        return $app;
    }

    /**
     * @param list<string> $executedCommands
     * @param array<string, array<string, mixed>> $commandArgs
     */
    private function createAppWithArgTrackingStubs(DevResetCommand $command, array &$executedCommands, array &$commandArgs): Application
    {
        $app = new Application();
        $app->addCommand($command);

        $stubNames = [
            'app:vault:purge',
            'doctrine:schema:drop',
            'doctrine:migrations:migrate',
            'doctrine:fixtures:load',
            'app:import:rss-feed',
            'app:vault:sync-from-db',
            'cache:pool:clear',
            'app:elasticsearch:index-articles',
            'app:editorial:batch-ingest',
            'app:vault:verify',
        ];

        foreach ($stubNames as $name) {
            $stub = new class($name, $executedCommands, $commandArgs) extends Command {
                /** @param list<string> $tracker */
                public function __construct(string $name, private array &$tracker, private array &$argTracker)
                {
                    parent::__construct($name);
                    $this->ignoreValidationErrors();
                }

                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    $cmdName = $this->getName();
                    $this->tracker[] = $cmdName;

                    return Command::SUCCESS;
                }
            };
            $app->addCommand($stub);
        }

        return $app;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($path);
    }
}
