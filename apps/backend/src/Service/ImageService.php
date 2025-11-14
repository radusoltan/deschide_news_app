<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Image;
use App\Entity\Thumbnail;
use App\Entity\ThumbnailProfile;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class ImageService
{
    private readonly string $storageRoot;

    private readonly string $originalsDir;

    private readonly string $thumbnailsDir;

    private readonly string $publicPath;

    private readonly array $thumbnailFormats;

    private readonly array $qualitySettings;

    private readonly bool $progressive;

    private readonly ImageManager $imageManager;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,
        ParameterBagInterface $params,
    ) {
        $this->storageRoot = $params->get('image.storage.root');
        $this->originalsDir = $params->get('image.storage.originals_dir');
        $this->thumbnailsDir = $params->get('image.storage.thumbnails_dir');
        $this->publicPath = $params->get('image.storage.public_path');
        $this->thumbnailFormats = $params->get('image.thumbnail.formats');
        $this->qualitySettings = $params->get('image.thumbnail.quality');
        $this->progressive = $params->get('image.thumbnail.progressive');

        // Initialize Intervention Image with GD driver
        $this->imageManager = new ImageManager(new GdDriver());
    }

    /**
     * Calculate automatic crop area centered on image.
     * Strategy: Use full image height, calculate width from aspect ratio, center horizontally.
     * If calculated width > image width, use full width and calculate height instead.
     *
     * @param Image $image The source image
     * @param ThumbnailProfile $profile The thumbnail profile with aspect ratio
     *
     * @return array Crop data: ['x' => int, 'y' => int, 'width' => int, 'height' => int]
     */
    public function calculateAutoCrop(Image $image, ThumbnailProfile $profile): array
    {
        $imageWidth = $image->getWidth();
        $imageHeight = $image->getHeight();

        // Parse aspect ratio (e.g., "16:9" -> [16, 9])
        $aspectRatio = $profile->getAspectRatio() ?? $profile->getCalculatedAspectRatio();
        [$arWidth, $arHeight] = array_map('intval', explode(':', $aspectRatio));

        // Calculate crop dimensions
        // Strategy 1: Use full height, calculate width
        $cropHeight = $imageHeight;
        $cropWidth = (int) round($cropHeight * ($arWidth / $arHeight));

        // If crop width exceeds image width, use Strategy 2: full width, calculate height
        if ($cropWidth > $imageWidth) {
            $cropWidth = $imageWidth;
            $cropHeight = (int) round($cropWidth * ($arHeight / $arWidth));
        }

        // Center the crop area
        $cropX = (int) round(($imageWidth - $cropWidth) / 2);
        $cropY = (int) round(($imageHeight - $cropHeight) / 2);

        $this->logger->debug('Calculated auto-crop', [
            'image' => ['width' => $imageWidth, 'height' => $imageHeight],
            'aspectRatio' => $aspectRatio,
            'crop' => ['x' => $cropX, 'y' => $cropY, 'width' => $cropWidth, 'height' => $cropHeight],
        ]);

        return [
            'x' => max(0, $cropX),
            'y' => max(0, $cropY),
            'width' => $cropWidth,
            'height' => $cropHeight,
        ];
    }

    /**
     * Generate a single thumbnail with optional custom crop.
     * If no cropData provided, auto-crop will be calculated and applied.
     *
     * @param Image $image The source image
     * @param ThumbnailProfile $profile The thumbnail profile
     * @param string $format Output format (webp, jpg, png)
     * @param array|null $cropData Custom crop coordinates from react-cropper
     *                             Format: {"x": 10, "y": 20, "width": 300, "height": 200, "unit": "px"}
     *                             or {"p": {"x": 5.5, "y": 10.2, "width": 80.5, "height": 70.3}, "unit": "percent"}
     *                             If null, auto-crop will be calculated
     */
    public function generateThumbnail(
        Image $image,
        ThumbnailProfile $profile,
        string $format = 'webp',
        ?array $cropData = null,
    ): Thumbnail {
        // Calculate auto-crop if no custom crop provided
        if (null === $cropData) {
            $cropData = $this->calculateAutoCrop($image, $profile);
            $this->logger->info('Using auto-crop', [
                'imageId' => $image->getId(),
                'profile' => $profile->getName(),
                'autoCrop' => $cropData,
            ]);
        }

        $this->logger->info('Generating thumbnail', [
            'imageId' => $image->getId(),
            'profile' => $profile->getName(),
            'format' => $format,
            'hasCrop' => $cropData !== null,
        ]);

        // Get absolute path to original image
        $imagePath = $this->storageRoot . '/' . $image->getPath();

        if (!file_exists($imagePath)) {
            throw new RuntimeException(\sprintf('Image file not found: %s', $imagePath));
        }

        // Load image
        $img = $this->imageManager->read($imagePath);

        // Apply crop (either auto-calculated or custom)
        if (null !== $cropData) {
            $this->validateCropData($image, $cropData);
            $absoluteCrop = $this->convertPercentageCrop($image, $cropData);

            $this->logger->debug('Applying crop', [
                'cropData' => $absoluteCrop,
            ]);

            $img->crop(
                $absoluteCrop['width'],
                $absoluteCrop['height'],
                $absoluteCrop['x'],
                $absoluteCrop['y']
            );
        }

        // Resize according to profile
        $mode = $profile->getMode() ?? 'cover';
        $targetWidth = $profile->getWidth();
        $targetHeight = $profile->getHeight();

        switch ($mode) {
            case 'cover':
                $img->cover($targetWidth, $targetHeight);
                break;
            case 'contain':
                $img->contain($targetWidth, $targetHeight);
                break;
            case 'crop':
                $img->crop($targetWidth, $targetHeight);
                break;
            case 'scale':
            default:
                $img->scale($targetWidth, $targetHeight);
                break;
        }

        // Generate thumbnail filename and path
        // Format: {imageId}_{profileName}.{format}
        // Example: 14_article_card.jpg
        $filename = \sprintf('%s_%s.%s', $image->getId(), $profile->getName(), $format);
        $relativePath = \sprintf(
            '%s/%s',
            $this->thumbnailsDir,
            $filename
        );
        $absolutePath = $this->storageRoot . '/' . $relativePath;

        // Ensure directory exists
        $this->ensureDirectory(\dirname($absolutePath));

        // Save thumbnail
        $this->saveThumbnail($img, $absolutePath, $format);

        // Get file size
        $fileSize = filesize($absolutePath);

        // Find or create thumbnail entity
        $thumbnail = $this->entityManager->getRepository(Thumbnail::class)->findOneBy([
            'image' => $image,
            'profile' => $profile,
        ]);

        if (!$thumbnail instanceof Thumbnail) {
            $thumbnail = new Thumbnail();
            $thumbnail->setImage($image)
                ->setProfile($profile);
            $this->entityManager->persist($thumbnail);
        }

        $thumbnail->setFilename($filename)
            ->setPath($relativePath)
            ->setWidth($targetWidth)
            ->setHeight($targetHeight)
            ->setSize($fileSize)
            ->setCropData($cropData);

        $this->entityManager->flush();

        $this->logger->info('Thumbnail generated successfully', [
            'thumbnailId' => $thumbnail->getId(),
            'size' => $fileSize,
        ]);

        return $thumbnail;
    }

    /**
     * Generate all thumbnails for all profiles and formats.
     */
    public function generateAllThumbnails(Image $image): array
    {
        $this->logger->info('Generating all thumbnails', ['imageId' => $image->getId()]);

        $profiles = $this->entityManager->getRepository(ThumbnailProfile::class)->findAll();
        $thumbnails = [];

        foreach ($profiles as $profile) {
            foreach ($this->thumbnailFormats as $format) {
                try {
                    $thumbnail = $this->generateThumbnail($image, $profile, $format);
                    $thumbnails[] = $thumbnail;
                } catch (Exception $e) {
                    $this->logger->error('Failed to generate thumbnail', [
                        'imageId' => $image->getId(),
                        'profile' => $profile->getName(),
                        'format' => $format,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $thumbnails;
    }

    /**
     * Apply custom crop to existing thumbnail (for react-cropper).
     */
    public function applyCrop(
        Thumbnail $thumbnail,
        array $cropData,
    ): Thumbnail {
        $this->logger->info('Applying custom crop to thumbnail', [
            'thumbnailId' => $thumbnail->getId(),
        ]);

        $image = $thumbnail->getImage();
        $profile = $thumbnail->getProfile();

        // Extract format from filename
        $pathInfo = pathinfo($thumbnail->getFilename());
        $format = $pathInfo['extension'] ?? 'webp';

        return $this->generateThumbnail($image, $profile, $format, $cropData);
    }

    /**
     * Get absolute path to image file.
     */
    public function getImagePath(Image $image): string
    {
        return $this->storageRoot . '/' . $image->getPath();
    }

    /**
     * Get public URL for image.
     */
    public function getPublicUrl(Image $image): string
    {
        return $this->publicPath . '/' . $image->getPath();
    }

    /**
     * Get public URL for thumbnail.
     */
    public function getThumbnailUrl(Thumbnail $thumbnail): string
    {
        return $this->publicPath . '/' . $thumbnail->getPath();
    }

    /**
     * Convert percentage crop coordinates to absolute pixels.
     */
    private function convertPercentageCrop(Image $image, array $cropData): array
    {
        // Check if percentage format
        if (isset($cropData['p'])) {
            $p = $cropData['p'];

            return [
                'x' => (int) round($image->getWidth() / 100 * $p['x']),
                'y' => (int) round($image->getHeight() / 100 * $p['y']),
                'width' => (int) round($image->getWidth() / 100 * $p['width']),
                'height' => (int) round($image->getHeight() / 100 * $p['height']),
            ];
        }

        // Already absolute coordinates
        return [
            'x' => (int) $cropData['x'],
            'y' => (int) $cropData['y'],
            'width' => (int) $cropData['width'],
            'height' => (int) $cropData['height'],
        ];
    }

    /**
     * Validate crop data.
     */
    private function validateCropData(Image $image, array $cropData): void
    {
        $absolute = $this->convertPercentageCrop($image, $cropData);

        $required = ['x', 'y', 'width', 'height'];
        foreach ($required as $key) {
            if (!isset($absolute[$key])) {
                throw new InvalidArgumentException(\sprintf('Missing crop coordinate: %s', $key));
            }
        }

        // Validate boundaries
        if ($absolute['x'] < 0 || $absolute['y'] < 0) {
            throw new InvalidArgumentException('Crop coordinates must be positive');
        }

        if ($absolute['width'] <= 0 || $absolute['height'] <= 0) {
            throw new InvalidArgumentException('Crop dimensions must be positive');
        }

        if ($absolute['x'] + $absolute['width'] > $image->getWidth()) {
            throw new InvalidArgumentException('Crop width exceeds image width');
        }

        if ($absolute['y'] + $absolute['height'] > $image->getHeight()) {
            throw new InvalidArgumentException('Crop height exceeds image height');
        }
    }

    /**
     * Save thumbnail with optimal settings per format.
     */
    private function saveThumbnail(ImageInterface $img, string $path, string $format): void
    {
        $quality = $this->qualitySettings[$format] ?? 85;

        match (strtolower($format)) {
            'jpg', 'jpeg' => $img->toJpeg($quality)->save($path),
            'webp' => $img->toWebp($quality)->save($path),
            'png' => $img->toPng()->save($path),
            default => throw new InvalidArgumentException(\sprintf('Unsupported format: %s', $format)),
        };
    }

    /**
     * Ensure directory exists.
     */
    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            $this->filesystem->mkdir($path, 0o755);
        }
    }
}
