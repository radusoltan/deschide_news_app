<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Image;
use App\Entity\ThumbnailProfile;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Unit tests for ImageService.
 *
 * Tests image processing logic, crop calculations, and validation
 * according to Symfony and API Platform testing best practices.
 */
class ImageServiceTest extends TestCase
{
    private ImageService $service;

    private EntityManagerInterface $entityManager;

    private LoggerInterface $logger;

    private Filesystem $filesystem;

    private ParameterBagInterface $params;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->params = $this->createMock(ParameterBagInterface::class);

        // Setup default parameters
        $this->params->method('get')->willReturnMap([
            ['image.storage.root', '/var/www/uploads'],
            ['image.storage.originals_dir', 'images'],
            ['image.storage.thumbnails_dir', 'thumbnails'],
            ['image.storage.public_path', '/uploads'],
            ['image.thumbnail.formats', ['webp', 'jpg']],
            ['image.thumbnail.quality', ['webp' => 85, 'jpg' => 90, 'png' => 100]],
            ['image.thumbnail.progressive', true],
        ]);

        $this->service = new ImageService(
            $this->entityManager,
            $this->logger,
            $this->filesystem,
            $this->params
        );
    }

    // ======================
    // calculateAutoCrop Tests
    // ======================

    #[Test]
    public function itCalculatesAutoCropForLandscapeImageWith169Ratio(): void
    {
        $image = $this->createImage(1920, 1080); // 16:9 landscape
        $profile = $this->createProfile('hero', 1600, 900, '16:9');

        $crop = $this->service->calculateAutoCrop($image, $profile);

        $this->assertEquals(0, $crop['x']); // Centered horizontally
        $this->assertEquals(0, $crop['y']); // No vertical offset
        $this->assertEquals(1920, $crop['width']); // Full width
        $this->assertEquals(1080, $crop['height']); // Full height
    }

    #[Test]
    public function itCalculatesAutoCropForPortraitImageWith43Ratio(): void
    {
        $image = $this->createImage(800, 1200); // Portrait image
        $profile = $this->createProfile('card', 400, 300, '4:3');

        $crop = $this->service->calculateAutoCrop($image, $profile);

        // Should use full width (800), calculate height for 4:3 ratio
        $expectedHeight = (int) round(800 * (3 / 4)); // = 600
        $expectedY = (int) round((1200 - $expectedHeight) / 2); // = 300

        $this->assertEquals(0, $crop['x']);
        $this->assertEquals($expectedY, $crop['y']);
        $this->assertEquals(800, $crop['width']);
        $this->assertEquals($expectedHeight, $crop['height']);
    }

    #[Test]
    public function itCalculatesAutoCropForSquareImageWith11Ratio(): void
    {
        $image = $this->createImage(1000, 1000); // Square
        $profile = $this->createProfile('avatar', 200, 200, '1:1');

        $crop = $this->service->calculateAutoCrop($image, $profile);

        $this->assertEquals(0, $crop['x']);
        $this->assertEquals(0, $crop['y']);
        $this->assertEquals(1000, $crop['width']);
        $this->assertEquals(1000, $crop['height']);
    }

    #[Test]
    public function itCentersCropHorizontallyWhenCroppingWidth(): void
    {
        $image = $this->createImage(2000, 1000); // Wide landscape
        $profile = $this->createProfile('hero', 1600, 900, '16:9');

        $crop = $this->service->calculateAutoCrop($image, $profile);

        // Should use full height (1000), calculate width for 16:9
        $expectedWidth = (int) round(1000 * (16 / 9)); // = 1778
        $expectedX = (int) round((2000 - $expectedWidth) / 2); // = 111

        $this->assertEquals($expectedX, $crop['x']);
        $this->assertEquals(0, $crop['y']);
        $this->assertEquals($expectedWidth, $crop['width']);
        $this->assertEquals(1000, $crop['height']);
    }

    #[Test]
    public function itCentersCropVerticallyWhenCroppingHeight(): void
    {
        $image = $this->createImage(800, 1600); // Tall portrait
        $profile = $this->createProfile('banner', 800, 200, '4:1');

        $crop = $this->service->calculateAutoCrop($image, $profile);

        // Should use full width (800), calculate height for 4:1
        $expectedHeight = (int) round(800 * (1 / 4)); // = 200
        $expectedY = (int) round((1600 - $expectedHeight) / 2); // = 700

        $this->assertEquals(0, $crop['x']);
        $this->assertEquals($expectedY, $crop['y']);
        $this->assertEquals(800, $crop['width']);
        $this->assertEquals($expectedHeight, $crop['height']);
    }

    #[Test]
    public function itEnsuresCropCoordinatesAreNonNegative(): void
    {
        $image = $this->createImage(100, 100); // Small image
        $profile = $this->createProfile('thumbnail', 80, 80, '1:1');

        $crop = $this->service->calculateAutoCrop($image, $profile);

        $this->assertGreaterThanOrEqual(0, $crop['x']);
        $this->assertGreaterThanOrEqual(0, $crop['y']);
        $this->assertGreaterThan(0, $crop['width']);
        $this->assertGreaterThan(0, $crop['height']);
    }

    // ======================
    // URL Generation Tests
    // ======================

    #[Test]
    public function itGeneratesCorrectImagePath(): void
    {
        $image = $this->createImage(800, 600);
        $image->setPath('images/test-image.jpg');

        $path = $this->service->getImagePath($image);

        $this->assertEquals('/var/www/uploads/images/test-image.jpg', $path);
    }

    #[Test]
    public function itGeneratesCorrectPublicUrlForImage(): void
    {
        $image = $this->createImage(800, 600);
        $image->setPath('images/test-image.jpg');

        $url = $this->service->getPublicUrl($image);

        $this->assertEquals('/uploads/images/test-image.jpg', $url);
    }

    #[Test]
    public function itGeneratesCorrectPublicUrlForThumbnail(): void
    {
        $thumbnail = $this->createMock(\App\Entity\Thumbnail::class);
        $thumbnail->method('getPath')->willReturn('thumbnails/hero_big/image_123.webp');

        $url = $this->service->getThumbnailUrl($thumbnail);

        $this->assertEquals('/uploads/thumbnails/hero_big/image_123.webp', $url);
    }

    // ======================
    // Crop Validation Tests (via Reflection)
    // ======================

    #[Test]
    public function itValidatesCorrectAbsoluteCropData(): void
    {
        $image = $this->createImage(800, 600);
        $cropData = ['x' => 100, 'y' => 50, 'width' => 400, 'height' => 300];

        // Use reflection to call private method
        $result = $this->callPrivateMethod('validateCropData', [$image, $cropData]);

        // If no exception thrown, validation passed
        $this->assertTrue(true);
    }

    #[Test]
    public function itValidatesCorrectPercentageCropData(): void
    {
        $image = $this->createImage(800, 600);
        $cropData = [
            'p' => ['x' => 10, 'y' => 10, 'width' => 50, 'height' => 50],
            'unit' => 'percent',
        ];

        $result = $this->callPrivateMethod('validateCropData', [$image, $cropData]);

        // If no exception thrown, validation passed
        $this->assertTrue(true);
    }

    #[Test]
    public function itRejectsNegativeCropCoordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Crop coordinates must be positive');

        $image = $this->createImage(800, 600);
        $cropData = ['x' => -10, 'y' => 50, 'width' => 400, 'height' => 300];

        $this->callPrivateMethod('validateCropData', [$image, $cropData]);
    }

    #[Test]
    public function itRejectsZeroOrNegativeCropDimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Crop dimensions must be positive');

        $image = $this->createImage(800, 600);
        $cropData = ['x' => 100, 'y' => 50, 'width' => 0, 'height' => 300];

        $this->callPrivateMethod('validateCropData', [$image, $cropData]);
    }

    #[Test]
    public function itRejectsCropExceedingImageWidth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Crop width exceeds image width');

        $image = $this->createImage(800, 600);
        $cropData = ['x' => 600, 'y' => 50, 'width' => 400, 'height' => 300]; // 600 + 400 > 800

        $this->callPrivateMethod('validateCropData', [$image, $cropData]);
    }

    #[Test]
    public function itRejectsCropExceedingImageHeight(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Crop height exceeds image height');

        $image = $this->createImage(800, 600);
        $cropData = ['x' => 100, 'y' => 400, 'width' => 400, 'height' => 300]; // 400 + 300 > 600

        $this->callPrivateMethod('validateCropData', [$image, $cropData]);
    }

    // ======================
    // Percentage Conversion Tests (via Reflection)
    // ======================

    #[Test]
    public function itConvertsPercentageCropToAbsolutePixels(): void
    {
        $image = $this->createImage(1000, 800);
        $cropData = [
            'p' => ['x' => 10, 'y' => 20, 'width' => 50, 'height' => 60],
            'unit' => 'percent',
        ];

        $absolute = $this->callPrivateMethod('convertPercentageCrop', [$image, $cropData]);

        $this->assertEquals(100, $absolute['x']); // 10% of 1000
        $this->assertEquals(160, $absolute['y']); // 20% of 800
        $this->assertEquals(500, $absolute['width']); // 50% of 1000
        $this->assertEquals(480, $absolute['height']); // 60% of 800
    }

    #[Test]
    public function itReturnsAbsoluteCropUnchanged(): void
    {
        $image = $this->createImage(800, 600);
        $cropData = ['x' => 100, 'y' => 50, 'width' => 400, 'height' => 300];

        $absolute = $this->callPrivateMethod('convertPercentageCrop', [$image, $cropData]);

        $this->assertEquals(100, $absolute['x']);
        $this->assertEquals(50, $absolute['y']);
        $this->assertEquals(400, $absolute['width']);
        $this->assertEquals(300, $absolute['height']);
    }

    #[Test]
    #[DataProvider('aspectRatioProvider')]
    public function itHandlesVariousAspectRatios(string $ratio, int $imageWidth, int $imageHeight): void
    {
        $image = $this->createImage($imageWidth, $imageHeight);
        $profile = $this->createProfile('test', 800, 600, $ratio);

        $crop = $this->service->calculateAutoCrop($image, $profile);

        // Verify crop stays within image bounds
        $this->assertLessThanOrEqual($imageWidth, $crop['x'] + $crop['width']);
        $this->assertLessThanOrEqual($imageHeight, $crop['y'] + $crop['height']);
        $this->assertGreaterThanOrEqual(0, $crop['x']);
        $this->assertGreaterThanOrEqual(0, $crop['y']);
    }

    public static function aspectRatioProvider(): Generator
    {
        yield '16:9 landscape' => ['16:9', 1920, 1080];
        yield '4:3 standard' => ['4:3', 1600, 1200];
        yield '1:1 square' => ['1:1', 1000, 1000];
        yield '9:16 portrait' => ['9:16', 1080, 1920];
        yield '21:9 ultrawide' => ['21:9', 2560, 1080];
        yield '3:2 photo' => ['3:2', 1500, 1000];
    }

    // ======================
    // Helper Methods
    // ======================

    private function createImage(int $width, int $height): Image
    {
        $image = new Image();
        $image->setWidth($width);
        $image->setHeight($height);
        $image->setFilename('test-image.jpg');
        $image->setPath('images/test-image.jpg');
        $image->setMimeType('image/jpeg');

        return $image;
    }

    private function createProfile(string $name, int $width, int $height, string $aspectRatio): ThumbnailProfile
    {
        $profile = new ThumbnailProfile();
        $profile->setName($name);
        $profile->setWidth($width);
        $profile->setHeight($height);
        $profile->setAspectRatio($aspectRatio);

        return $profile;
    }

    /**
     * Call private/protected method using reflection.
     */
    private function callPrivateMethod(string $methodName, array $args = []): mixed
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->service, $args);
    }
}
