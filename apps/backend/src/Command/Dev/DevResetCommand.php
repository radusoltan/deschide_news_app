<?php

declare(strict_types=1);

namespace App\Command\Dev;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:dev:reset',
    description: 'Reset complet dev: drop DB + fixtures + CSV import + reindex',
)]
final class DevResetCommand extends Command
{
    private const DEFAULT_CSV_GLOB = '/var/www/deschide_news_app/articles-export-*.csv';

    private const CATEGORY_SLUG_TRANSLATIONS_JSON = __DIR__ . '/../../../fixtures/data/category-slug-translations.json';

    /** Per-category article caps for CSV article selection. */
    private const ARTICLE_CAPS = [
        'politica' => 400, 'societate' => 400, 'externe' => 400, 'economie' => 400,
        'romania' => 999, 'cultura' => 999, 'sport' => 999, 'editoriale' => 999,
        'opinii' => 999, 'advertorial' => 999, 'anti-fake' => 999,
    ];

    /** @var list<array{step: string, status: string, time: float}> */
    private array $report = [];

    public function __construct(
        private readonly string $appEnv,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('skip-csv', null, InputOption::VALUE_NONE, 'Skip CSV import + article selection')
            ->addOption('skip-translations', null, InputOption::VALUE_NONE, 'Skip EN translation generation')
            ->addOption('skip-images', null, InputOption::VALUE_NONE, 'Skip image download')
            ->addOption('skip-topics', null, InputOption::VALUE_NONE, 'Skip topics + tags loading')
            ->addOption('skip-elasticsearch', null, InputOption::VALUE_NONE, 'Skip Elasticsearch reindex')
            ->addOption('en-top-per-category', null, InputOption::VALUE_REQUIRED, 'Top N articles per category for EN translation', '10')
            ->addOption('csv-path', null, InputOption::VALUE_REQUIRED, 'Path to CSV file (auto-detected if not set)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Dev Reset — Full Fixtures Pipeline');
        $startTime = microtime(true);

        if ($this->appEnv !== 'dev' && $this->appEnv !== 'test') {
            $io->error("Această comandă rulează DOAR în dev/test. Mediul curent: {$this->appEnv}");

            return Command::FAILURE;
        }

        if ($input->isInteractive()) {
            if (!$io->confirm('Ești sigur? Aceasta va ȘTERGE toate datele din DB.', false)) {
                $io->warning('Operațiune anulată.');

                return Command::SUCCESS;
            }
        }

        $skipCsv = $input->getOption('skip-csv');
        $skipTranslations = $input->getOption('skip-translations');
        $skipImages = $input->getOption('skip-images');
        $skipTopics = $input->getOption('skip-topics');
        $skipEs = $input->getOption('skip-elasticsearch');

        // --- Step 1: Drop schema ---
        $this->runStep($io, '1. Drop database schema', fn () => $this->runSubCommand(
            $output, 'doctrine:schema:drop',
            ['--force' => true, '--full-database' => true],
        ));
        $this->em->clear();

        // --- Step 2: Migrations ---
        $this->runStep($io, '2. Run migrations', fn () => $this->runSubCommand(
            $output, 'doctrine:migrations:migrate',
        ));

        // --- Step 3: All fixtures in ONE pass (avoids --append dependency conflicts) ---
        $this->em->clear();
        $fixtureGroups = ['categories', 'menu', 'user', 'app-settings'];
        if (!$skipTopics) {
            $fixtureGroups[] = 'topics';
        }

        $this->runStep($io, '3. Load fixtures (' . implode(', ', $fixtureGroups) . ')', fn () => $this->runSubCommand(
            $output, 'doctrine:fixtures:load',
            ['--group' => $fixtureGroups],
        ));
        $this->em->clear();

        // --- Step 3b: Belt-and-braces — re-apply category slug translations from JSON
        // (idempotent; CategoryFixtures already applies on first load, but this guarantees
        // convergence if JSON is amended without re-running fixtures).
        if (is_file(self::CATEGORY_SLUG_TRANSLATIONS_JSON)) {
            $this->runStep($io, '3b. Apply category slug translations', fn () => $this->runSubCommand(
                $output, 'app:category:apply-slug-translations',
                ['--input' => self::CATEGORY_SLUG_TRANSLATIONS_JSON],
            ));
            $this->em->clear();
        }

        // --- Step 4: CSV import phases 1-3 ---
        if (!$skipCsv) {
            $csvPath = $input->getOption('csv-path') ?? $this->findCsvPath();

            if ($csvPath === null) {
                $io->warning('No CSV file found — skipping CSV import');
                $this->report[] = ['step' => '4. CSV import', 'status' => 'SKIPPED (no file)', 'time' => 0];
            } else {
                foreach (['articles', 'translations', 'links'] as $phase) {
                    $this->runStep($io, "4. CSV import: {$phase}", fn () => $this->runSubCommand(
                        $output, 'app:import:csv-legacy-articles',
                        ['path' => $csvPath, '--phase' => $phase, '--skip-images' => true],
                    ));
                }
            }
        } else {
            $this->report[] = ['step' => '4. CSV import', 'status' => 'SKIPPED', 'time' => 0];
        }

        // --- Step 5: Article selection (DBAL direct, not fixture) ---
        if (!$skipCsv) {
            $this->runStep($io, '5. Article selection (cull per caps)', function () use ($io) {
                return $this->cullArticles($io);
            });
        } else {
            $this->report[] = ['step' => '5. Article selection', 'status' => 'SKIPPED', 'time' => 0];
        }

        // --- Step 6: EN translations ---
        if (!$skipTranslations && !$skipCsv) {
            $topN = $input->getOption('en-top-per-category');
            $this->runStep($io, "6. Generate EN translations (top {$topN}/cat)", fn () => $this->runSubCommand(
                $output, 'app:fixtures:generate-translations',
                ['--top-per-category' => $topN, '--target-locale' => 'en', '--resume' => true],
            ));
        } else {
            $this->report[] = ['step' => '6. EN translations', 'status' => 'SKIPPED', 'time' => 0];
        }

        // --- Step 7: Images ---
        if (!$skipImages && !$skipCsv) {
            $this->runStep($io, '7. Download images + placeholders', fn () => $this->runSubCommand(
                $output, 'app:fixtures:download-images',
                ['--top-per-category' => '5'],
            ));
        } else {
            $this->report[] = ['step' => '7. Image download', 'status' => 'SKIPPED', 'time' => 0];
        }

        // --- Step 8: Redis cache ---
        $this->runStep($io, '8. Clear cache pools', fn () => $this->runSubCommand(
            $output, 'cache:pool:clear',
            ['pools' => ['cache.global_clearer']],
        ));

        // --- Step 9: Elasticsearch ---
        if (!$skipEs) {
            $this->runStep($io, '9. Create ES index', fn () => $this->runSubCommand(
                $output, 'app:elasticsearch:create-index',
            ));
            $this->runStep($io, '9. Index articles in ES', fn () => $this->runSubCommand(
                $output, 'app:elasticsearch:index-articles',
            ));
        } else {
            $this->report[] = ['step' => '9. Elasticsearch', 'status' => 'SKIPPED', 'time' => 0];
        }

        // --- Step 10: Symfony cache ---
        $this->runStep($io, '10. Clear Symfony cache', fn () => $this->runSubCommand(
            $output, 'cache:clear',
        ));

        // --- Summary ---
        $totalTime = microtime(true) - $startTime;
        $io->section('Raport final');
        $io->table(
            ['Pas', 'Status', 'Durată'],
            array_map(fn (array $r) => [
                $r['step'],
                $r['status'],
                $r['time'] > 0 ? sprintf('%.1fs', $r['time']) : '-',
            ], $this->report),
        );

        $this->printCounts($io);
        $io->text(sprintf('Total: %.0f sec (%.1f min)', $totalTime, $totalTime / 60));

        $hasFailures = \count(array_filter($this->report, fn (array $r) => $r['status'] === 'FAILED')) > 0;

        if ($hasFailures) {
            $io->warning('Reset completat cu erori. Verificați raportul de mai sus.');

            return Command::FAILURE;
        }

        $io->success('Reset complet finalizat cu succes.');

        return Command::SUCCESS;
    }

    /**
     * Cull CSV-imported articles per category caps (DBAL, avoids fixture dependency issues).
     */
    private function cullArticles(SymfonyStyle $io): int
    {
        $conn = $this->em->getConnection();
        $totalBefore = (int) $conn->fetchOne('SELECT COUNT(*) FROM articles');

        if ($totalBefore === 0) {
            $io->text('  No articles to cull');

            return Command::SUCCESS;
        }

        $totalDeleted = 0;

        foreach (self::ARTICLE_CAPS as $slug => $cap) {
            $categoryId = $conn->fetchOne('SELECT id FROM categories WHERE slug = ?', [$slug]);
            if ($categoryId === false) {
                continue;
            }

            $count = (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM articles a
                 JOIN external_article_mapping eam ON eam.article_id = a.id
                 WHERE eam.source = 'csv_legacy_deschide' AND a.category_id = ?",
                [$categoryId],
            );

            if ($count <= $cap) {
                continue;
            }

            $idsToDelete = $conn->fetchFirstColumn(
                "WITH ranked AS (
                    SELECT a.id, ROW_NUMBER() OVER (ORDER BY a.published_at DESC NULLS LAST, a.id DESC) AS rn
                    FROM articles a
                    JOIN external_article_mapping eam ON eam.article_id = a.id
                    WHERE eam.source = 'csv_legacy_deschide' AND a.category_id = ?
                )
                SELECT id FROM ranked WHERE rn > ?",
                [$categoryId, $cap],
            );

            if (\count($idsToDelete) === 0) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, \count($idsToDelete), '?'));
            $conn->executeStatement(
                "DELETE FROM ext_translations WHERE object_class = 'App\\Entity\\Article' AND foreign_key::int IN ({$placeholders})",
                $idsToDelete,
            );
            $deleted = $conn->executeStatement("DELETE FROM articles WHERE id IN ({$placeholders})", $idsToDelete);
            $totalDeleted += $deleted;
        }

        $io->text("  Culled: {$totalBefore} → " . ($totalBefore - $totalDeleted) . " ({$totalDeleted} removed)");

        return Command::SUCCESS;
    }

    private function runStep(SymfonyStyle $io, string $stepName, callable $action): void
    {
        $io->write("  {$stepName}... ");
        $stepStart = microtime(true);

        try {
            $exitCode = $action();
            $elapsed = microtime(true) - $stepStart;

            if ($exitCode === Command::SUCCESS) {
                $this->report[] = ['step' => $stepName, 'status' => 'OK', 'time' => $elapsed];
                $io->writeln(sprintf('<info>OK</info> (%.1fs)', $elapsed));
            } else {
                $this->report[] = ['step' => $stepName, 'status' => 'FAILED', 'time' => $elapsed];
                $io->writeln(sprintf('<error>FAILED</error> (%.1fs)', $elapsed));
            }
        } catch (\Throwable $e) {
            $elapsed = microtime(true) - $stepStart;
            $this->report[] = ['step' => $stepName, 'status' => 'FAILED', 'time' => $elapsed];
            $io->writeln(sprintf('<error>FAILED: %s</error>', $e->getMessage()));
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function runSubCommand(OutputInterface $output, string $commandName, array $arguments = []): int
    {
        $command = $this->getApplication()?->find($commandName);
        if ($command === null) {
            throw new \RuntimeException("Command not found: {$commandName}");
        }

        $input = new ArrayInput($arguments);
        $input->setInteractive(false);

        return $command->run($input, $output);
    }

    private function findCsvPath(): ?string
    {
        $files = glob(self::DEFAULT_CSV_GLOB);
        if ($files === false || $files === []) {
            return null;
        }
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    private function printCounts(SymfonyStyle $io): void
    {
        try {
            $conn = $this->em->getConnection();
            $counts = [
                'categories' => (int) $conn->fetchOne('SELECT COUNT(*) FROM categories'),
                'topics (total)' => (int) $conn->fetchOne('SELECT COUNT(*) FROM topics'),
                'topics (leaf)' => (int) $conn->fetchOne('SELECT COUNT(*) FROM topics WHERE lvl = 2'),
                'tags' => (int) $conn->fetchOne('SELECT COUNT(*) FROM tags'),
                'menu_items' => (int) $conn->fetchOne('SELECT COUNT(*) FROM menu_items'),
                'articles' => (int) $conn->fetchOne('SELECT COUNT(*) FROM articles'),
            ];

            $io->section('Entity Counts');
            $io->table(
                ['Entity', 'Count'],
                array_map(fn (string $k, int $v) => [$k, (string) $v], array_keys($counts), array_values($counts)),
            );
        } catch (\Throwable) {
            $io->warning('Could not fetch entity counts');
        }
    }
}
