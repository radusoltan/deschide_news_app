<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ArticleImage;
use App\Entity\Image;
use App\Repository\ExternalArticleMappingRepository;
use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Phase 4: Downloads images from external CDNs (Webflow, Supabase) and creates
 * Image + ArticleImage entities for imported articles.
 */
class LegacyImageDownloader
{
    private const MAX_RETRIES = 3;
    private const RATE_LIMIT_DELAY_MS = 100; // 100ms between requests = ~10/sec
    private const REQUEST_TIMEOUT = 30;

    /** @var array{downloaded: int, failed: int, skipped: int} */
    private array $stats = ['downloaded' => 0, 'failed' => 0, 'skipped' => 0];

    /** @var string[] */
    private array $failedUrls = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExternalArticleMappingRepository $mappingRepository,
        private readonly ImageRepository $imageRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Download images for all imported articles.
     *
     * @param bool $dryRun If true, count but don't download
     * @param int  $batchSize Flush every N images
     */
    public function downloadAll(bool $dryRun = false, int $batchSize = 50): void
    {
        $mappings = $this->mappingRepository->findBySource(LegacyArticleImporter::SOURCE_KEY);
        $uploadDir = $this->projectDir . '/public/uploads/images/originals/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0o755, true);
        }

        $processed = 0;

        foreach ($mappings as $mapping) {
            $article = $mapping->getArticle();
            $metadata = $mapping->getMetadata();
            $imageUrl = $metadata['main_image_url'] ?? '';

            if (empty($imageUrl) || $article === null) {
                continue;
            }

            // Skip if article already has images
            if ($article->getArticleImages()->count() > 0) {
                ++$this->stats['skipped'];
                continue;
            }

            if ($dryRun) {
                ++$this->stats['downloaded'];
                continue;
            }

            $image = $this->downloadImage($imageUrl, $uploadDir);

            if ($image !== null) {
                $articleImage = new ArticleImage($article, $image, 0, true);
                $this->entityManager->persist($articleImage);
                ++$this->stats['downloaded'];
            } else {
                ++$this->stats['failed'];
            }

            ++$processed;

            if ($processed % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }

            // Rate limiting
            usleep(self::RATE_LIMIT_DELAY_MS * 1000);

            // Progress log every 100
            if ($processed % 100 === 0) {
                $this->logger->info('Image download progress: {count} processed', ['count' => $processed]);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Download a single image, create Image entity.
     */
    private function downloadImage(string $url, string $uploadDir): ?Image
    {
        // Check if already downloaded (by originalFilename)
        $originalFilename = $this->extractFilename($url);
        $existing = $this->imageRepository->findOneBy(['originalFilename' => $originalFilename]);
        if ($existing !== null) {
            return $existing;
        }

        $content = $this->fetchWithRetry($url);
        if ($content === null) {
            return null;
        }

        // Determine extension from content
        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($content);
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $filename = sprintf('legacy_%s.%s', bin2hex(random_bytes(12)), $extension);
        $filePath = $uploadDir . $filename;

        file_put_contents($filePath, $content);

        // Get dimensions
        $imageInfo = @getimagesize($filePath);
        $width = $imageInfo[0] ?? 0;
        $height = $imageInfo[1] ?? 0;

        if ($width === 0 || $height === 0) {
            // Invalid image — remove and skip
            @unlink($filePath);
            $this->logger->warning('Invalid image (0 dimensions): {url}', ['url' => $url]);

            return null;
        }

        $image = new Image();
        $image->setFilename($filename);
        $image->setOriginalFilename($originalFilename);
        $image->setPath('images/originals/' . $filename);
        $image->setMimeType($mimeType);
        $image->setSize(\strlen($content));
        $image->setWidth($width);
        $image->setHeight($height);

        $this->entityManager->persist($image);

        return $image;
    }

    /**
     * Fetch URL content with exponential backoff retry.
     */
    private function fetchWithRetry(string $url): ?string
    {
        $delays = [1, 3, 9]; // seconds

        for ($attempt = 0; $attempt < self::MAX_RETRIES; ++$attempt) {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'timeout' => self::REQUEST_TIMEOUT,
                    'max_redirects' => 5,
                ]);

                if ($response->getStatusCode() === 200) {
                    return $response->getContent();
                }

                $this->logger->warning('Image download HTTP {status}: {url}', [
                    'status' => $response->getStatusCode(),
                    'url' => $url,
                ]);
            } catch (\Exception $e) {
                $this->logger->warning('Image download attempt {attempt} failed: {url} — {error}', [
                    'attempt' => $attempt + 1,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($attempt < self::MAX_RETRIES - 1) {
                sleep($delays[$attempt]);
            }
        }

        $this->failedUrls[] = $url;

        return null;
    }

    private function extractFilename(string $url): string
    {
        $filename = basename(parse_url($url, \PHP_URL_PATH) ?: 'unknown.jpg');

        // Truncate if too long
        if (\strlen($filename) > 255) {
            $ext = pathinfo($filename, \PATHINFO_EXTENSION);
            $filename = substr($filename, 0, 250 - \strlen($ext)) . '.' . $ext;
        }

        return $filename;
    }

    /**
     * @return array{downloaded: int, failed: int, skipped: int}
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * @return string[]
     */
    public function getFailedUrls(): array
    {
        return $this->failedUrls;
    }

    public function resetStats(): void
    {
        $this->stats = ['downloaded' => 0, 'failed' => 0, 'skipped' => 0];
        $this->failedUrls = [];
    }
}
