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
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('isOpen')->willReturn(true);
    }

    public function testRefusesInProd(): void
    {
        $command = new DevResetCommand('prod', $this->em);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('DOAR în dev/test', $tester->getDisplay());
    }

    public function testRefusesInStaging(): void
    {
        $command = new DevResetCommand('staging', $this->em);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testAllowsDevEnvironment(): void
    {
        $command = new DevResetCommand('dev', $this->em);
        $app = $this->createAppWithStubs($command);

        $tester = new CommandTester($command);
        $tester->execute(['--skip-fixtures' => true, '--skip-elasticsearch' => true, '--skip-import' => true], ['interactive' => false]);

        $display = $tester->getDisplay();
        $this->assertStringNotContainsString('DOAR în dev/test', $display);
    }

    public function testAllowsTestEnvironment(): void
    {
        $command = new DevResetCommand('test', $this->em);
        $app = $this->createAppWithStubs($command);

        $tester = new CommandTester($command);
        $tester->execute(['--skip-fixtures' => true, '--skip-elasticsearch' => true, '--skip-import' => true], ['interactive' => false]);

        $display = $tester->getDisplay();
        $this->assertStringNotContainsString('DOAR în dev/test', $display);
    }

    public function testExecutesAllSteps(): void
    {
        $executedCommands = [];

        $command = new DevResetCommand('dev', $this->em);
        $app = $this->createAppWithTrackingStubs($command, $executedCommands);

        $tester = new CommandTester($command);
        $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();

        // Verify all steps reported
        $this->assertStringContainsString('Drop database schema', $display);
        $this->assertStringContainsString('Run migrations', $display);
        $this->assertStringContainsString('Load fixtures', $display);
        $this->assertStringContainsString('Import RSS', $display);
        $this->assertStringContainsString('Clear cache pools', $display);
        $this->assertStringContainsString('Reindex Elasticsearch', $display);
        $this->assertStringContainsString('Raport final', $display);

        // Verify sub-commands were called
        $this->assertContains('doctrine:schema:drop', $executedCommands);
        $this->assertContains('doctrine:migrations:migrate', $executedCommands);
        $this->assertContains('doctrine:fixtures:load', $executedCommands);
        $this->assertContains('app:import:rss-feed', $executedCommands);
        $this->assertContains('cache:pool:clear', $executedCommands);
        $this->assertContains('app:elasticsearch:index-articles', $executedCommands);
    }

    public function testSkipOptionsRespected(): void
    {
        $executedCommands = [];

        $command = new DevResetCommand('dev', $this->em);
        $app = $this->createAppWithTrackingStubs($command, $executedCommands);

        $tester = new CommandTester($command);
        $tester->execute([
            '--skip-fixtures' => true,
            '--skip-import' => true,
            '--skip-elasticsearch' => true,
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $this->assertNotContains('doctrine:fixtures:load', $executedCommands);
        $this->assertNotContains('app:import:rss-feed', $executedCommands);
        $this->assertNotContains('app:elasticsearch:index-articles', $executedCommands);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('SKIPPED', $display);
    }

    public function testSkipImportAloneWorks(): void
    {
        $executedCommands = [];

        $command = new DevResetCommand('dev', $this->em);
        $app = $this->createAppWithTrackingStubs($command, $executedCommands);

        $tester = new CommandTester($command);
        $tester->execute([
            '--skip-import' => true,
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Fixtures should run, import should not
        $this->assertContains('doctrine:fixtures:load', $executedCommands);
        $this->assertNotContains('app:import:rss-feed', $executedCommands);
    }

    public function testImportSkippedWhenFixturesSkipped(): void
    {
        $executedCommands = [];

        $command = new DevResetCommand('dev', $this->em);
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

        $command = new DevResetCommand('dev', $this->em);
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

        $command = new DevResetCommand('dev', $this->em);
        $app = $this->createAppWithTrackingStubs($command, $executedCommands);

        $tester = new CommandTester($command);
        $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertNotContains('app:editorial:batch-ingest', $executedCommands);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('SKIPPED', $display);
    }

    public function testImportLimitOption(): void
    {
        $executedCommands = [];

        $command = new DevResetCommand('dev', $this->em);
        $app = $this->createAppWithTrackingStubs($command, $executedCommands);

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
            'doctrine:schema:drop',
            'doctrine:migrations:migrate',
            'doctrine:fixtures:load',
            'app:import:rss-feed',
            'cache:pool:clear',
            'app:elasticsearch:index-articles',
            'app:editorial:batch-ingest',
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
}
