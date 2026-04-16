<?php

declare(strict_types=1);

namespace App\Command\Fixtures;

use App\Entity\ArticleImage;
use App\Entity\Image;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Service\RemoteImageDownloader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Downloads images for the top N articles per category and creates
 * SVG placeholder images for the rest. For app:dev:reset use only.
 */
#[AsCommand(
    name: 'app:fixtures:download-images',
    description: 'Download images for top N articles per category + SVG placeholders for the rest',
)]
final class DownloadImagesCommand extends Command
{
    /** Category slug → brand color for placeholder SVGs */
    private const CATEGORY_COLORS = [
        'politica' => '#1a365d',
        'societate' => '#2d3748',
        'externe' => '#285e61',
        'economie' => '#744210',
        'romania' => '#1e3a5f',
        'cultura' => '#553c9a',
        'sport' => '#22543d',
        'editoriale' => '#702459',
        'opinii' => '#4a5568',
        'advertorial' => '#975a16',
        'anti-fake' => '#9b2c2c',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepo,
        private readonly ArticleRepository $articleRepo,
        private readonly RemoteImageDownloader $imageDownloader,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('top-per-category', null, InputOption::VALUE_REQUIRED, 'Number of articles to download real images for', '5')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Re-download even if ArticleImage already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $topN = (int) $input->getOption('top-per-category');

        $io->title("Download images (top {$topN}/category) + generate SVG placeholders");

        // Ensure placeholder directory exists
        $placeholderDir = $this->projectDir . '/public/uploads/placeholders';
        if (!is_dir($placeholderDir)) {
            mkdir($placeholderDir, 0755, true);
        }

        // Generate SVG placeholders per category
        $placeholderImages = $this->generatePlaceholders($placeholderDir);
        $io->text('  Generated ' . \count($placeholderImages) . ' SVG placeholders');

        $categories = $this->categoryRepo->findAll();
        $downloaded = 0;
        $placeholders = 0;
        $failed = 0;

        foreach ($categories as $category) {
            $slug = $category->getSlug();

            // Get published articles for this category, ordered by most recent
            $conn = $this->em->getConnection();
            $articleIds = $conn->fetchFirstColumn(
                "SELECT a.id FROM articles a
                 WHERE a.category_id = ? AND a.status = 'published'
                 ORDER BY a.published_at DESC NULLS LAST, a.id DESC",
                [$category->getId()],
            );

            foreach ($articleIds as $index => $articleId) {
                $article = $this->articleRepo->find($articleId);
                if ($article === null) {
                    continue;
                }

                // Skip if article already has images
                if ($article->getArticleImages()->count() > 0 && !$input->getOption('force')) {
                    continue;
                }

                if ($index < $topN) {
                    // Top N: try to download real image from legacy URL
                    $imageUrl = $conn->fetchOne(
                        "SELECT metadata->>'image_url' FROM external_article_mapping WHERE article_id = ?",
                        [$articleId],
                    );

                    if ($imageUrl !== false && $imageUrl !== null && $imageUrl !== '') {
                        $image = $this->imageDownloader->download($imageUrl);
                        if ($image !== null) {
                            $this->em->persist($image);
                            $articleImage = new ArticleImage($article, $image, 0, true);
                            $this->em->persist($articleImage);
                            $this->em->flush();
                            $downloaded++;
                            continue;
                        }
                    }
                    $failed++;
                }

                // Use placeholder for non-top or failed downloads
                $placeholderImage = $placeholderImages[$slug] ?? null;
                if ($placeholderImage !== null) {
                    $articleImage = new ArticleImage($article, $placeholderImage, 0, false);
                    $this->em->persist($articleImage);
                    $placeholders++;

                    // Batch flush every 100 placeholders
                    if ($placeholders % 100 === 0) {
                        $this->em->flush();
                        $io->text("  Placeholders progress: {$placeholders}...");
                    }
                }
            }
        }

        $this->em->flush();
        $io->success("Done: {$downloaded} downloaded, {$placeholders} placeholders, {$failed} download failures");

        return Command::SUCCESS;
    }

    /**
     * Generate one SVG placeholder per category and return Image entities.
     *
     * @return array<string, Image> slug → Image entity
     */
    private function generatePlaceholders(string $dir): array
    {
        $images = [];

        foreach (self::CATEGORY_COLORS as $slug => $color) {
            $svgPath = "{$dir}/category-{$slug}.svg";
            $label = strtoupper($slug);

            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450">
  <rect width="800" height="450" fill="{$color}"/>
  <text x="400" y="225" font-family="sans-serif" font-size="32" fill="white" text-anchor="middle" dominant-baseline="central">{$label}</text>
</svg>
SVG;

            file_put_contents($svgPath, $svg);

            $image = new Image();
            $image->setFilename("category-{$slug}.svg");
            $image->setPath("/uploads/placeholders/category-{$slug}.svg");
            $this->em->persist($image);

            $images[$slug] = $image;
        }

        $this->em->flush();

        return $images;
    }
}
