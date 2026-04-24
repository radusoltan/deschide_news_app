<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Editorial;

use App\Command\Editorial\DevResetCommand;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for DevResetCommand against the post-aa6824f pipeline.
 *
 * Strategy:
 *  - Stub every sub-command DevResetCommand dispatches; record invocations.
 *  - Mock the EntityManager + Connection so cullArticles short-circuits on
 *    "no articles" and printCounts can fetch zeroed counts without erroring.
 *  - Assert orchestration only (step labels in display, sub-commands invoked,
 *    skip-flag semantics). DB-level cull logic and ES indexing are out of
 *    scope for unit tests — they belong to integration/functional layers.
 */
class DevResetCommandTest extends TestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);

        // cullArticles: SELECT COUNT(*) FROM articles → 0 triggers early return.
        // printCounts: every fetchOne in the summary block also gets 0.
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(0);
        $this->em->method('getConnection')->willReturn($connection);
    }

    public function testRefusesInProd(): void
    {
        $command = new DevResetCommand('prod', $this->em);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('DOAR în dev/test', $tester->getDisplay());
    }

    public function testRefusesInStaging(): void
    {
        $command = new DevResetCommand('staging', $this->em);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testAllowsDevEnvironment(): void
    {
        $command = new DevResetCommand('dev', $this->em);
        $executed = [];
        $this->registerStubs($command, $executed);

        $tester = new CommandTester($command);
        $tester->execute(
            ['--skip-csv' => true, '--skip-elasticsearch' => true, '--skip-topics' => true],
            ['interactive' => false],
        );

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringNotContainsString('DOAR în dev/test', $tester->getDisplay());
    }

    public function testAllowsTestEnvironment(): void
    {
        $command = new DevResetCommand('test', $this->em);
        $executed = [];
        $this->registerStubs($command, $executed);

        $tester = new CommandTester($command);
        $tester->execute(
            ['--skip-csv' => true, '--skip-elasticsearch' => true, '--skip-topics' => true],
            ['interactive' => false],
        );

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringNotContainsString('DOAR în dev/test', $tester->getDisplay());
    }

    public function testFullPipelineInvokesEverySubCommand(): void
    {
        $command = new DevResetCommand('dev', $this->em);
        $executed = [];
        $this->registerStubs($command, $executed);

        $tester = new CommandTester($command);
        // Provide a fake CSV path so findCsvPath() doesn't matter; the stub
        // for app:import:csv-legacy-articles ignores the value.
        $tester->execute(['--csv-path' => '/tmp/dummy.csv'], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // All ten dispatched sub-commands must have run at least once.
        self::assertContains('doctrine:schema:drop', $executed);
        self::assertContains('doctrine:migrations:migrate', $executed);
        self::assertContains('doctrine:fixtures:load', $executed);
        self::assertContains('app:import:csv-legacy-articles', $executed);
        self::assertContains('app:fixtures:generate-translations', $executed);
        self::assertContains('app:fixtures:download-images', $executed);
        self::assertContains('cache:pool:clear', $executed);
        self::assertContains('app:elasticsearch:create-index', $executed);
        self::assertContains('app:elasticsearch:index-articles', $executed);
        self::assertContains('cache:clear', $executed);

        // CSV import runs three phases (articles, translations, links).
        self::assertSame(
            3,
            \count(array_filter($executed, static fn (string $name) => $name === 'app:import:csv-legacy-articles')),
            'CSV import must run all three phases (articles, translations, links)',
        );

        $display = $tester->getDisplay();
        self::assertStringContainsString('Dev Reset — Full Fixtures Pipeline', $display);
        self::assertStringContainsString('Drop database schema', $display);
        self::assertStringContainsString('Run migrations', $display);
        self::assertStringContainsString('Load fixtures', $display);
        self::assertStringContainsString('CSV import: articles', $display);
        self::assertStringContainsString('CSV import: translations', $display);
        self::assertStringContainsString('CSV import: links', $display);
        self::assertStringContainsString('Article selection (cull per caps)', $display);
        self::assertStringContainsString('Raport final', $display);
    }

    public function testSkipCsvOmitsImportSelectionAndDownstreamSteps(): void
    {
        $command = new DevResetCommand('dev', $this->em);
        $executed = [];
        $this->registerStubs($command, $executed);

        $tester = new CommandTester($command);
        $tester->execute(['--skip-csv' => true], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // CSV import skipped.
        self::assertNotContains('app:import:csv-legacy-articles', $executed);
        // Translations and image download both gate on !skipCsv → skipped transitively.
        self::assertNotContains('app:fixtures:generate-translations', $executed);
        self::assertNotContains('app:fixtures:download-images', $executed);

        $display = $tester->getDisplay();
        self::assertStringContainsString('SKIPPED', $display);
        self::assertStringNotContainsString('Article selection (cull per caps)', $display);
    }

    public function testSkipTopicsOmitsTopicsFromFixtureGroups(): void
    {
        $command = new DevResetCommand('dev', $this->em);
        $executed = [];
        $this->registerStubs($command, $executed);

        $tester = new CommandTester($command);
        $tester->execute(
            ['--skip-topics' => true, '--skip-csv' => true, '--skip-elasticsearch' => true],
            ['interactive' => false],
        );

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains('doctrine:fixtures:load', $executed);

        $display = $tester->getDisplay();
        // The fixtures step header lists groups in parens; with --skip-topics
        // the suffix ", topics)" must not appear on that line.
        self::assertStringContainsString('Load fixtures (categories, menu, user, live-pipeline, app-settings)', $display);
        self::assertDoesNotMatchRegularExpression('/Load fixtures \([^)]*topics[^)]*\)/', $display);
    }

    public function testSkipElasticsearchOmitsEsCommands(): void
    {
        $command = new DevResetCommand('dev', $this->em);
        $executed = [];
        $this->registerStubs($command, $executed);

        $tester = new CommandTester($command);
        $tester->execute(
            ['--skip-elasticsearch' => true, '--skip-csv' => true],
            ['interactive' => false],
        );

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertNotContains('app:elasticsearch:create-index', $executed);
        self::assertNotContains('app:elasticsearch:index-articles', $executed);
    }

    public function testSkipTranslationsOmitsTranslationStep(): void
    {
        $command = new DevResetCommand('dev', $this->em);
        $executed = [];
        $this->registerStubs($command, $executed);

        $tester = new CommandTester($command);
        $tester->execute(
            ['--skip-translations' => true, '--csv-path' => '/tmp/dummy.csv', '--skip-elasticsearch' => true],
            ['interactive' => false],
        );

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertNotContains('app:fixtures:generate-translations', $executed);
        // Image download still runs (independent flag, gated only on !skipImages && !skipCsv).
        self::assertContains('app:fixtures:download-images', $executed);
    }

    public function testSkipImagesOmitsImageDownloadStep(): void
    {
        $command = new DevResetCommand('dev', $this->em);
        $executed = [];
        $this->registerStubs($command, $executed);

        $tester = new CommandTester($command);
        $tester->execute(
            ['--skip-images' => true, '--csv-path' => '/tmp/dummy.csv', '--skip-elasticsearch' => true],
            ['interactive' => false],
        );

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertNotContains('app:fixtures:download-images', $executed);
        // Translations still runs (independent flag, gated only on !skipTranslations && !skipCsv).
        self::assertContains('app:fixtures:generate-translations', $executed);
    }

    /**
     * Register stubs for every sub-command DevResetCommand dispatches.
     * Each stub records its name into $executed when invoked.
     *
     * @param list<string> $executed
     */
    private function registerStubs(DevResetCommand $command, array &$executed): Application
    {
        $app = new Application();
        $app->addCommand($command);

        $stubNames = [
            'doctrine:schema:drop',
            'doctrine:migrations:migrate',
            'doctrine:fixtures:load',
            'app:import:csv-legacy-articles',
            'app:fixtures:generate-translations',
            'app:fixtures:download-images',
            'cache:pool:clear',
            'cache:clear',
            'app:elasticsearch:create-index',
            'app:elasticsearch:index-articles',
        ];

        foreach ($stubNames as $name) {
            $stub = new class($name, $executed) extends Command {
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
