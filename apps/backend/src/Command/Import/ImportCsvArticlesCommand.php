<?php

declare(strict_types=1);

namespace App\Command\Import;

use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\ExternalArticleMapping;
use App\Entity\Image;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Repository\ExternalArticleMappingRepository;
use App\Repository\ImageRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use League\Csv\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import:csv-articles',
    description: 'Import articles from CSV files (buchis.csv and buchis_2.csv)'
)]
class ImportCsvArticlesCommand extends Command
{
    private const CSV_FILES = [
        'buchis.csv',
        'buchis_2.csv',
    ];

    private const UPLOAD_DIR = '/var/www/deschide_news_app/deschide_backend/public/uploads/images/';

    private array $categoryCache = [];

    private array $authorCache = [];

    private array $imageCache = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly AuthorRepository $authorRepository,
        private readonly ImageRepository $imageRepository,
        private readonly ExternalArticleMappingRepository $mappingRepository,
        private readonly SluggerInterface $slugger,
        private readonly HttpClientInterface $httpClient,
        private readonly ManagerRegistry $doctrine,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'Specific CSV file to import (buchis.csv or buchis_2.csv)', null)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit number of articles to import', null)
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Skip first N articles', 0)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run mode (no database writes)')
            ->addOption('locale', 'l', InputOption::VALUE_OPTIONAL, 'Locale for articles', 'ro');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $specificFile = $input->getOption('file');
        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $offset = (int) $input->getOption('offset');
        $dryRun = $input->getOption('dry-run');
        $locale = $input->getOption('locale');

        $io->title('Import Articles from CSV Files');

        $io->section('Configuration');
        $io->definitionList(
            ['File' => $specificFile ?? 'ALL CSV files'],
            ['Locale' => $locale],
            ['Limit' => $limit ?? 'ALL'],
            ['Offset' => $offset],
            ['Mode' => $dryRun ? 'DRY RUN (no database writes)' : 'LIVE'],
        );

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No actual database changes will be made');
        }

        // Determine which files to process
        $csvFiles = $specificFile ? [$specificFile] : self::CSV_FILES;

        $totalStats = [
            'total' => 0,
            'success' => 0,
            'error' => 0,
            'skipped' => 0,
            'existing' => 0,
        ];

        foreach ($csvFiles as $csvFile) {
            $csvPath = '/var/www/deschide_news_app/migration_strategy/' . $csvFile;

            if (!file_exists($csvPath)) {
                $io->error("CSV file not found: {$csvPath}");
                continue;
            }

            $io->section("Processing file: {$csvFile}");

            try {
                $stats = $this->processCsvFile($csvPath, $locale, $limit, $offset, $dryRun, $io);

                // Merge stats
                foreach ($stats as $key => $value) {
                    $totalStats[$key] += $value;
                }
            } catch (Exception $e) {
                $io->error("Failed to process {$csvFile}: " . $e->getMessage());
                continue;
            }
        }

        // Display final statistics
        $io->section('Import Statistics');

        $io->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['Total Processed', $totalStats['total'], '100%'],
                ['Success', $totalStats['success'], \sprintf('%.1f%%', $totalStats['total'] > 0 ? ($totalStats['success'] / $totalStats['total']) * 100 : 0)],
                ['Existing (Skipped)', $totalStats['existing'], \sprintf('%.1f%%', $totalStats['total'] > 0 ? ($totalStats['existing'] / $totalStats['total']) * 100 : 0)],
                ['Skipped (Invalid)', $totalStats['skipped'], \sprintf('%.1f%%', $totalStats['total'] > 0 ? ($totalStats['skipped'] / $totalStats['total']) * 100 : 0)],
                ['Error', $totalStats['error'], \sprintf('%.1f%%', $totalStats['total'] > 0 ? ($totalStats['error'] / $totalStats['total']) * 100 : 0)],
            ]
        );

        if ($totalStats['success'] > 0) {
            $io->success(\sprintf('Successfully imported %d articles!', $totalStats['success']));
        }

        if ($totalStats['error'] > 0) {
            $io->warning(\sprintf('%d articles failed to import', $totalStats['error']));
        }

        if ($dryRun) {
            $io->note('This was a DRY RUN. Run without --dry-run to actually import.');
        }

        return Command::SUCCESS;
    }

    /**
     * Reset EntityManager when it becomes closed due to exceptions.
     */
    private function resetEntityManager(): void
    {
        if (!$this->entityManager->isOpen()) {
            // Get a fresh EntityManager instance
            $this->entityManager = $this->doctrine->resetManager();

            // Clear caches to prevent using detached entities
            $this->categoryCache = [];
            $this->authorCache = [];
            $this->imageCache = [];
        }
    }

    private function processCsvFile(string $csvPath, string $locale, ?int $limit, int $offset, bool $dryRun, SymfonyStyle $io): array
    {
        $csv = Reader::createFromPath($csvPath, 'r');
        $csv->setHeaderOffset(0);

        $stats = [
            'total' => 0,
            'success' => 0,
            'error' => 0,
            'skipped' => 0,
            'existing' => 0,
        ];

        $records = iterator_to_array($csv->getRecords());
        $totalRecords = \count($records);

        // Apply offset and limit
        $records = \array_slice($records, $offset, $limit);

        $io->writeln(\sprintf('Processing %d records (offset: %d, total in file: %d)', \count($records), $offset, $totalRecords));

        $io->progressStart(\count($records));

        foreach ($records as $rowIndex => $record) {
            ++$stats['total'];

            try {
                // Validate required fields
                if (empty($record['Item ID']) || empty($record['Title']) || empty($record['Slug'])) {
                    $io->writeln(\sprintf('  [SKIP] Row %d: Missing required fields', $rowIndex), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['skipped'];
                    $io->progressAdvance();
                    continue;
                }

                // Check if article already exists by external ID via mapping table
                $externalId = $record['Item ID'];
                $source = 'csv_buchis'; // Source identifier

                // Skip known problematic articles
                $problematicArticles = ['673da2a73525288561a3f39f']; // Article with image filename too long
                if (\in_array($externalId, $problematicArticles, true)) {
                    $io->writeln(\sprintf('  [SKIP] Article with Item ID %s is in skip list (known issue)', $externalId), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['skipped'];
                    $io->progressAdvance();
                    continue;
                }

                $existingArticle = $this->mappingRepository->findArticleByExternalId($source, $externalId);

                if ($existingArticle) {
                    $io->writeln(\sprintf('  [EXISTS] Article with Item ID %s already exists (ID: %d)', $externalId, $existingArticle->getId()), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['existing'];
                    $io->progressAdvance();
                    continue;
                }

                // Skip draft/archived articles
                if (isset($record['Draft']) && $record['Draft'] === 'true') {
                    $io->writeln(\sprintf('  [SKIP] Row %d: Draft article', $rowIndex), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['skipped'];
                    $io->progressAdvance();
                    continue;
                }

                if (isset($record['Archived']) && $record['Archived'] === 'true') {
                    $io->writeln(\sprintf('  [SKIP] Row %d: Archived article', $rowIndex), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['skipped'];
                    $io->progressAdvance();
                    continue;
                }

                if ($dryRun) {
                    $io->writeln(\sprintf('  [DRY] Would import: %s (ID: %s)', substr($record['Title'], 0, 60), $externalId), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['success'];
                } else {
                    // Import the article
                    $article = $this->importArticle($record, $locale, $io);

                    if ($article) {
                        // Create external mapping
                        $mapping = new ExternalArticleMapping();
                        $mapping->setArticle($article);
                        $mapping->setSource($source);
                        $mapping->setExternalId($externalId);
                        $mapping->setMetadata([
                            'original_slug' => $record['Slug'],
                            'collection_id' => $record['Collection ID'] ?? null,
                            'locale_id' => $record['Locale ID'] ?? null,
                            'import_date' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
                        ]);

                        $this->entityManager->persist($mapping);

                        $io->writeln(\sprintf('  [OK] Imported: %s (New ID: %d)', substr($record['Title'], 0, 60), $article->getId()), OutputInterface::VERBOSITY_VERBOSE);
                        ++$stats['success'];
                    } else {
                        ++$stats['error'];
                    }
                }
            } catch (Exception $e) {
                $io->writeln(\sprintf('  [ERROR] Failed to import article "%s": %s', substr($record['Title'] ?? 'Unknown', 0, 100), $e->getMessage()));
                ++$stats['error'];

                // Check if EntityManager is closed
                if (!$this->entityManager->isOpen()) {
                    $io->writeln('  [WARN] EntityManager closed due to error - resetting...', OutputInterface::VERBOSITY_VERBOSE);

                    // Reset EntityManager to continue processing
                    $this->resetEntityManager();

                    $io->writeln('  [OK] EntityManager reset successfully', OutputInterface::VERBOSITY_VERBOSE);
                }
            }

            $io->progressAdvance();

            // Flush every 50 records to avoid memory issues
            if (!$dryRun && $stats['success'] % 50 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                // Clear caches to prevent memory leaks and detached entities
                $this->categoryCache = [];
                $this->authorCache = [];
                $this->imageCache = [];
            }
        }

        $io->progressFinish();

        // Final flush
        if (!$dryRun) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        return $stats;
    }

    private function importArticle(array $record, string $locale, SymfonyStyle $io): ?Article
    {
        try {
            $article = new Article();

            // Set translatable locale
            $article->setTranslatableLocale($locale);

            // Basic fields
            $article->setTitle($record['Title']);

            // Handle slug - check if exists and add suffix if needed
            $baseSlug = $record['Slug'];
            $slug = $baseSlug;
            $counter = 1;

            // Check if slug exists
            while ($this->articleRepository->findOneBy(['slug' => $slug])) {
                $slug = $baseSlug . '-' . $counter;
                ++$counter;

                // Safety check to prevent infinite loop
                if ($counter > 100) {
                    $slug = $baseSlug . '-' . uniqid();
                    break;
                }
            }

            $article->setSlug($slug);

            // Content fields
            if (!empty($record['Lead text'])) {
                $article->setLead($this->cleanHtml($record['Lead text']));
            }

            if (!empty($record['Content Text'])) {
                $article->setContent($this->cleanHtml($record['Content Text']));
            }

            // Status
            $article->setStatus(ArticleStatus::PUBLISHED);

            // Badges
            if (isset($record['Is Breaking News?']) && $record['Is Breaking News?'] === 'true') {
                $article->setBadge(ArticleBadge::BREAKING);
            } elseif (isset($record['Is News alert?']) && $record['Is News alert?'] === 'true') {
                $article->setBadge(ArticleBadge::ALERT);
            } elseif (isset($record['Is Flash News?']) && $record['Is Flash News?'] === 'true') {
                $article->setBadge(ArticleBadge::FLASH);
            }

            // Featured flag
            if (isset($record['Is featured?']) && $record['Is featured?'] === 'true') {
                $article->setIsFeatured(true);
            }

            // Keywords - Article entity doesn't have keywords field yet
            // TODO: Add keywords field to Article entity if needed

            // Published date - prefer Manual Data over Published On
            $publishedDate = null;
            if (!empty($record['Manual Data'])) {
                $publishedDate = $this->parseDate($record['Manual Data']);
            } elseif (!empty($record['Published On'])) {
                $publishedDate = $this->parseDate($record['Published On']);
            }

            if ($publishedDate) {
                $article->setPublishedAt($publishedDate);
            }

            // Category
            if (!empty($record['Category'])) {
                $category = $this->findOrCreateCategory($record['Category'], $locale);
                if ($category) {
                    $article->setCategory($category);
                }
            }

            // Author
            if (!empty($record['Author'])) {
                $author = $this->findOrCreateAuthor($record['Author']);
                if ($author) {
                    $article->addAuthor($author);
                }
            }

            // Persist article first to get an ID
            $this->entityManager->persist($article);
            $this->entityManager->flush();

            // Handle main image
            if (!empty($record['Main Image'])) {
                $image = $this->downloadAndCreateImage($record['Main Image'], $record['Short Description'] ?? '', $io);
                if ($image) {
                    $articleImage = new ArticleImage();
                    $articleImage->setArticle($article);
                    $articleImage->setImage($image);
                    $articleImage->setPosition(0);
                    $articleImage->setIsFeatured(true);

                    $this->entityManager->persist($articleImage);
                }
            }

            $this->entityManager->flush();

            return $article;
        } catch (Exception $e) {
            $io->writeln(\sprintf('  [ERROR] Failed to import article "%s": %s', $record['Title'] ?? 'Unknown', $e->getMessage()), OutputInterface::VERBOSITY_VERBOSE);

            return null;
        }
    }

    private function findOrCreateCategory(string $categoryName, string $locale): ?Category
    {
        $cacheKey = $categoryName . '_' . $locale;

        if (isset($this->categoryCache[$cacheKey])) {
            return $this->categoryCache[$cacheKey];
        }

        // Try to find by title (case-insensitive)
        $category = $this->categoryRepository->createQueryBuilder('c')
            ->where('LOWER(c.title) = LOWER(:title)')
            ->setParameter('title', $categoryName)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$category) {
            // Create new category (capitalize first letter)
            $category = new Category();
            $category->setTranslatableLocale($locale);
            $category->setTitle(ucfirst(strtolower($categoryName)));
            $category->setSlug($this->slugger->slug($categoryName)->lower()->toString());

            $this->entityManager->persist($category);
            $this->entityManager->flush();
        }

        $this->categoryCache[$cacheKey] = $category;

        return $category;
    }

    private function findOrCreateAuthor(string $authorName): ?Author
    {
        // Trim and validate author name
        $authorName = trim($authorName);
        if (empty($authorName)) {
            return null;
        }

        if (isset($this->authorCache[$authorName])) {
            return $this->authorCache[$authorName];
        }

        // Parse author name
        $nameParts = explode(' ', $authorName, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        // Validate that we have at least a first name
        if (empty($firstName)) {
            return null;
        }

        // Try to find by name
        $author = $this->authorRepository->findOneBy([
            'firstName' => $firstName,
            'lastName' => $lastName,
        ]);

        if (!$author) {
            // Create new author with placeholder email
            $author = new Author();
            $author->setFirstName($firstName);
            $author->setLastName($lastName);
            $author->setSlug($this->slugger->slug($authorName)->lower()->toString());

            // Generate placeholder email from slug
            $emailSlug = $this->slugger->slug($authorName)->lower()->toString();
            $author->setEmail($emailSlug . '@imported.deschide.md');

            $this->entityManager->persist($author);
            $this->entityManager->flush();
        }

        $this->authorCache[$authorName] = $author;

        return $author;
    }

    private function downloadAndCreateImage(string $imageUrl, string $description, SymfonyStyle $io): ?Image
    {
        try {
            // Check cache
            if (isset($this->imageCache[$imageUrl])) {
                return $this->imageCache[$imageUrl];
            }

            // Check if image already exists by URL (truncate if needed)
            $originalFilenameCheck = basename($imageUrl);
            if (\strlen($originalFilenameCheck) > 255) {
                $originalFilenameCheck = substr($originalFilenameCheck, 0, 255);
            }

            $existingImage = $this->imageRepository->findOneBy(['originalFilename' => $originalFilenameCheck]);
            if ($existingImage) {
                $this->imageCache[$imageUrl] = $existingImage;

                return $existingImage;
            }

            // Download image
            $response = $this->httpClient->request('GET', $imageUrl, [
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() !== 200) {
                $io->writeln(\sprintf('  [WARN] Failed to download image: %s (Status: %d)', $imageUrl, $response->getStatusCode()), OutputInterface::VERBOSITY_VERY_VERBOSE);

                return null;
            }

            $imageContent = $response->getContent();
            $contentType = $response->getHeaders()['content-type'][0] ?? 'application/octet-stream';

            // Determine file extension
            $extension = match ($contentType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'jpg',
            };

            // Generate unique filename
            $filename = \sprintf('csv_import_%s.%s', uniqid('', true), $extension);
            $filePath = self::UPLOAD_DIR . $filename;

            // Ensure directory exists
            if (!is_dir(self::UPLOAD_DIR)) {
                mkdir(self::UPLOAD_DIR, 0o755, true);
            }

            // Save file
            file_put_contents($filePath, $imageContent);

            // Get image dimensions
            $imageInfo = getimagesize($filePath);
            $width = $imageInfo[0] ?? null;
            $height = $imageInfo[1] ?? null;

            // Create Image entity
            $image = new Image();
            $image->setFilename($filename);
            $image->setPath('images/' . $filename);

            // Truncate originalFilename if too long (max 255 chars)
            $originalFilename = basename($imageUrl);
            if (\strlen($originalFilename) > 255) {
                $originalFilename = substr($originalFilename, 0, 255);
            }

            $image->setOriginalFilename($originalFilename);
            $image->setMimeType($contentType);
            $image->setSize(\strlen($imageContent));

            if ($width && $height) {
                $image->setWidth($width);
                $image->setHeight($height);
            }

            if (!empty($description)) {
                // Truncate alt text if too long (max 255 chars)
                $alt = \strlen($description) > 255 ? substr($description, 0, 255) : $description;
                $image->setAlt($alt);
            }

            $this->entityManager->persist($image);

            // Only flush if EntityManager is still open
            if ($this->entityManager->isOpen()) {
                $this->entityManager->flush();
                $this->imageCache[$imageUrl] = $image;

                return $image;
            }
            // EntityManager is closed, return null and let the main catch block handle it
            $io->writeln(\sprintf('  [WARN] EntityManager closed while persisting image %s', $imageUrl), OutputInterface::VERBOSITY_VERY_VERBOSE);

            return null;

        } catch (Exception $e) {
            $io->writeln(\sprintf('  [WARN] Failed to download/process image %s: %s', $imageUrl, $e->getMessage()), OutputInterface::VERBOSITY_VERY_VERBOSE);

            // Don't throw - just return null and let the article import continue
            return null;
        }
    }

    private function parseDate(string $dateString): ?DateTimeImmutable
    {
        try {
            // Remove timezone info in parentheses
            $cleaned = preg_replace('/\s*\([^)]*\)$/', '', $dateString);

            // Parse date
            $date = new DateTimeImmutable($cleaned);

            return $date;
        } catch (Exception $e) {
            return null;
        }
    }

    private function cleanHtml(string $content): string
    {
        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        // Fix common HTML issues
        $content = str_replace(['<p> </p>', '<p></p>'], '', $content);

        // Trim
        return trim($content);
    }
}
