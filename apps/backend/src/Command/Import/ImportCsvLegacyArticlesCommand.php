<?php

declare(strict_types=1);

namespace App\Command\Import;

use App\Service\Import\CsvLegacyParser;
use App\Service\Import\LegacyArticleImporter;
use App\Service\Import\LegacyAuthorMapper;
use App\Service\Import\LegacyCategoryMapper;
use App\Service\Import\LegacyImageDownloader;
use App\Service\Import\LegacyLinkReplacer;
use App\Service\Import\LegacyTranslationImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'app:import:csv-legacy-articles',
    description: 'Import articles from legacy deschide.md CSV export (14K articles, RO + RU translations)',
)]
class ImportCsvLegacyArticlesCommand extends Command
{
    private const CHECKPOINT_FILE = 'var/import-csv-checkpoint.json';
    private const MAPPING_FILE = 'var/import-csv-mapping.json';
    private const ORPHAN_LINKS_FILE = 'var/import-orphan-links.json';
    private const ERRORS_FILE = 'var/import-csv-errors.log';
    private const UNKNOWN_AUTHORS_FILE = 'var/import-csv-author-unknowns.log';

    public function __construct(
        private readonly CsvLegacyParser $parser,
        private readonly LegacyArticleImporter $articleImporter,
        private readonly LegacyTranslationImporter $translationImporter,
        private readonly LegacyLinkReplacer $linkReplacer,
        private readonly LegacyImageDownloader $imageDownloader,
        private readonly LegacyCategoryMapper $categoryMapper,
        private readonly LegacyAuthorMapper $authorMapper,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path to the CSV file')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate import without writing to DB')
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Flush every N articles', 50)
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Skip first N rows', 0)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit to N articles (0 = all)', 0)
            ->addOption('phase', null, InputOption::VALUE_OPTIONAL, 'Run specific phase: all|articles|translations|links|images', 'all')
            ->addOption('skip-images', null, InputOption::VALUE_NONE, 'Skip image download phase')
            ->addOption('skip-translations', null, InputOption::VALUE_NONE, 'Skip RU translation phase')
            ->addOption('skip-link-replace', null, InputOption::VALUE_NONE, 'Skip internal link replacement phase');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stopwatch = new Stopwatch();
        $stopwatch->start('import');

        $csvPath = $input->getArgument('path');
        $dryRun = (bool) $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');
        $offset = (int) $input->getOption('offset');
        $limit = (int) $input->getOption('limit');
        $phase = $input->getOption('phase');
        $skipImages = (bool) $input->getOption('skip-images');
        $skipTranslations = (bool) $input->getOption('skip-translations');
        $skipLinkReplace = (bool) $input->getOption('skip-link-replace');

        // Validate CSV file
        if (!file_exists($csvPath)) {
            $io->error(sprintf('CSV file not found: %s', $csvPath));

            return Command::FAILURE;
        }

        $io->title('Import Legacy deschide.md Articles from CSV');
        $this->showConfig($io, $csvPath, $dryRun, $batchSize, $offset, $limit, $phase, $skipImages, $skipTranslations, $skipLinkReplace);

        if ($dryRun) {
            $io->warning('DRY RUN MODE — no database changes will be made');
        }

        // Phase routing
        $runArticles = \in_array($phase, ['all', 'articles'], true);
        $runTranslations = \in_array($phase, ['all', 'translations'], true) && !$skipTranslations;
        $runLinks = \in_array($phase, ['all', 'links'], true) && !$skipLinkReplace;
        $runImages = \in_array($phase, ['all', 'images'], true) && !$skipImages;

        // Phase 0: Ensure categories exist
        if ($runArticles) {
            $io->section('Phase 0: Ensuring categories exist');
            $this->categoryMapper->ensureAllCategoriesExist();
            $io->success('Categories ready');
        }

        // Phase 1: Import articles (RO)
        if ($runArticles) {
            $this->runPhaseArticles($io, $csvPath, $offset, $limit, $batchSize, $dryRun);
        }

        // Phase 2: Import translations (RU)
        if ($runTranslations) {
            $this->runPhaseTranslations($io, $csvPath, $offset, $limit, $batchSize, $dryRun);
        }

        // Phase 3: Link replacement
        if ($runLinks) {
            $this->runPhaseLinkReplace($io, $dryRun);
        }

        // Phase 4: Image download
        if ($runImages) {
            $this->runPhaseImages($io, $dryRun, $batchSize);
        }

        // Final report
        $event = $stopwatch->stop('import');
        $this->showFinalReport($io, $event);

        // Save output files
        if (!$dryRun) {
            $this->saveOutputFiles($io);
        }

        return Command::SUCCESS;
    }

    private function runPhaseArticles(SymfonyStyle $io, string $csvPath, int $offset, int $limit, int $batchSize, bool $dryRun): void
    {
        $io->section('Phase 1: Import Articles (RO)');

        $totalRecords = $this->parser->countRecords($csvPath);
        $io->writeln(sprintf('Total records in CSV: %d', $totalRecords));

        $effectiveLimit = $limit > 0 ? min($limit, $totalRecords - $offset) : $totalRecords - $offset;
        $io->progressStart($effectiveLimit);

        $batchCount = 0;

        foreach ($this->parser->parse($csvPath, $offset, $limit) as $rowIndex => $data) {
            $this->articleImporter->importRow($data, $dryRun);

            ++$batchCount;

            if ($batchCount % $batchSize === 0 && !$dryRun) {
                $this->articleImporter->flush();
                $this->saveCheckpoint('articles', $rowIndex, $this->articleImporter->getStats());
            }

            $io->progressAdvance();
        }

        // Final flush
        if (!$dryRun) {
            $this->articleImporter->flush();
        }

        $io->progressFinish();

        $stats = $this->articleImporter->getStats();
        $io->table(
            ['Metric', 'Count'],
            [
                ['Imported', $stats['imported']],
                ['Skipped (duplicates)', $stats['skipped']],
                ['Errors', $stats['errors']],
            ],
        );
    }

    private function runPhaseTranslations(SymfonyStyle $io, string $csvPath, int $offset, int $limit, int $batchSize, bool $dryRun): void
    {
        $io->section('Phase 2: Import Translations (RU)');

        $batchCount = 0;
        $totalWithRu = 0;

        foreach ($this->parser->parse($csvPath, $offset, $limit) as $data) {
            if (!$data['hasRuTranslation']) {
                continue;
            }

            ++$totalWithRu;
            $this->translationImporter->importTranslation($data, $dryRun);

            ++$batchCount;
            if ($batchCount % $batchSize === 0 && !$dryRun) {
                $this->translationImporter->flush();
            }
        }

        if (!$dryRun) {
            $this->translationImporter->flush();
        }

        $stats = $this->translationImporter->getStats();
        $io->table(
            ['Metric', 'Count'],
            [
                ['Articles with RU content', $totalWithRu],
                ['Translated', $stats['translated']],
                ['Skipped', $stats['skipped']],
                ['Errors', $stats['errors']],
            ],
        );
    }

    private function runPhaseLinkReplace(SymfonyStyle $io, bool $dryRun): void
    {
        $io->section('Phase 3: Internal Link Replacement');
        $io->writeln('Scanning imported articles for deschide.md links...');

        $this->linkReplacer->replaceAll($dryRun);

        $stats = $this->linkReplacer->getStats();
        $io->table(
            ['Metric', 'Count'],
            [
                ['Links replaced', $stats['replaced']],
                ['Orphan links', $stats['orphan']],
                ['Articles updated', $stats['articlesUpdated']],
            ],
        );

        if ($stats['orphan'] > 0) {
            $io->note(sprintf('%d orphan links logged to %s', $stats['orphan'], self::ORPHAN_LINKS_FILE));
        }
    }

    private function runPhaseImages(SymfonyStyle $io, bool $dryRun, int $batchSize): void
    {
        $io->section('Phase 4: Image Download');
        $io->writeln('Downloading images from Webflow/Supabase CDN...');
        $io->note('This may take several hours for 14K images. Use Ctrl+C to interrupt safely.');

        $this->imageDownloader->downloadAll($dryRun, $batchSize);

        $stats = $this->imageDownloader->getStats();
        $io->table(
            ['Metric', 'Count'],
            [
                ['Downloaded', $stats['downloaded']],
                ['Failed', $stats['failed']],
                ['Skipped (already exists)', $stats['skipped']],
            ],
        );
    }

    private function showConfig(SymfonyStyle $io, string $csvPath, bool $dryRun, int $batchSize, int $offset, int $limit, string $phase, bool $skipImages, bool $skipTranslations, bool $skipLinkReplace): void
    {
        $io->definitionList(
            ['CSV File' => $csvPath],
            ['Mode' => $dryRun ? 'DRY RUN' : 'LIVE'],
            ['Batch Size' => $batchSize],
            ['Offset' => $offset],
            ['Limit' => $limit > 0 ? $limit : 'ALL'],
            ['Phase' => $phase],
            ['Skip Images' => $skipImages ? 'YES' : 'NO'],
            ['Skip Translations' => $skipTranslations ? 'YES' : 'NO'],
            ['Skip Link Replace' => $skipLinkReplace ? 'YES' : 'NO'],
        );
    }

    private function showFinalReport(SymfonyStyle $io, \Symfony\Component\Stopwatch\StopwatchEvent $event): void
    {
        $duration = $event->getDuration();
        $memory = $event->getMemory();

        $hours = floor($duration / 3_600_000);
        $minutes = floor(($duration % 3_600_000) / 60_000);
        $seconds = floor(($duration % 60_000) / 1000);

        $articleStats = $this->articleImporter->getStats();
        $translationStats = $this->translationImporter->getStats();
        $linkStats = $this->linkReplacer->getStats();
        $imageStats = $this->imageDownloader->getStats();

        $io->newLine(2);
        $io->writeln('<fg=green>═══════════════════════════════════════</>');
        $io->writeln('<fg=green>  Import Complete</>');
        $io->writeln('<fg=green>═══════════════════════════════════════</>');
        $io->writeln(sprintf('  Articles imported:     %s', number_format($articleStats['imported'])));
        $io->writeln(sprintf('  Articles skipped:      %s (duplicates)', number_format($articleStats['skipped'])));
        $io->writeln(sprintf('  Translations (RU):     %s', number_format($translationStats['translated'])));
        $io->writeln(sprintf('  Links replaced:        %s', number_format($linkStats['replaced'])));
        $io->writeln(sprintf('  Orphan links:          %s', number_format($linkStats['orphan'])));
        $io->writeln(sprintf('  Images downloaded:     %s', number_format($imageStats['downloaded'])));
        $io->writeln(sprintf('  Images failed:         %s', number_format($imageStats['failed'])));
        $io->writeln(sprintf('  Errors:                %s', number_format($articleStats['errors'])));
        $io->writeln(sprintf('  Duration:              %dh %02dm %02ds', $hours, $minutes, $seconds));
        $io->writeln(sprintf('  Peak memory:           %s MB', number_format($memory / 1_048_576, 1)));
        $io->writeln('<fg=green>═══════════════════════════════════════</>');
    }

    private function saveCheckpoint(string $phase, int $lastOffset, array $stats): void
    {
        $path = $this->projectDir . '/' . self::CHECKPOINT_FILE;
        $data = [
            'phase' => $phase,
            'lastOffset' => $lastOffset,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'stats' => $stats,
        ];

        file_put_contents($path, json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    private function saveOutputFiles(SymfonyStyle $io): void
    {
        $baseDir = $this->projectDir . '/';

        // Orphan links
        $orphanLinks = $this->linkReplacer->getOrphanLinks();
        if (\count($orphanLinks) > 0) {
            file_put_contents(
                $baseDir . self::ORPHAN_LINKS_FILE,
                json_encode($orphanLinks, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
            );
            $io->note(sprintf('Orphan links saved to: %s', self::ORPHAN_LINKS_FILE));
        }

        // Error log
        $errors = $this->articleImporter->getErrorLog();
        if (\count($errors) > 0) {
            file_put_contents($baseDir . self::ERRORS_FILE, implode("\n", $errors) . "\n");
            $io->note(sprintf('Errors saved to: %s', self::ERRORS_FILE));
        }

        // Unknown authors
        $unknownAuthors = $this->authorMapper->getUnknownAuthors();
        if (\count($unknownAuthors) > 0) {
            file_put_contents($baseDir . self::UNKNOWN_AUTHORS_FILE, implode("\n", $unknownAuthors) . "\n");
            $io->note(sprintf('Unknown authors saved to: %s', self::UNKNOWN_AUTHORS_FILE));
        }

        // Final checkpoint
        $this->saveCheckpoint('complete', 0, [
            'articles' => $this->articleImporter->getStats(),
            'translations' => $this->translationImporter->getStats(),
            'links' => $this->linkReplacer->getStats(),
            'images' => $this->imageDownloader->getStats(),
        ]);
    }
}
