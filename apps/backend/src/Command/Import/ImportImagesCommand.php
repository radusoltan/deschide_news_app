<?php

declare(strict_types=1);

namespace App\Command\Import;

use App\Service\Import\ImportTokenService;
use App\Service\Import\MigrationLoggerService;
use App\Service\Import\NewscoopConnectionService;
use Exception;
use Imagick;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import:images',
    description: 'Import images from Newscoop via API with multipart/form-data upload and WebP conversion'
)]
class ImportImagesCommand extends Command
{
    private const API_BASE_URL = 'http://127.0.0.1:8081';

    private const NEWSCOOP_IMAGES_PATH = '/mnt/d/ext-hdd/backups/alpha/newscoop/images';

    private const UPLOAD_TEMP_PATH = '/tmp/newscoop_import';

    private const WEBP_QUALITY = 85; // Good balance between quality and size

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
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit number of images', null)
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Offset for pagination', 0)
            ->addOption('min-article', null, InputOption::VALUE_OPTIONAL, 'Minimum article number to filter images', null)
            ->addOption('max-article', null, InputOption::VALUE_OPTIONAL, 'Maximum article number to filter images', null)
            ->addOption('no-webp', null, InputOption::VALUE_NONE, 'Skip WebP conversion (import original format)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run mode (no API calls)');
    }

    /**
     * Convert image to WebP format using Imagick
     */
    private function convertToWebP(string $sourcePath, string $destPath): bool
    {
        try {
            $imagick = new Imagick($sourcePath);

            // Strip metadata to reduce size
            $imagick->stripImage();

            // Set WebP compression quality
            $imagick->setImageCompressionQuality(self::WEBP_QUALITY);

            // Convert to WebP
            $imagick->setImageFormat('webp');

            // Write to destination
            $imagick->writeImage($destPath);
            $imagick->destroy();

            return true;
        } catch (Exception $e) {
            // If conversion fails, return false to use original
            return false;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $offset = (int) $input->getOption('offset');
        $minArticle = $input->getOption('min-article') ? (int) $input->getOption('min-article') : null;
        $maxArticle = $input->getOption('max-article') ? (int) $input->getOption('max-article') : null;
        $noWebp = $input->getOption('no-webp');
        $dryRun = $input->getOption('dry-run');

        $io->title('FAZA 3: Import Imagini Newscoop → Deschide');

        $io->section('Configuration');
        $io->definitionList(
            ['Limit' => $limit ?? 'ALL'],
            ['Offset' => $offset],
            ['Min Article Number' => $minArticle ?? 'N/A'],
            ['Max Article Number' => $maxArticle ?? 'N/A'],
            ['WebP Conversion' => $noWebp ? 'DISABLED' : 'ENABLED (quality: ' . self::WEBP_QUALITY . ')'],
            ['Mode' => $dryRun ? 'DRY RUN (no API calls)' : 'LIVE'],
            ['Newscoop Images Path' => self::NEWSCOOP_IMAGES_PATH],
            ['Temp Upload Path' => self::UPLOAD_TEMP_PATH],
        );

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No actual API calls will be made');
        }

        // Create temp directory
        if (!$dryRun && !is_dir(self::UPLOAD_TEMP_PATH)) {
            mkdir(self::UPLOAD_TEMP_PATH, 0o777, true);
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

        // Step 2: Fetch images from Newscoop
        $io->section('Step 2: Fetch Images from Newscoop');

        try {
            $images = $this->newscoopConnection->fetchImages($limit, $offset, $minArticle, $maxArticle);
            $io->writeln(\sprintf('Found %d images in Newscoop (offset: %d)', \count($images), $offset));

            if (\count($images) === 0) {
                $io->warning('No images found in Newscoop!');

                return Command::SUCCESS;
            }
        } catch (Exception $e) {
            $io->error('Failed to fetch images: ' . $e->getMessage());

            return Command::FAILURE;
        }

        // Step 3: Import images via API
        $io->section('Step 3: Import Images via API');

        $stats = [
            'total' => \count($images),
            'success' => 0,
            'error' => 0,
            'skipped' => 0,
            'missing_file' => 0,
        ];

        $io->progressStart(\count($images));

        foreach ($images as $imageData) {
            $newscoopId = $imageData['Id'];
            $filename = $imageData['ImageFileName'];
            $sourcePath = self::NEWSCOOP_IMAGES_PATH . '/' . $filename;

            try {
                // Check if already imported
                if ($this->migrationLogger->isImported('image', $newscoopId)) {
                    $io->writeln(\sprintf('  [SKIP] Image %d already imported', $newscoopId), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['skipped'];
                    $io->progressAdvance();
                    continue;
                }

                // Check if source file exists
                if (!file_exists($sourcePath)) {
                    $io->writeln(\sprintf('  [MISSING] Image %d: file not found: %s', $newscoopId, $filename), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['missing_file'];

                    if (!$dryRun) {
                        $this->migrationLogger->logError('image', $newscoopId, 'Source file not found: ' . $filename);
                    }

                    $io->progressAdvance();
                    continue;
                }

                // Check file size (skip if 0 bytes)
                $fileSize = filesize($sourcePath);
                if ($fileSize === 0) {
                    $io->writeln(\sprintf('  [SKIP] Image %d: empty file (0 bytes): %s', $newscoopId, $filename), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['skipped'];
                    $io->progressAdvance();
                    continue;
                }

                if ($dryRun) {
                    $io->writeln(\sprintf('  [DRY] Would import: %s (%d bytes)', $filename, $fileSize), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['success'];
                } else {
                    // Determine file to upload (WebP converted or original)
                    $uploadPath = null;
                    $uploadFilename = $filename;
                    $originalSize = $fileSize;
                    $convertedToWebp = false;

                    if (!$noWebp) {
                        // Try to convert to WebP
                        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                        // Only convert supported formats (skip already WebP and GIFs with animation)
                        if (\in_array($extension, ['jpg', 'jpeg', 'png', 'bmp', 'tiff'], true)) {
                            $webpFilename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
                            $webpPath = self::UPLOAD_TEMP_PATH . '/' . $webpFilename;

                            if ($this->convertToWebP($sourcePath, $webpPath)) {
                                $uploadPath = $webpPath;
                                $uploadFilename = $webpFilename;
                                $convertedToWebp = true;
                                $newSize = filesize($webpPath);
                                $savedPercent = round((1 - $newSize / $originalSize) * 100, 1);
                                $io->writeln(\sprintf('  [WEBP] %s → %s (saved %.1f%%)', $filename, $webpFilename, $savedPercent), OutputInterface::VERBOSITY_VERBOSE);
                            }
                        }
                    }

                    // If not converted, copy original to temp
                    if ($uploadPath === null) {
                        $uploadPath = self::UPLOAD_TEMP_PATH . '/' . $filename;
                        copy($sourcePath, $uploadPath);
                    }

                    // Prepare multipart form data
                    $formData = [
                        'file' => fopen($uploadPath, 'r'),
                        'alt' => $imageData['Description'] ?? '',
                        'imageAuthor' => $imageData['Photographer'] ?? '',
                    ];

                    // API POST request with multipart/form-data
                    $response = $this->httpClient->request('POST', self::API_BASE_URL . '/api/images', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $jwtToken,
                        ],
                        'body' => $formData,
                    ]);

                    // Add delay to avoid rate limiting (429 Too Many Requests)
                    usleep(500000); // 500ms delay between requests

                    if ($response->getStatusCode() === 201) {
                        $responseData = $response->toArray();
                        $deschideId = $responseData['id'];

                        // Log success
                        $this->migrationLogger->logSuccess(
                            'image',
                            $newscoopId,
                            $deschideId,
                            [
                                'filename' => $filename,
                                'width' => $imageData['width'] ?? null,
                                'height' => $imageData['height'] ?? null,
                                'size' => $fileSize,
                            ]
                        );

                        // IMPORTANT: DO NOT delete source file - preserve originals on external HDD
                        // unlink($sourcePath); // DISABLED - keep originals safe!

                        $io->writeln(\sprintf('  [OK] Image %d → %d: %s (source preserved)', $newscoopId, $deschideId, $filename), OutputInterface::VERBOSITY_VERBOSE);
                        ++$stats['success'];
                    } else {
                        throw new RuntimeException('Unexpected status code: ' . $response->getStatusCode());
                    }

                    // Cleanup temp file
                    if (file_exists($uploadPath)) {
                        unlink($uploadPath);
                    }
                }
            } catch (Exception $e) {
                $errorMessage = \sprintf('Image %d (%s): %s', $newscoopId, $filename, $e->getMessage());

                if (!$dryRun) {
                    $this->migrationLogger->logError('image', $newscoopId, $e->getMessage());
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
                ['Missing File', $stats['missing_file'], \sprintf('%.1f%%', ($stats['missing_file'] / $stats['total']) * 100)],
            ]
        );

        if ($stats['error'] > 0) {
            $io->warning(\sprintf('%d images failed to import', $stats['error']));

            if (!$dryRun) {
                $io->note('Check errors with: symfony console doctrine:query:sql "SELECT * FROM newscoop_migration_log WHERE entity_type=\'image\' AND status=\'error\' LIMIT 20"');
            }
        }

        if ($stats['missing_file'] > 0) {
            $io->warning(\sprintf('%d images have missing source files', $stats['missing_file']));
        }

        if ($stats['success'] > 0) {
            $io->success(\sprintf('Successfully imported %d images!', $stats['success']));
        }

        if ($dryRun) {
            $io->note('This was a DRY RUN. Run without --dry-run to actually import.');
        } else {
            $io->note([
                'To continue importing more images, use:',
                \sprintf('  symfony console app:import:images --offset=%d', $offset + \count($images)),
            ]);
        }

        // Cleanup temp directory if empty
        if (!$dryRun && is_dir(self::UPLOAD_TEMP_PATH)) {
            $files = scandir(self::UPLOAD_TEMP_PATH);
            if (\count($files) <= 2) { // Only . and ..
                rmdir(self::UPLOAD_TEMP_PATH);
            }
        }

        return Command::SUCCESS;
    }
}
