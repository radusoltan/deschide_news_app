<?php

declare(strict_types=1);

namespace App\Command\Import;

use App\Service\Import\ImportTokenService;
use App\Service\Import\MigrationLoggerService;
use App\Service\Import\NewscoopConnectionService;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import:translations',
    description: 'Import translations (ru, en) for already imported RO articles via API'
)]
class ImportTranslationsCommand extends Command
{
    private const API_BASE_URL = 'http://127.0.0.1:8081';

    private const LOCALE_MAP = [
        1 => 'en',
        15 => 'ru',
    ];

    public function __construct(
        private readonly NewscoopConnectionService $newscoopConnection,
        private readonly MigrationLoggerService $migrationLogger,
        private readonly ImportTokenService $importTokenService,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit number of articles to process', null)
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Offset for pagination', 0)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run mode (no API calls)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $offset = (int) $input->getOption('offset');
        $dryRun = $input->getOption('dry-run');

        $io->title('FAZA 5: Import Traduceri Articole (RU, EN)');

        $io->section('Configuration');
        $io->definitionList(
            ['Limit' => $limit ?? 'ALL'],
            ['Offset' => $offset],
            ['Mode' => $dryRun ? 'DRY RUN (no API calls)' : 'LIVE'],
        );

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No actual API calls will be made');
        }

        // Step 1: Generate JWT token
        $io->section('Step 1: Generate JWT Token');

        try {
            $jwtToken = $this->importTokenService->getImportToken();
            $io->success('JWT token generated');
        } catch (Exception $e) {
            $io->error('Failed to generate JWT token: ' . $e->getMessage());

            return Command::FAILURE;
        }

        // Step 2: Fetch already imported RO articles
        $io->section('Step 2: Fetch Imported RO Articles');

        try {
            $importedArticles = $this->migrationLogger->getMappings('article', 'success', $limit, $offset);
            $io->writeln(\sprintf('Found %d imported RO articles (offset: %d)', \count($importedArticles), $offset));

            if (\count($importedArticles) === 0) {
                $io->warning('No imported articles found!');

                return Command::SUCCESS;
            }
        } catch (Exception $e) {
            $io->error('Failed to fetch imported articles: ' . $e->getMessage());

            return Command::FAILURE;
        }

        // Step 3: Process translations
        $io->section('Step 3: Import Translations via API');

        $stats = [
            'total_articles' => \count($importedArticles),
            'articles_processed' => 0,
            'articles_with_translations' => 0,
            'articles_without_translations' => 0,
            'translations_success' => 0,
            'translations_error' => 0,
            'translations_ru' => 0,
            'translations_en' => 0,
        ];

        $io->progressStart(\count($importedArticles));

        foreach ($importedArticles as $newscoopId => $deschideId) {
            try {
                // Fetch translations from Newscoop (ru=15, en=1)
                $translations = $this->newscoopConnection->fetchTranslations($newscoopId, [1, 15]);

                if (\count($translations) === 0) {
                    $io->writeln(\sprintf('  [SKIP] Article %d has no translations', $newscoopId), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['articles_without_translations'];
                    ++$stats['articles_processed'];
                    $io->progressAdvance();
                    continue;
                }

                ++$stats['articles_with_translations'];
                $articleHasError = false;

                // Process each translation (ru, en)
                foreach ($translations as $translation) {
                    $locale = self::LOCALE_MAP[$translation['IdLanguage']] ?? null;

                    if (!$locale) {
                        $io->writeln(\sprintf('  [SKIP] Unknown language ID: %d', $translation['IdLanguage']), OutputInterface::VERBOSITY_VERBOSE);
                        continue;
                    }

                    // Check if translation already imported
                    $translationKey = "{$newscoopId}_{$locale}";
                    if ($this->migrationLogger->isImported('article_translation', $translationKey)) {
                        $io->writeln(\sprintf('  [SKIP] Translation %s already imported', $translationKey), OutputInterface::VERBOSITY_VERBOSE);
                        continue;
                    }

                    // Skip if no content
                    if (empty($translation['FContinut'])) {
                        $io->writeln(\sprintf('  [SKIP] Translation %s has no content', $translationKey), OutputInterface::VERBOSITY_VERBOSE);
                        continue;
                    }

                    try {
                        if ($dryRun) {
                            $io->writeln(\sprintf('  [DRY] Would import translation: %s (%s)', $translationKey, $locale), OutputInterface::VERBOSITY_VERBOSE);
                            ++$stats['translations_success'];
                            ++$stats["translations_{$locale}"];
                        } else {
                            // Step 1: GET current article to retrieve all fields
                            $getResponse = $this->httpClient->request('GET', self::API_BASE_URL . '/api/articles/' . $deschideId, [
                                'headers' => [
                                    'Accept' => 'application/ld+json',
                                    'Authorization' => 'Bearer ' . $jwtToken,
                                ],
                            ]);

                            if ($getResponse->getStatusCode() !== 200) {
                                throw new RuntimeException('Failed to fetch article: ' . $getResponse->getStatusCode());
                            }

                            $articleData = $getResponse->toArray();

                            // Step 2: Build translation payload with all required fields
                            $payload = $this->buildTranslationPayload($translation, $articleData);

                            // Step 3: PUT request with Accept-Language header (Gedmo magic!)
                            $response = $this->httpClient->request('PUT', self::API_BASE_URL . '/api/articles/' . $deschideId, [
                                'headers' => [
                                    'Content-Type' => 'application/ld+json',
                                    'Accept' => 'application/ld+json',
                                    'Accept-Language' => $locale, // CRITICAL for Gedmo Translatable!
                                    'Authorization' => 'Bearer ' . $jwtToken,
                                ],
                                'json' => $payload,
                            ]);

                            if ($response->getStatusCode() === 200) {
                                // Log success
                                $this->migrationLogger->logSuccess(
                                    'article_translation',
                                    $translationKey,
                                    $deschideId,
                                    [
                                        'locale' => $locale,
                                        'title' => substr($translation['FTitlu'] ?? '', 0, 100),
                                    ]
                                );

                                $io->writeln(\sprintf('  [OK] Translation %s: %s', $translationKey, substr($translation['FTitlu'] ?? '', 0, 60)), OutputInterface::VERBOSITY_VERBOSE);
                                ++$stats['translations_success'];
                                ++$stats["translations_{$locale}"];
                            } else {
                                $errorBody = '';

                                try {
                                    $errorData = $response->toArray(false);
                                    $errorBody = json_encode($errorData);
                                } catch (Exception $e) {
                                    $errorBody = 'Could not parse error response';
                                }

                                throw new RuntimeException('Unexpected status code: ' . $response->getStatusCode() . ' - ' . $errorBody);
                            }
                        }
                    } catch (Exception $e) {
                        $errorMessage = \sprintf('Translation %s: %s', $translationKey, $e->getMessage());

                        if (!$dryRun) {
                            $this->migrationLogger->logError('article_translation', $translationKey, $e->getMessage());
                        }

                        $io->writeln(\sprintf('  [ERROR] %s', $errorMessage), OutputInterface::VERBOSITY_VERBOSE);
                        ++$stats['translations_error'];
                        $articleHasError = true;
                    }
                }

                if (!$articleHasError) {
                    ++$stats['articles_processed'];
                }
            } catch (Exception $e) {
                $io->writeln(\sprintf('  [ERROR] Article %d: %s', $newscoopId, $e->getMessage()), OutputInterface::VERBOSITY_VERBOSE);
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        // Step 4: Display statistics
        $io->section('Step 4: Translation Statistics');

        $io->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total Articles Checked', $stats['total_articles'], '100%'],
                ['Articles Processed', $stats['articles_processed'], \sprintf('%.1f%%', ($stats['articles_processed'] / $stats['total_articles']) * 100)],
                ['Articles WITH Translations', $stats['articles_with_translations'], \sprintf('%.1f%%', ($stats['articles_with_translations'] / $stats['total_articles']) * 100)],
                ['Articles WITHOUT Translations', $stats['articles_without_translations'], \sprintf('%.1f%%', ($stats['articles_without_translations'] / $stats['total_articles']) * 100)],
                ['---', '---', '---'],
                ['Translations Success', $stats['translations_success'], ''],
                ['  - Russian (ru)', $stats['translations_ru'], \sprintf('%.1f%%', $stats['translations_success'] > 0 ? ($stats['translations_ru'] / $stats['translations_success']) * 100 : 0)],
                ['  - English (en)', $stats['translations_en'], \sprintf('%.1f%%', $stats['translations_success'] > 0 ? ($stats['translations_en'] / $stats['translations_success']) * 100 : 0)],
                ['Translations Error', $stats['translations_error'], ''],
            ]
        );

        if ($stats['translations_error'] > 0) {
            $io->warning(\sprintf('%d translations failed to import', $stats['translations_error']));

            if (!$dryRun) {
                $io->note('Check errors with: symfony console doctrine:query:sql "SELECT * FROM newscoop_migration_log WHERE entity_type=\'article_translation\' AND status=\'error\' LIMIT 20"');
            }
        }

        if ($stats['translations_success'] > 0) {
            $io->success(\sprintf(
                'Successfully imported %d translations (%d ru, %d en)!',
                $stats['translations_success'],
                $stats['translations_ru'],
                $stats['translations_en']
            ));
        }

        if ($dryRun) {
            $io->note('This was a DRY RUN. Run without --dry-run to actually import.');
        } else {
            $io->note([
                'To continue importing more translations, use:',
                \sprintf('  symfony console app:import:translations --offset=%d', $offset + \count($importedArticles)),
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Build translation payload for API.
     */
    private function buildTranslationPayload(array $translationData, array $articleData): array
    {
        // Use FTitlu if available, fallback to Name (both should exist for valid articles)
        $title = !empty($translationData['FTitlu']) ? $translationData['FTitlu'] : ($translationData['Name'] ?? '');
        $subtitle = $translationData['Fsubtitlu'] ?? null;
        $lead = $translationData['Flead'] ?? null;
        $content = $translationData['FContinut'] ?? '';

        // Clean HTML content
        $content = $this->cleanHtmlContent($content);

        // Build payload: merge translation with existing article data
        $payload = [
            // Translatable fields (override with translation)
            'title' => $title,
            'lead' => $lead,
            'content' => $content,

            // Non-translatable fields (keep from original)
            'status' => $articleData['status'] ?? 'draft',
            'isFeatured' => $articleData['isFeatured'] ?? false,
        ];

        // Optional fields
        if (isset($articleData['badge'])) {
            $payload['badge'] = $articleData['badge'];
        }

        if (isset($articleData['keywords'])) {
            $payload['keywords'] = $articleData['keywords'];
        }

        if (isset($articleData['publishedAt'])) {
            $payload['publishedAt'] = $articleData['publishedAt'];
        }

        return $payload;
    }

    /**
     * Clean HTML content.
     */
    private function cleanHtmlContent(string $content): string
    {
        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        // Fix common HTML issues
        $content = str_replace(['<p> </p>', '<p></p>'], '', $content);

        // Trim
        return trim($content);
    }
}
