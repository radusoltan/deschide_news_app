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
    name: 'app:import:authors',
    description: 'Import authors from Newscoop via API'
)]
class ImportAuthorsCommand extends Command
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
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit number of authors', null)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run mode (no API calls)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $dryRun = $input->getOption('dry-run');

        $io->title('FAZA 2: Import Autori Newscoop → Deschide');

        $io->section('Configuration');
        $io->definitionList(
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

        // Step 2: Fetch authors from Newscoop
        $io->section('Step 2: Fetch Authors from Newscoop');

        try {
            $authors = $this->newscoopConnection->fetchAuthors($limit);
            $io->writeln(\sprintf('Found %d authors in Newscoop', \count($authors)));

            if (\count($authors) === 0) {
                $io->warning('No authors found in Newscoop!');

                return Command::SUCCESS;
            }
        } catch (Exception $e) {
            $io->error('Failed to fetch authors: ' . $e->getMessage());

            return Command::FAILURE;
        }

        // Step 3: Import authors via API
        $io->section('Step 3: Import Authors via API');

        $stats = [
            'total' => \count($authors),
            'success' => 0,
            'error' => 0,
            'skipped' => 0,
        ];

        $io->progressStart(\count($authors));

        foreach ($authors as $authorData) {
            $newscoopId = $authorData['id'];
            $authorName = trim(($authorData['first_name'] ?? '') . ' ' . ($authorData['last_name'] ?? ''));

            try {
                // Check if already imported
                if ($this->migrationLogger->isImported('author', $newscoopId)) {
                    $io->writeln(\sprintf('  [SKIP] Author %d already imported', $newscoopId), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['skipped'];
                    $io->progressAdvance();
                    continue;
                }

                // Build API payload
                $payload = $this->buildAuthorPayload($authorData);

                // Skip if invalid data
                if ($payload === null) {
                    $io->writeln(\sprintf('  [SKIP] Author %d has invalid data: %s', $newscoopId, $authorName), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['skipped'];
                    $io->progressAdvance();
                    continue;
                }

                if ($dryRun) {
                    $io->writeln(\sprintf('  [DRY] Would import: %s', $authorName), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['success'];
                } else {
                    // API POST request
                    $response = $this->httpClient->request('POST', self::API_BASE_URL . '/api/authors', [
                        'headers' => [
                            'Content-Type' => 'application/ld+json',
                            'Accept' => 'application/ld+json',
                            'Authorization' => 'Bearer ' . $jwtToken,
                        ],
                        'json' => $payload,
                    ]);

                    if ($response->getStatusCode() === 201) {
                        $responseData = $response->toArray();
                        $deschideId = $responseData['id'];

                        // Log success
                        $this->migrationLogger->logSuccess(
                            'author',
                            $newscoopId,
                            $deschideId,
                            [
                                'name' => $authorName,
                                'email' => $authorData['email'] ?? null,
                            ]
                        );

                        $io->writeln(\sprintf('  [OK] Author %d → %d: %s', $newscoopId, $deschideId, $authorName), OutputInterface::VERBOSITY_VERBOSE);
                        ++$stats['success'];
                    } else {
                        throw new RuntimeException('Unexpected status code: ' . $response->getStatusCode());
                    }
                }
            } catch (Exception $e) {
                $errorMessage = \sprintf('Author %d (%s): %s', $newscoopId, $authorName, $e->getMessage());

                if (!$dryRun) {
                    $this->migrationLogger->logError('author', $newscoopId, $e->getMessage());
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
            $io->warning(\sprintf('%d authors failed to import', $stats['error']));

            if (!$dryRun) {
                $io->note('Check errors with: symfony console doctrine:query:sql "SELECT * FROM newscoop_migration_log WHERE entity_type=\'author\' AND status=\'error\'"');
            }
        }

        if ($stats['success'] > 0) {
            $io->success(\sprintf('Successfully imported %d authors!', $stats['success']));
        }

        if ($dryRun) {
            $io->note('This was a DRY RUN. Run without --dry-run to actually import.');
        }

        return Command::SUCCESS;
    }

    /**
     * Build author payload for API
     * Returns null if data is invalid.
     */
    private function buildAuthorPayload(array $authorData): ?array
    {
        $firstName = trim($authorData['first_name'] ?? '');
        $lastName = trim($authorData['last_name'] ?? '');
        $email = trim($authorData['email'] ?? '');
        $bio = trim($authorData['biography'] ?? '');

        // Handle cases where author has no first name (use last name as first name)
        if (empty($firstName) && !empty($lastName)) {
            $firstName = $lastName;
            $lastName = '';
        }

        // Handle cases where author has only one word name
        if (empty($firstName) && empty($lastName)) {
            return null; // Skip authors with no name at all
        }

        // If last name is empty, use a placeholder
        if (empty($lastName)) {
            $lastName = '.';
        }

        // Generate email if missing
        if (empty($email)) {
            $emailSlug = $this->generateSlug($firstName . ' ' . $lastName);
            $email = $emailSlug . '@deschide.md';
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailSlug = $this->generateSlug($firstName . ' ' . $lastName);
            $email = $emailSlug . '@deschide.md';
        }

        return [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'bio' => !empty($bio) ? $bio : null,
            'status' => 'active',
            'isActive' => true,
        ];
    }

    /**
     * Generate slug from string (same logic as categories).
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
