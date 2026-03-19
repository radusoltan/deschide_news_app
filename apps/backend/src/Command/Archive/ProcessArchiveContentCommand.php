<?php

declare(strict_types=1);

namespace App\Command\Archive;

use App\Service\Archive\ContentProcessor;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command pentru procesarea conținutului de arhivă din bazele de date legacy.
 *
 * Transformă shortcodes proprietare Newscoop/Beta în HTML modern:
 * - <!** Image X> → <figure><img></figure>
 * - <!** Link Internal ...> → <a href="...">
 * - <!** Title> → <h3>
 *
 * Suportă:
 * - Procesare batch cu limit/offset pentru articole mari
 * - Dry-run pentru preview fără modificări
 * - Export JSON pentru stagiere
 * - Statistici detaliate pe fiecare sursă
 */
#[AsCommand(
    name: 'app:archive:process-content',
    description: 'Process legacy archive content (shortcodes → modern HTML)'
)]
class ProcessArchiveContentCommand extends Command
{
    // Date despre articole cu shortcodes (din analiză)
    private const NEWSCOOP_ARTICLES_WITH_IMAGES = 16182;
    private const BETA_ARTICLES_WITH_IMAGES = 4046;
    private const TOTAL_ARTICLES_WITH_SHORTCODES = 20228;

    // Batch size pentru procesare
    private const DEFAULT_BATCH_SIZE = 100;

    // Statistici globale
    private array $globalStats = [
        'total_articles' => 0,
        'articles_with_shortcodes' => 0,
        'articles_processed' => 0,
        'images_processed' => 0,
        'images_not_found' => 0,
        'links_processed' => 0,
        'links_broken' => 0,
        'titles_converted' => 0,
        'snippets_removed' => 0,
        'errors' => 0,
    ];

    public function __construct(
        private readonly ContentProcessor $contentProcessor,
        private readonly Connection $newscoopConnection,
        private readonly Connection $betaDeschideConnection,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'source',
                's',
                InputOption::VALUE_OPTIONAL,
                'Source database: newscoop, beta, or all',
                'all'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Limit number of articles to process',
                null
            )
            ->addOption(
                'offset',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Offset for resuming batch processing',
                0
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Preview mode - no saving, just display statistics'
            )
            ->addOption(
                'save-processed',
                null,
                InputOption::VALUE_OPTIONAL,
                'Save processed content to JSON files (directory path)',
                null
            )
            ->addOption(
                'language',
                null,
                InputOption::VALUE_OPTIONAL,
                'Language ID to process (default: 2 for Romanian)',
                2
            )
            ->addOption(
                'show-samples',
                null,
                InputOption::VALUE_NONE,
                'Show sample transformations (before/after)'
            )
            ->addOption(
                'only-with-shortcodes',
                null,
                InputOption::VALUE_NONE,
                'Process only articles that contain shortcodes'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Parse options
        $source = strtolower((string) $input->getOption('source'));
        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $offset = (int) $input->getOption('offset');
        $dryRun = $input->getOption('dry-run');
        $saveDir = $input->getOption('save-processed');
        $languageId = (int) $input->getOption('language');
        $showSamples = $input->getOption('show-samples');
        $onlyWithShortcodes = $input->getOption('only-with-shortcodes');

        // Validate source
        if (!\in_array($source, ['newscoop', 'beta', 'all'], true)) {
            $io->error("Invalid source: $source. Must be one of: newscoop, beta, all");

            return Command::FAILURE;
        }

        // Validate save directory
        if ($saveDir && !is_dir($saveDir) && !mkdir($saveDir, 0755, true) && !is_dir($saveDir)) {
            $io->error("Cannot create directory: $saveDir");

            return Command::FAILURE;
        }

        // Display header
        $io->title('🔄 Archive Content Processor');
        $io->text('Transform legacy Newscoop/Beta shortcodes to modern HTML');

        // Display configuration
        $this->displayConfiguration($io, $source, $limit, $offset, $dryRun, $saveDir, $languageId, $onlyWithShortcodes);

        if ($dryRun) {
            $io->warning('🔍 DRY RUN MODE - No data will be saved');
        }

        // Confirm execution
        if (!$io->confirm('Continue with processing?', true)) {
            $io->note('Operation cancelled');

            return Command::SUCCESS;
        }

        // Process sources
        $sources = $source === 'all' ? ['newscoop', 'beta'] : [$source];
        $totalProcessed = 0;

        foreach ($sources as $currentSource) {
            $result = $this->processSource(
                $io,
                $currentSource,
                $limit,
                $offset,
                $languageId,
                $dryRun,
                $saveDir,
                $showSamples,
                $onlyWithShortcodes
            );

            if ($result === Command::FAILURE) {
                return Command::FAILURE;
            }

            $totalProcessed += $result;
        }

        // Display final statistics
        $this->displayFinalStatistics($io, $dryRun);

        // Display next steps
        if (!$dryRun && $saveDir) {
            $io->success("✅ Processed content saved to: $saveDir");
            $io->note([
                'Next steps:',
                '1. Review the JSON files in the output directory',
                '2. Import processed content to new database',
                '3. Run without --dry-run to save to database directly (when ready)',
            ]);
        }

        if ($dryRun) {
            $io->note('Run without --dry-run to actually save the processed content');
        }

        return Command::SUCCESS;
    }

    /**
     * Procesează articole dintr-o sursă specifică.
     */
    private function processSource(
        SymfonyStyle $io,
        string $source,
        ?int $limit,
        int $offset,
        int $languageId,
        bool $dryRun,
        ?string $saveDir,
        bool $showSamples,
        bool $onlyWithShortcodes
    ): int {
        $io->section(\sprintf('📂 Processing: %s', strtoupper($source)));

        $connection = $source === ContentProcessor::SOURCE_NEWSCOOP
            ? $this->newscoopConnection
            : $this->betaDeschideConnection;

        // Fetch articles
        try {
            $articles = $this->fetchArticles($connection, $source, $limit, $offset, $languageId, $onlyWithShortcodes);
            $articlesCount = \count($articles);

            if ($articlesCount === 0) {
                $io->warning("No articles found in $source");

                return 0;
            }

            $io->writeln(\sprintf('Found <info>%d</info> articles', $articlesCount));
        } catch (\Exception $e) {
            $io->error(\sprintf('Failed to fetch articles: %s', $e->getMessage()));
            $this->logger->error('Article fetch failed', [
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }

        // Create progress bar
        $progressBar = new ProgressBar($io, $articlesCount);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        $processedArticles = [];
        $samplesShown = 0;
        $maxSamples = 3;

        // Process each article
        foreach ($articles as $article) {
            try {
                $result = $this->processArticle(
                    $article,
                    $source,
                    $languageId,
                    $showSamples && $samplesShown < $maxSamples
                );

                if ($result) {
                    $processedArticles[] = $result;

                    // Show sample if requested
                    if ($showSamples && $samplesShown < $maxSamples && $result['has_changes']) {
                        $this->displaySample($io, $result, $source);
                        ++$samplesShown;
                    }
                }

                // Update statistics
                $this->updateGlobalStats($this->contentProcessor->getStats());
            } catch (\Exception $e) {
                ++$this->globalStats['errors'];
                $this->logger->error('Article processing failed', [
                    'article_number' => $article['Number'],
                    'source' => $source,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        // Save processed content if requested
        if (!$dryRun && $saveDir) {
            $this->saveProcessedContent($io, $source, $processedArticles, $saveDir);
        }

        // Display source statistics
        $this->displaySourceStatistics($io, $source);

        return $articlesCount;
    }

    /**
     * Fetch articles din baza de date legacy.
     */
    private function fetchArticles(
        Connection $connection,
        string $source,
        ?int $limit,
        int $offset,
        int $languageId,
        bool $onlyWithShortcodes
    ): array {
        // Query diferit pentru Newscoop vs Beta
        if ($source === ContentProcessor::SOURCE_NEWSCOOP) {
            $sql = '
                SELECT
                    a.Number,
                    a.Name as title,
                    a.IdLanguage,
                    a.Published,
                    a.PublishDate,
                    x.FTitlu as full_title,
                    x.Fsubtitlu as subtitle,
                    x.Flead as lead,
                    x.FContinut as content
                FROM Articles a
                INNER JOIN Xstiri x ON a.Number = x.NrArticle AND a.IdLanguage = x.IdLanguage
                WHERE a.IdLanguage = :languageId
                    AND a.Published = \'Y\'
                    AND x.FContinut IS NOT NULL
            ';

            if ($onlyWithShortcodes) {
                $sql .= ' AND (x.FContinut LIKE :imagePattern
                              OR x.FContinut LIKE :linkPattern
                              OR x.FContinut LIKE :titlePattern)';
            }

            $sql .= ' ORDER BY a.Number ASC';

            if ($limit) {
                $sql .= \sprintf(' LIMIT %d OFFSET %d', $limit, $offset);
            }

            $params = ['languageId' => $languageId];
            if ($onlyWithShortcodes) {
                $params['imagePattern'] = '%<!** Image %';
                $params['linkPattern'] = '%<!** Link Internal%';
                $params['titlePattern'] = '%<!** Title%';
            }
        } else {
            // Beta database query (structură similară dar posibil cu alte câmpuri)
            $sql = '
                SELECT
                    a.Number,
                    a.Name as title,
                    a.IdLanguage,
                    a.Published,
                    a.PublishDate,
                    x.FTitlu as full_title,
                    x.Fsubtitlu as subtitle,
                    x.Flead as lead,
                    x.FContinut as content
                FROM Articles a
                INNER JOIN Xstiri x ON a.Number = x.NrArticle AND a.IdLanguage = x.IdLanguage
                WHERE a.IdLanguage = :languageId
                    AND a.Published = \'Y\'
                    AND x.FContinut IS NOT NULL
            ';

            if ($onlyWithShortcodes) {
                $sql .= ' AND (x.FContinut LIKE :imagePattern
                              OR x.FContinut LIKE :linkPattern
                              OR x.FContinut LIKE :titlePattern)';
            }

            $sql .= ' ORDER BY a.Number ASC';

            if ($limit) {
                $sql .= \sprintf(' LIMIT %d OFFSET %d', $limit, $offset);
            }

            $params = ['languageId' => $languageId];
            if ($onlyWithShortcodes) {
                $params['imagePattern'] = '%<!** Image %';
                $params['linkPattern'] = '%<!** Link Internal%';
                $params['titlePattern'] = '%<!** Title%';
            }
        }

        return $connection->fetchAllAssociative($sql, $params);
    }

    /**
     * Procesează un singur articol.
     */
    private function processArticle(
        array $article,
        string $source,
        int $languageId,
        bool $captureDetails
    ): ?array {
        ++$this->globalStats['total_articles'];

        $originalContent = $article['content'] ?? '';

        if (empty($originalContent)) {
            return null;
        }

        // Check for shortcodes
        $hasShortcodes = $this->contentProcessor->hasShortcodes($originalContent);

        if ($hasShortcodes) {
            ++$this->globalStats['articles_with_shortcodes'];
        }

        // Count shortcodes
        $shortcodeCounts = $this->contentProcessor->countShortcodes($originalContent);

        // Process content
        $processedContent = $this->contentProcessor->processContent(
            $originalContent,
            (int) $article['Number'],
            $source,
            $languageId
        );

        ++$this->globalStats['articles_processed'];

        $result = [
            'article_number' => $article['Number'],
            'title' => $article['title'] ?? 'Untitled',
            'language_id' => $article['IdLanguage'],
            'published_date' => $article['PublishDate'] ?? null,
            'has_shortcodes' => $hasShortcodes,
            'shortcode_counts' => $shortcodeCounts,
            'has_changes' => $originalContent !== $processedContent,
            'original_length' => \strlen($originalContent),
            'processed_length' => \strlen($processedContent),
            'source' => $source,
        ];

        if ($captureDetails) {
            $result['original_content'] = $originalContent;
            $result['processed_content'] = $processedContent;
        }

        return $result;
    }

    /**
     * Salvează conținutul procesat în fișiere JSON.
     */
    private function saveProcessedContent(
        SymfonyStyle $io,
        string $source,
        array $processedArticles,
        string $saveDir
    ): void {
        if (empty($processedArticles)) {
            return;
        }

        $timestamp = date('Y-m-d_H-i-s');
        $filename = \sprintf('%s/processed_%s_%s.json', $saveDir, $source, $timestamp);

        $data = [
            'metadata' => [
                'source' => $source,
                'processed_at' => date('Y-m-d H:i:s'),
                'total_articles' => \count($processedArticles),
                'articles_with_changes' => array_reduce(
                    $processedArticles,
                    fn ($carry, $item) => $carry + ($item['has_changes'] ? 1 : 0),
                    0
                ),
            ],
            'articles' => $processedArticles,
        ];

        try {
            file_put_contents(
                $filename,
                json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)
            );
            $io->writeln(\sprintf('💾 Saved to: <info>%s</info>', $filename));
        } catch (\Exception $e) {
            $io->error(\sprintf('Failed to save JSON: %s', $e->getMessage()));
        }
    }

    /**
     * Afișează configurația comenzii.
     */
    private function displayConfiguration(
        SymfonyStyle $io,
        string $source,
        ?int $limit,
        int $offset,
        bool $dryRun,
        ?string $saveDir,
        int $languageId,
        bool $onlyWithShortcodes
    ): void {
        $io->section('⚙️  Configuration');

        $config = [
            'Source' => strtoupper($source),
            'Language ID' => $languageId === 2 ? '2 (Romanian)' : $languageId,
            'Limit' => $limit ?? 'ALL',
            'Offset' => $offset,
            'Mode' => $dryRun ? '🔍 DRY RUN (preview only)' : '💾 LIVE (will process)',
            'Filter' => $onlyWithShortcodes ? '✅ Only articles with shortcodes' : 'All articles',
        ];

        if ($saveDir) {
            $config['Save To'] = $saveDir;
        }

        $io->definitionList(...array_map(
            fn ($key, $value) => [$key => $value],
            array_keys($config),
            array_values($config)
        ));

        // Display expected counts
        if ($source === 'all' || $source === 'newscoop') {
            $io->text(\sprintf('📊 Newscoop: ~%s articles with image shortcodes', number_format(self::NEWSCOOP_ARTICLES_WITH_IMAGES)));
        }
        if ($source === 'all' || $source === 'beta') {
            $io->text(\sprintf('📊 Beta: ~%s articles with image shortcodes', number_format(self::BETA_ARTICLES_WITH_IMAGES)));
        }
        if ($source === 'all') {
            $io->text(\sprintf('📊 Total: ~%s articles with shortcodes', number_format(self::TOTAL_ARTICLES_WITH_SHORTCODES)));
        }

        $io->newLine();
    }

    /**
     * Afișează un exemplu de transformare (before/after).
     */
    private function displaySample(SymfonyStyle $io, array $result, string $source): void {
        $io->newLine();
        $io->section(\sprintf('📄 Sample: Article #%d (%s)', $result['article_number'], strtoupper($source)));

        $io->writeln(\sprintf('<comment>Title:</comment> %s', $result['title']));
        $io->writeln(\sprintf('<comment>Shortcodes:</comment> Images=%d, Links=%d, Titles=%d',
            $result['shortcode_counts']['images'],
            $result['shortcode_counts']['internal_links'],
            $result['shortcode_counts']['titles']
        ));

        $io->newLine();
        $io->writeln('<comment>BEFORE (excerpt):</comment>');
        $io->writeln($this->truncateText($result['original_content'] ?? '', 500));

        $io->newLine();
        $io->writeln('<comment>AFTER (excerpt):</comment>');
        $io->writeln($this->truncateText($result['processed_content'] ?? '', 500));

        $io->newLine();
    }

    /**
     * Afișează statistici pentru o sursă.
     */
    private function displaySourceStatistics(SymfonyStyle $io, string $source): void {
        $stats = $this->contentProcessor->getStats();

        $io->section(\sprintf('📊 Statistics: %s', strtoupper($source)));

        $table = new Table($io);
        $table->setHeaders(['Metric', 'Count']);
        $table->setRows([
            ['Images Processed', $stats['images_processed']],
            ['Images Not Found', $stats['images_not_found']],
            ['Links Processed', $stats['links_processed']],
            ['Links Broken', $stats['links_broken']],
            ['Titles Converted', $stats['titles_converted']],
            ['Snippets Removed', $stats['snippets_removed']],
        ]);
        $table->render();

        $io->newLine();
    }

    /**
     * Afișează statistici finale globale.
     */
    private function displayFinalStatistics(SymfonyStyle $io, bool $dryRun): void {
        $io->section('📈 Final Statistics');

        $table = new Table($io);
        $table->setHeaders(['Metric', 'Count']);
        $table->setRows([
            ['Total Articles Fetched', number_format($this->globalStats['total_articles'])],
            ['Articles With Shortcodes', number_format($this->globalStats['articles_with_shortcodes'])],
            ['Articles Processed', number_format($this->globalStats['articles_processed'])],
            ['Images Processed', number_format($this->globalStats['images_processed'])],
            ['Images Not Found', number_format($this->globalStats['images_not_found'])],
            ['Internal Links Processed', number_format($this->globalStats['links_processed'])],
            ['Broken Links', number_format($this->globalStats['links_broken'])],
            ['Titles Converted', number_format($this->globalStats['titles_converted'])],
            ['Snippets Removed', number_format($this->globalStats['snippets_removed'])],
            ['Errors', number_format($this->globalStats['errors'])],
        ]);
        $table->render();

        // Calculate success rate
        if ($this->globalStats['images_processed'] > 0) {
            $imageSuccessRate = ($this->globalStats['images_processed'] /
                ($this->globalStats['images_processed'] + $this->globalStats['images_not_found'])) * 100;
            $io->writeln(\sprintf('✅ Image success rate: <info>%.2f%%</info>', $imageSuccessRate));
        }

        if ($this->globalStats['links_processed'] > 0) {
            $linkSuccessRate = ($this->globalStats['links_processed'] /
                ($this->globalStats['links_processed'] + $this->globalStats['links_broken'])) * 100;
            $io->writeln(\sprintf('✅ Link success rate: <info>%.2f%%</info>', $linkSuccessRate));
        }

        $io->newLine();

        if ($dryRun) {
            $io->note('This was a dry run - no data was modified');
        }
    }

    /**
     * Update statisticile globale cu datele curente.
     */
    private function updateGlobalStats(array $stats): void
    {
        $this->globalStats['images_processed'] += $stats['images_processed'];
        $this->globalStats['images_not_found'] += $stats['images_not_found'];
        $this->globalStats['links_processed'] += $stats['links_processed'];
        $this->globalStats['links_broken'] += $stats['links_broken'];
        $this->globalStats['titles_converted'] += $stats['titles_converted'];
        $this->globalStats['snippets_removed'] += $stats['snippets_removed'];
    }

    /**
     * Truncate text pentru preview.
     */
    private function truncateText(string $text, int $maxLength): string
    {
        if (\strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength) . '... [truncated]';
    }
}
