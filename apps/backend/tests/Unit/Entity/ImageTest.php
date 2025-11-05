<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Image;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Image entity.
 *
 * Tests entity creation, getters, setters, relationships, and business logic
 * according to Symfony and API Platform testing best practices.
 */
class ImageTest extends TestCase
{
    public function testImageCreation(): void
    {
        $image = new Image();

        $this->assertNull($image->getId());
        $this->assertInstanceOf(Image::class, $image);
    }

    public function testSetAndGetFilename(): void
    {
        $image = new Image();
        $filename = 'test-image_12345.jpg';

        $result = $image->setFilename($filename);

        $this->assertSame($image, $result); // Test fluent interface
        $this->assertEquals($filename, $image->getFilename());
    }

    public function testSetAndGetOriginalFilename(): void
    {
        $image = new Image();
        $originalFilename = 'my-photo.jpg';

        $result = $image->setOriginalFilename($originalFilename);

        $this->assertSame($image, $result);
        $this->assertEquals($originalFilename, $image->getOriginalFilename());
    }

    public function testSetAndGetPath(): void
    {
        $image = new Image();
        $path = 'images/test-image_12345.jpg';

        $result = $image->setPath($path);

        $this->assertSame($image, $result);
        $this->assertEquals($path, $image->getPath());
    }

    public function testPathCanBeNull(): void
    {
        $image = new Image();

        $image->setPath(null);

        $this->assertNull($image->getPath());
    }

    public function testSetAndGetMimeType(): void
    {
        $image = new Image();
        $mimeType = 'image/jpeg';

        $result = $image->setMimeType($mimeType);

        $this->assertSame($image, $result);
        $this->assertEquals($mimeType, $image->getMimeType());
    }

    public function testMimeTypeCanBeNull(): void
    {
        $image = new Image();

        $image->setMimeType(null);

        $this->assertNull($image->getMimeType());
    }

    public function testSetAndGetSize(): void
    {
        $image = new Image();
        $size = 1024000; // 1MB in bytes

        $result = $image->setSize($size);

        $this->assertSame($image, $result);
        $this->assertEquals($size, $image->getSize());
    }

    public function testSizeCanBeNull(): void
    {
        $image = new Image();

        $image->setSize(null);

        $this->assertNull($image->getSize());
    }

    public function testSetAndGetWidth(): void
    {
        $image = new Image();
        $width = 1920;

        $result = $image->setWidth($width);

        $this->assertSame($image, $result);
        $this->assertEquals($width, $image->getWidth());
    }

    public function testSetAndGetHeight(): void
    {
        $image = new Image();
        $height = 1080;

        $result = $image->setHeight($height);

        $this->assertSame($image, $result);
        $this->assertEquals($height, $image->getHeight());
    }

    // Translatable Fields Tests

    public function testSetAndGetAlt(): void
    {
        $image = new Image();
        $alt = 'Beautiful landscape photo';

        $result = $image->setAlt($alt);

        $this->assertSame($image, $result);
        $this->assertEquals($alt, $image->getAlt());
    }

    public function testAltCanBeNull(): void
    {
        $image = new Image();

        $image->setAlt(null);

        $this->assertNull($image->getAlt());
    }

    public function testSetAndGetCaption(): void
    {
        $image = new Image();
        $caption = 'Photo taken in the mountains during sunrise';

        $result = $image->setCaption($caption);

        $this->assertSame($image, $result);
        $this->assertEquals($caption, $image->getCaption());
    }

    public function testCaptionCanBeNull(): void
    {
        $image = new Image();

        $image->setCaption(null);

        $this->assertNull($image->getCaption());
    }

    public function testSetAndGetDescription(): void
    {
        $image = new Image();
        $description = 'A detailed description of the image content and context.';

        $result = $image->setDescription($description);

        $this->assertSame($image, $result);
        $this->assertEquals($description, $image->getDescription());
    }

    public function testDescriptionCanBeNull(): void
    {
        $image = new Image();

        $image->setDescription(null);

        $this->assertNull($image->getDescription());
    }

    public function testSetAndGetImageAuthor(): void
    {
        $image = new Image();
        $author = 'John Photographer';

        $result = $image->setImageAuthor($author);

        $this->assertSame($image, $result);
        $this->assertEquals($author, $image->getImageAuthor());
    }

    public function testImageAuthorCanBeNull(): void
    {
        $image = new Image();

        $image->setImageAuthor(null);

        $this->assertNull($image->getImageAuthor());
    }

    // Timestamp Tests

    public function testCreatedAtTimestamp(): void
    {
        $image = new Image();

        // Initially null (will be set by Gedmo on persist)
        $this->assertNull($image->getCreatedAt());
    }

    public function testUpdatedAtTimestamp(): void
    {
        $image = new Image();

        // Initially null (will be set by Gedmo on persist/update)
        $this->assertNull($image->getUpdatedAt());
    }

    // Translatable Tests

    public function testTranslatableLocale(): void
    {
        $image = new Image();

        $this->assertNull($image->getLocale());

        $image->setTranslatableLocale('ro');
        $this->assertEquals('ro', $image->getLocale());

        $image->setTranslatableLocale('en');
        $this->assertEquals('en', $image->getLocale());

        $image->setTranslatableLocale('ru');
        $this->assertEquals('ru', $image->getLocale());
    }

    // Thumbnails Collection Tests

    public function testThumbnailsCollectionInitialization(): void
    {
        $image = new Image();

        // Collection should be initialized and empty
        $this->assertCount(0, $image->getThumbnails());
        $this->assertInstanceOf(Collection::class, $image->getThumbnails());
    }

    // Note: Image entity doesn't have addThumbnail/removeThumbnail methods.
    // Thumbnails are managed by the owning side (Thumbnail entity with setImage method).

    // Max Length Tests

    public function testAltMaxLength(): void
    {
        $image = new Image();
        $longAlt = str_repeat('a', 255);

        $image->setAlt($longAlt);

        $this->assertEquals(255, \strlen($image->getAlt()));
    }

    public function testCaptionMaxLength(): void
    {
        $image = new Image();
        $longCaption = str_repeat('a', 500);

        $image->setCaption($longCaption);

        $this->assertEquals(500, \strlen($image->getCaption()));
    }

    // Fluent Interface Test

    public function testFluentInterface(): void
    {
        $image = new Image();

        $result = $image
            ->setFilename('test.jpg')
            ->setOriginalFilename('original-test.jpg')
            ->setPath('images/test.jpg')
            ->setMimeType('image/jpeg')
            ->setSize(1024000)
            ->setWidth(1920)
            ->setHeight(1080)
            ->setAlt('Test image')
            ->setCaption('Test caption')
            ->setDescription('Test description')
            ->setImageAuthor('Test Author');

        $this->assertSame($image, $result);
        $this->assertEquals('test.jpg', $image->getFilename());
        $this->assertEquals('original-test.jpg', $image->getOriginalFilename());
        $this->assertEquals('images/test.jpg', $image->getPath());
        $this->assertEquals('image/jpeg', $image->getMimeType());
        $this->assertEquals(1024000, $image->getSize());
        $this->assertEquals(1920, $image->getWidth());
        $this->assertEquals(1080, $image->getHeight());
        $this->assertEquals('Test image', $image->getAlt());
        $this->assertEquals('Test caption', $image->getCaption());
        $this->assertEquals('Test description', $image->getDescription());
        $this->assertEquals('Test Author', $image->getImageAuthor());
    }

    // Aspect Ratio Tests

    public function testGetAspectRatio(): void
    {
        $image = new Image();
        $image->setWidth(1920);
        $image->setHeight(1080);

        // 1920/1080 = 1.777...
        $aspectRatio = $image->getAspectRatio();
        $this->assertIsFloat($aspectRatio);
        $this->assertEqualsWithDelta(1.777, $aspectRatio, 0.01);
    }

    public function testGetAspectRatioWithZeroHeight(): void
    {
        $image = new Image();
        $image->setWidth(1920);
        $image->setHeight(0);

        // Should return 0 or handle division by zero gracefully
        $aspectRatio = $image->getAspectRatio();
        $this->assertEquals(0, $aspectRatio);
    }

    // File Size Formatting Tests (if implemented)

    public function testGetFormattedSize(): void
    {
        $image = new Image();

        // Test various sizes
        $image->setSize(1024); // 1 KB
        $formattedSize = $image->getFormattedSize();
        $this->assertStringContainsString('KB', $formattedSize);

        $image->setSize(1024 * 1024); // 1 MB
        $formattedSize = $image->getFormattedSize();
        $this->assertStringContainsString('MB', $formattedSize);
    }

    public function testGetFormattedSizeWithNullSize(): void
    {
        $image = new Image();
        $image->setSize(null);

        $formattedSize = $image->getFormattedSize();
        $this->assertEquals('0 B', $formattedSize);
    }
}
