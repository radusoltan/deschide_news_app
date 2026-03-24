<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Entity\Image;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:seed:images',
    description: 'Seed placeholder images, create Image entities, and link to articles'
)]
class SeedImagesCommand extends Command
{
    private readonly string $uploadsDir;

    /** @var array<string, string> Category label => hex color */
    private const CATEGORY_COLORS = [
        'Politica' => '#1d4ed8',
        'Economie' => '#047857',
        'Societate' => '#7c3aed',
        'Sport' => '#dc2626',
        'Cultura' => '#b45309',
        'Externe' => '#0891b2',
        'Stiinta' => '#6366f1',
        'Tehnologie' => '#ec4899',
        'Sanatate' => '#14b8a6',
        'Educatie' => '#f59e0b',
        'Justitie' => '#4a5568',
        'Mediu' => '#10b981',
        'Opinii' => '#8b5cf6',
        'Investigatii' => '#ef4444',
        'Interviu' => '#06b6d4',
        'Reportaj' => '#d97706',
        'Diaspora' => '#3b82f6',
        'Lifestyle' => '#f472b6',
    ];

    /** @var array<int, array{int, int}> Width x Height options */
    private const IMAGE_SIZES = [
        [1920, 1080],
        [800, 600],
        [1200, 675],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        ParameterBagInterface $params,
    ) {
        parent::__construct();
        $projectDir = $params->get('kernel.project_dir');
        $this->uploadsDir = $projectDir . '/public/uploads/images/originals';
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', null, InputOption::VALUE_OPTIONAL, 'Number of images to generate', 200)
            ->addOption('link-percent', null, InputOption::VALUE_OPTIONAL, 'Percentage of articles to link (0-100)', 80)
            ->addOption('skip-images', null, InputOption::VALUE_NONE, 'Skip image generation (only link existing images to articles)')
            ->addOption('skip-links', null, InputOption::VALUE_NONE, 'Skip article linking (only generate images)')
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Batch flush size', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('STG-09: Seed Images and Link to Articles');

        $count = (int) $input->getOption('count');
        $linkPercent = (int) $input->getOption('link-percent');
        $skipImages = $input->getOption('skip-images');
        $skipLinks = $input->getOption('skip-links');
        $batchSize = (int) $input->getOption('batch-size');

        $io->definitionList(
            ['Images to generate' => $skipImages ? 'SKIP' : (string) $count],
            ['Link percentage' => $skipLinks ? 'SKIP' : $linkPercent . '%'],
            ['Batch size' => (string) $batchSize],
            ['Uploads directory' => $this->uploadsDir],
        );

        // Ensure uploads directory exists
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0755, true);
            $io->note('Created uploads directory: ' . $this->uploadsDir);
        }

        $newImageIds = [];

        // Phase 1: Generate placeholder images and create Image entities
        if (!$skipImages) {
            $newImageIds = $this->generateImages($io, $count, $batchSize);
        }

        // Phase 2: Link images to articles
        if (!$skipLinks) {
            $this->linkImagesToArticles($io, $linkPercent, $batchSize);
        }

        // Phase 3: Print summary
        $this->printSummary($io);

        $io->success('STG-09 completed! Run "symfony console app:import:generate-thumbnails" to generate thumbnails.');

        return Command::SUCCESS;
    }

    /**
     * @return list<int> IDs of created Image entities
     */
    private function generateImages(SymfonyStyle $io, int $count, int $batchSize): array
    {
        $io->section('Phase 1: Generate Placeholder Images');

        $categoryLabels = array_keys(self::CATEGORY_COLORS);
        $categoryColors = array_values(self::CATEGORY_COLORS);
        $createdIds = [];
        $filesCreated = 0;
        $entitiesCreated = 0;
        $skipped = 0;

        $io->progressStart($count);

        for ($i = 1; $i <= $count; ++$i) {
            // Use deterministic selection based on index for idempotency
            $sizeIndex = $i % count(self::IMAGE_SIZES);
            [$width, $height] = self::IMAGE_SIZES[$sizeIndex];

            $catIndex = $i % count($categoryLabels);
            $label = $categoryLabels[$catIndex];
            $hexColor = $categoryColors[$catIndex];

            $filename = sprintf('seed_img_%03d_%dx%d.jpg', $i, $width, $height);
            $filePath = $this->uploadsDir . '/' . $filename;

            // Check if file already exists on disk
            if (file_exists($filePath)) {
                // Check if entity also exists
                $existing = $this->entityManager->getRepository(Image::class)->findOneBy(['filename' => $filename]);
                if ($existing) {
                    $createdIds[] = $existing->getId();
                    ++$skipped;
                    $io->progressAdvance();

                    continue;
                }
            }

            // Generate the JPEG image file
            $fileSize = $this->createPlaceholderImage($filePath, $width, $height, $label, $hexColor, $i);

            if ($fileSize === false) {
                $io->warning("Failed to create image: $filename");

                continue;
            }

            ++$filesCreated;

            // Create Image entity
            $image = new Image();
            $image->setFilename($filename);
            $image->setOriginalFilename($filename);
            $image->setPath('images/originals/' . $filename);
            $image->setWidth($width);
            $image->setHeight($height);
            $image->setMimeType('image/jpeg');
            $image->setSize($fileSize);
            $image->setAlt(sprintf('Placeholder image %d - %s (%dx%d)', $i, $label, $width, $height));
            $image->setCaption(sprintf('%s - %dx%d', $label, $width, $height));
            $image->setImageAuthor('Seed Generator');

            $this->entityManager->persist($image);
            ++$entitiesCreated;

            // Batch flush
            if ($entitiesCreated % $batchSize === 0) {
                $this->entityManager->flush();
            }

            // Get the ID after flush
            if ($entitiesCreated % $batchSize === 0) {
                // Re-query for all entities flushed in this batch to collect IDs
                // (they are already assigned after flush)
            }

            $io->progressAdvance();
        }

        // Final flush
        $this->entityManager->flush();

        // Collect all seed image IDs
        $createdIds = $this->entityManager->getConnection()->fetchFirstColumn(
            "SELECT id FROM images WHERE filename LIKE 'seed_img_%' ORDER BY id"
        );

        $io->progressFinish();

        $io->table(
            ['Metric', 'Value'],
            [
                ['Files created on disk', (string) $filesCreated],
                ['Image entities created', (string) $entitiesCreated],
                ['Skipped (already exist)', (string) $skipped],
                ['Total seed images in DB', (string) count($createdIds)],
            ]
        );

        return array_map('intval', $createdIds);
    }

    /**
     * Create a placeholder JPEG image with colored background and text overlay.
     *
     * @return int|false File size in bytes, or false on failure
     */
    private function createPlaceholderImage(
        string $filePath,
        int $width,
        int $height,
        string $label,
        string $hexColor,
        int $index
    ): int|false {
        $img = imagecreatetruecolor($width, $height);

        if ($img === false) {
            return false;
        }

        // Parse hex color
        $r = hexdec(substr($hexColor, 1, 2));
        $g = hexdec(substr($hexColor, 3, 2));
        $b = hexdec(substr($hexColor, 5, 2));

        // Fill background with the category color
        $bgColor = imagecolorallocate($img, (int) $r, (int) $g, (int) $b);
        imagefill($img, 0, 0, $bgColor);

        // Add a subtle gradient overlay (darker at bottom)
        for ($y = 0; $y < $height; ++$y) {
            $factor = $y / $height;
            $overlayAlpha = (int) (50 * $factor); // 0 to 50
            $overlayColor = imagecolorallocatealpha($img, 0, 0, 0, 127 - $overlayAlpha);
            imageline($img, 0, $y, $width, $y, $overlayColor);
        }

        // Add text using built-in fonts (no TTF dependency)
        $white = imagecolorallocate($img, 255, 255, 255);
        $lightGray = imagecolorallocate($img, 200, 200, 200);

        // Main label (category name) - large built-in font (5 = largest)
        $font = 5;
        $charWidth = imagefontwidth($font);
        $charHeight = imagefontheight($font);

        // Center the category label
        $labelWidth = strlen($label) * $charWidth;
        $labelX = (int) (($width - $labelWidth) / 2);
        $labelY = (int) (($height - $charHeight) / 2 - $charHeight);
        imagestring($img, $font, $labelX, $labelY, $label, $white);

        // Dimension text below
        $dimText = sprintf('%dx%d', $width, $height);
        $dimWidth = strlen($dimText) * $charWidth;
        $dimX = (int) (($width - $dimWidth) / 2);
        $dimY = $labelY + $charHeight + 10;
        imagestring($img, $font, $dimX, $dimY, $dimText, $lightGray);

        // Index number in top-left
        $indexText = sprintf('#%03d', $index);
        imagestring($img, $font, 20, 20, $indexText, $white);

        // "DESCHIDE.MD" watermark in bottom-right
        $watermark = 'DESCHIDE.MD';
        $wmWidth = strlen($watermark) * $charWidth;
        imagestring($img, $font, $width - $wmWidth - 20, $height - $charHeight - 20, $watermark, $lightGray);

        // Add a decorative border
        $borderColor = imagecolorallocate($img, 255, 255, 255);
        imagerectangle($img, 10, 10, $width - 11, $height - 11, $borderColor);

        // Save as JPEG (quality 85)
        $result = imagejpeg($img, $filePath, 85);
        imagedestroy($img);

        if (!$result) {
            return false;
        }

        return filesize($filePath);
    }

    private function linkImagesToArticles(SymfonyStyle $io, int $linkPercent, int $batchSize): void
    {
        $io->section('Phase 2: Link Images to Articles');

        $conn = $this->entityManager->getConnection();

        // Get all article IDs that do NOT already have images
        $articlesWithoutImages = $conn->fetchFirstColumn(
            'SELECT a.id FROM articles a
             WHERE NOT EXISTS (
                 SELECT 1 FROM article_image ai WHERE ai.article_id = a.id
             )
             ORDER BY a.id'
        );

        $totalWithout = count($articlesWithoutImages);
        $io->writeln(sprintf('Articles without images: %d', $totalWithout));

        if ($totalWithout === 0) {
            $io->note('All articles already have images linked.');

            return;
        }

        // Calculate how many to link based on percentage of TOTAL articles
        $totalArticles = (int) $conn->fetchOne('SELECT count(*) FROM articles');
        $targetLinked = (int) ceil($totalArticles * ($linkPercent / 100));
        $alreadyLinked = (int) $conn->fetchOne('SELECT count(DISTINCT article_id) FROM article_image');
        $toLink = max(0, $targetLinked - $alreadyLinked);

        if ($toLink > $totalWithout) {
            $toLink = $totalWithout;
        }

        $io->writeln(sprintf(
            'Target: %d%% of %d articles = %d linked. Already linked: %d. Will link: %d',
            $linkPercent,
            $totalArticles,
            $targetLinked,
            $alreadyLinked,
            $toLink
        ));

        if ($toLink === 0) {
            $io->note('Target link percentage already met.');

            return;
        }

        // Get all available image IDs
        $allImageIds = $conn->fetchFirstColumn('SELECT id FROM images ORDER BY id');

        if (count($allImageIds) === 0) {
            $io->error('No images available to link!');

            return;
        }

        $allImageIds = array_map('intval', $allImageIds);

        // Shuffle articles and take $toLink of them
        shuffle($articlesWithoutImages);
        $articlesToLink = array_slice($articlesWithoutImages, 0, $toLink);

        $io->progressStart($toLink);

        $linksCreated = 0;

        foreach ($articlesToLink as $index => $articleId) {
            $articleId = (int) $articleId;

            // Randomly decide: 1 or 2 images per article (70% get 1, 30% get 2)
            $imageCount = (random_int(1, 10) <= 7) ? 1 : 2;

            // Pick random image IDs
            $selectedImageIds = [];
            $shuffledImages = $allImageIds;
            shuffle($shuffledImages);
            $selectedImageIds = array_slice($shuffledImages, 0, min($imageCount, count($shuffledImages)));

            // Load Article reference (no full load needed)
            $articleRef = $this->entityManager->getReference(Article::class, $articleId);

            foreach ($selectedImageIds as $pos => $imageId) {
                $imageRef = $this->entityManager->getReference(Image::class, $imageId);

                $articleImage = new ArticleImage(
                    article: $articleRef,
                    image: $imageRef,
                    position: $pos,
                    isFeatured: $pos === 0
                );

                $this->entityManager->persist($articleImage);
                ++$linksCreated;
            }

            // Batch flush
            if (($index + 1) % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }

            $io->progressAdvance();
        }

        // Final flush
        $this->entityManager->flush();
        $this->entityManager->clear();

        $io->progressFinish();

        $io->table(
            ['Metric', 'Value'],
            [
                ['Articles linked', (string) count($articlesToLink)],
                ['ArticleImage records created', (string) $linksCreated],
            ]
        );
    }

    private function printSummary(SymfonyStyle $io): void
    {
        $io->section('Final Summary');

        $conn = $this->entityManager->getConnection();

        $totalImages = (int) $conn->fetchOne('SELECT count(*) FROM images');
        $seedImages = (int) $conn->fetchOne("SELECT count(*) FROM images WHERE filename LIKE 'seed_img_%'");
        $totalArticleImages = (int) $conn->fetchOne('SELECT count(*) FROM article_image');
        $totalArticles = (int) $conn->fetchOne('SELECT count(*) FROM articles');
        $articlesWithImages = (int) $conn->fetchOne('SELECT count(DISTINCT article_id) FROM article_image');
        $coveragePercent = $totalArticles > 0 ? round(($articlesWithImages / $totalArticles) * 100, 1) : 0;

        $io->table(
            ['Entity', 'Count', 'Notes'],
            [
                ['Total images', (string) $totalImages, sprintf('%d seed + %d other', $seedImages, $totalImages - $seedImages)],
                ['Total article_image links', (string) $totalArticleImages, ''],
                ['Total articles', (string) $totalArticles, ''],
                ['Articles with images', (string) $articlesWithImages, sprintf('%.1f%% coverage', $coveragePercent)],
            ]
        );

        // Count seed images on disk
        $diskCount = 0;
        if (is_dir($this->uploadsDir)) {
            $files = glob($this->uploadsDir . '/seed_img_*.jpg');
            $diskCount = $files !== false ? count($files) : 0;
        }

        $io->writeln(sprintf('Seed image files on disk: %d', $diskCount));
    }
}
