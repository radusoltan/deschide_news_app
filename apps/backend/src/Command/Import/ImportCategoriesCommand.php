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
    name: 'app:import:categories',
    description: 'Import categories from Newscoop via API'
)]
class ImportCategoriesCommand extends Command
{
    private const API_BASE_URL = 'http://127.0.0.1:8081';

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
            ->addOption('locale', 'l', InputOption::VALUE_OPTIONAL, 'Locale (ro, ru, en)', 'ro')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit number of categories', null)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run mode (no API calls)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $locale = $input->getOption('locale');
        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $dryRun = $input->getOption('dry-run');

        $io->title('FAZA 1: Import Categorii Newscoop → Deschide');

        $io->section('Configuration');
        $io->definitionList(
            ['Locale' => $locale],
            ['Limit' => $limit ?? 'ALL'],
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

        // Step 2: Fetch categories from Newscoop
        $io->section('Step 2: Fetch Categories from Newscoop');

        try {
            $categories = $this->newscoopConnection->fetchCategories($locale, $limit);
            $io->writeln(\sprintf('Found %d categories in Newscoop (locale: %s)', \count($categories), $locale));

            if (\count($categories) === 0) {
                $io->warning('No categories found in Newscoop!');

                return Command::SUCCESS;
            }
        } catch (Exception $e) {
            $io->error('Failed to fetch categories: ' . $e->getMessage());

            return Command::FAILURE;
        }

        // Step 3: Import categories via API
        $io->section('Step 3: Import Categories via API');

        $stats = [
            'total' => \count($categories),
            'success' => 0,
            'error' => 0,
            'skipped' => 0,
        ];

        $io->progressStart(\count($categories));

        foreach ($categories as $categoryData) {
            $newscoopId = $categoryData['Number'];
            $newscoopName = $categoryData['Name'];

            try {
                // Check if already imported
                if ($this->migrationLogger->isImported('category', $newscoopId)) {
                    $io->writeln(\sprintf('  [SKIP] Category %d already imported', $newscoopId), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['skipped'];
                    $io->progressAdvance();
                    continue;
                }

                // Build API payload
                $payload = $this->buildCategoryPayload($categoryData);

                if ($dryRun) {
                    $io->writeln(\sprintf('  [DRY] Would import: %s', $newscoopName), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['success'];
                } else {
                    // API POST request
                    $response = $this->httpClient->request('POST', self::API_BASE_URL . '/api/categories', [
                        'headers' => [
                            'Content-Type' => 'application/ld+json',
                            'Accept' => 'application/ld+json',
                            'Accept-Language' => $locale,
                            'Authorization' => 'Bearer ' . $jwtToken,
                        ],
                        'json' => $payload,
                    ]);

                    if ($response->getStatusCode() === 201) {
                        $responseData = $response->toArray();
                        $deschideId = $responseData['id'];

                        // Log success
                        $this->migrationLogger->logSuccess(
                            'category',
                            $newscoopId,
                            $deschideId,
                            [
                                'name' => $newscoopName,
                                'locale' => $locale,
                            ]
                        );

                        $io->writeln(\sprintf('  [OK] Category %d → %d: %s', $newscoopId, $deschideId, $newscoopName), OutputInterface::VERBOSITY_VERBOSE);
                        ++$stats['success'];
                    } else {
                        throw new RuntimeException('Unexpected status code: ' . $response->getStatusCode());
                    }
                }
            } catch (Exception $e) {
                $errorMessage = \sprintf('Category %d (%s): %s', $newscoopId, $newscoopName, $e->getMessage());

                if (!$dryRun) {
                    $this->migrationLogger->logError('category', $newscoopId, $e->getMessage());
                }

                $io->writeln(\sprintf('  [ERROR] %s', $errorMessage), OutputInterface::VERBOSITY_VERBOSE);
                ++$stats['error'];
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        // Step 4: Display statistics
        $io->section('Step 4: Import Statistics');

        $io->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['Total', $stats['total'], '100%'],
                ['Success', $stats['success'], \sprintf('%.1f%%', ($stats['success'] / $stats['total']) * 100)],
                ['Error', $stats['error'], \sprintf('%.1f%%', ($stats['error'] / $stats['total']) * 100)],
                ['Skipped', $stats['skipped'], \sprintf('%.1f%%', ($stats['skipped'] / $stats['total']) * 100)],
            ]
        );

        if ($stats['error'] > 0) {
            $io->warning(\sprintf('%d categories failed to import', $stats['error']));

            if (!$dryRun) {
                $io->note('Check errors with: symfony console doctrine:query:sql "SELECT * FROM newscoop_migration_log WHERE entity_type=\'category\' AND status=\'error\'"');
            }
        }

        if ($stats['success'] > 0) {
            $io->success(\sprintf('Successfully imported %d categories!', $stats['success']));
        }

        if ($dryRun) {
            $io->note('This was a DRY RUN. Run without --dry-run to actually import.');
        }

        return Command::SUCCESS;
    }

    /**
     * Build category payload for API.
     */
    private function buildCategoryPayload(array $categoryData): array
    {
        $name = $categoryData['Name'];
        $shortName = $categoryData['ShortName'] ?? null;
        $description = $categoryData['Description'] ?? null;

        // Generate slug from name
        $slug = $this->generateSlug($shortName ?? $name);

        return [
            'title' => $name,
            'slug' => $slug,
            'description' => $description,
        ];
    }

    /**
     * Generate slug from string.
     */
    private function generateSlug(string $text): string
    {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Romanian diacritics
        $text = str_replace(
            ['ă', 'â', 'î', 'ș', 'ț', 'Ă', 'Â', 'Î', 'Ș', 'Ț'],
            ['a', 'a', 'i', 's', 't', 'a', 'a', 'i', 's', 't'],
            $text
        );

        // Russian cyrillic (basic transliteration)
        $cyrillicMap = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];
        $text = strtr($text, $cyrillicMap);

        // Remove special characters
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);

        // Replace spaces with hyphens
        $text = preg_replace('/\s+/', '-', $text);

        // Remove multiple hyphens
        $text = preg_replace('/-+/', '-', $text);

        // Trim hyphens
        return trim($text, '-');
    }
}
