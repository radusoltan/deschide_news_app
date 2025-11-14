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
    name: 'app:import:articles',
    description: 'Import articles from Newscoop via API'
)]
class ImportArticlesCommand extends Command
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
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit number of articles', null)
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Offset for pagination', 0)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run mode (no API calls)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $locale = $input->getOption('locale');
        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $offset = (int) $input->getOption('offset');
        $dryRun = $input->getOption('dry-run');

        $io->title('FAZA 4: Import Articole Newscoop → Deschide');

        $io->section('Configuration');
        $io->definitionList(
            ['Locale' => $locale],
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

        // Step 2: Fetch articles from Newscoop
        $io->section('Step 2: Fetch Articles from Newscoop');

        try {
            $articles = $this->newscoopConnection->fetchArticles($locale, true, $limit, $offset);
            $io->writeln(\sprintf('Found %d articles in Newscoop (locale: %s, offset: %d)', \count($articles), $locale, $offset));

            if (\count($articles) === 0) {
                $io->warning('No articles found in Newscoop!');

                return Command::SUCCESS;
            }
        } catch (Exception $e) {
            $io->error('Failed to fetch articles: ' . $e->getMessage());

            return Command::FAILURE;
        }

        // Step 3: Import articles via API
        $io->section('Step 3: Import Articles via API');

        $stats = [
            'total' => \count($articles),
            'success' => 0,
            'error' => 0,
            'skipped' => 0,
        ];

        $io->progressStart(\count($articles));

        foreach ($articles as $articleData) {
            $newscoopId = $articleData['Number'];
            $title = $articleData['FTitlu'] ?? $articleData['Name'] ?? 'Untitled';

            try {
                // Check if already imported
                if ($this->migrationLogger->isImported('article', $newscoopId)) {
                    $io->writeln(\sprintf('  [SKIP] Article %d already imported', $newscoopId), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['skipped'];
                    $io->progressAdvance();
                    continue;
                }

                // Skip if no content
                if (empty($articleData['FContinut'])) {
                    $io->writeln(\sprintf('  [SKIP] Article %d has no content', $newscoopId), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['skipped'];
                    $io->progressAdvance();
                    continue;
                }

                // Build API payload
                $payload = $this->buildArticlePayload($articleData, $locale);

                if ($dryRun) {
                    $io->writeln(\sprintf('  [DRY] Would import: %s', substr($title, 0, 60)), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['success'];
                } else {
                    // API POST request
                    $response = $this->httpClient->request('POST', self::API_BASE_URL . '/api/articles', [
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
                            'article',
                            $newscoopId,
                            $deschideId,
                            [
                                'title' => substr($title, 0, 100),
                                'locale' => $locale,
                                'section' => $articleData['NrSection'] ?? null,
                            ]
                        );

                        $io->writeln(\sprintf('  [OK] Article %d → %d: %s', $newscoopId, $deschideId, substr($title, 0, 60)), OutputInterface::VERBOSITY_VERBOSE);
                        ++$stats['success'];
                    } else {
                        throw new RuntimeException('Unexpected status code: ' . $response->getStatusCode());
                    }
                }
            } catch (Exception $e) {
                $errorMessage = \sprintf('Article %d (%s): %s', $newscoopId, substr($title, 0, 40), $e->getMessage());

                if (!$dryRun) {
                    $this->migrationLogger->logError('article', $newscoopId, $e->getMessage());
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
            $io->warning(\sprintf('%d articles failed to import', $stats['error']));

            if (!$dryRun) {
                $io->note('Check errors with: symfony console doctrine:query:sql "SELECT * FROM newscoop_migration_log WHERE entity_type=\'article\' AND status=\'error\' LIMIT 20"');
            }
        }

        if ($stats['success'] > 0) {
            $io->success(\sprintf('Successfully imported %d articles!', $stats['success']));
        }

        if ($dryRun) {
            $io->note('This was a DRY RUN. Run without --dry-run to actually import.');
        } else {
            $io->note([
                'To continue importing more articles, use:',
                \sprintf('  symfony console app:import:articles --locale=%s --offset=%d', $locale, $offset + \count($articles)),
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Build article payload for API.
     */
    private function buildArticlePayload(array $articleData, string $locale): array
    {
        $title = $articleData['FTitlu'] ?? $articleData['Name'] ?? 'Untitled';
        $subtitle = $articleData['Fsubtitlu'] ?? null;
        $lead = $articleData['Flead'] ?? null;
        $content = $articleData['FContinut'] ?? '';

        // Clean HTML content
        $content = $this->cleanHtmlContent($content);

        // Determine status
        $status = $articleData['Published'] === 'Y' ? 'published' : 'draft';

        // Determine badge
        $badge = null;
        if (!empty($articleData['FBREAKING_NEWS']) && $articleData['FBREAKING_NEWS'] !== '0') {
            $badge = 'breaking';
        } elseif (!empty($articleData['FNEWS_ALERT']) && $articleData['FNEWS_ALERT'] !== '0') {
            $badge = 'alert';
        } elseif (!empty($articleData['FFLASH']) && $articleData['FFLASH'] !== '0') {
            $badge = 'flash';
        }

        // Build payload
        $payload = [
            'title' => $title,
            'lead' => $lead,
            'content' => $content,
            'status' => $status,
            'isFeatured' => ($articleData['OnFrontPage'] ?? 'N') === 'Y',
        ];

        // Add optional fields
        if ($badge) {
            $payload['badge'] = $badge;
        }

        if (!empty($articleData['Keywords'])) {
            $payload['keywords'] = $articleData['Keywords'];
        }

        // Add published date if available
        if (!empty($articleData['PublishDate'])) {
            $payload['publishedAt'] = date('c', strtotime($articleData['PublishDate']));
        }

        // Map category (will be done in FAZA 7 - Relations)
        // Map authors (will be done in FAZA 7 - Relations)
        // Map images (will be done in FAZA 7 - Relations)

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
