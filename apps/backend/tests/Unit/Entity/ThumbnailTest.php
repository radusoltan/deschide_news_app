<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Image;
use App\Entity\Thumbnail;
use App\Entity\ThumbnailProfile;
use PHPUnit\Framework\TestCase;

class ThumbnailTest extends TestCase
{
    private Thumbnail $thumbnail;

    protected function setUp(): void
    {
        $this->thumbnail = new Thumbnail();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->thumbnail->getId());
        $this->assertNull($this->thumbnail->getFilename());
        $this->assertNull($this->thumbnail->getPath());
        $this->assertNull($this->thumbnail->getWidth());
        $this->assertNull($this->thumbnail->getHeight());
        $this->assertNull($this->thumbnail->getSize());
        $this->assertNull($this->thumbnail->getImage());
        $this->assertNull($this->thumbnail->getProfile());
        $this->assertNull($this->thumbnail->getCreatedAt());
        $this->assertNull($this->thumbnail->getCropData());
    }

    public function testSetGetFilename(): void
    {
        $result = $this->thumbnail->setFilename('thumb_123.webp');
        $this->assertSame('thumb_123.webp', $this->thumbnail->getFilename());
        $this->assertSame($this->thumbnail, $result);
    }

    public function testSetGetPath(): void
    {
        $result = $this->thumbnail->setPath('thumbnails/hero_big/thumb_123.webp');
        $this->assertSame('thumbnails/hero_big/thumb_123.webp', $this->thumbnail->getPath());
        $this->assertSame($this->thumbnail, $result);
    }

    public function testSetGetWidth(): void
    {
        $result = $this->thumbnail->setWidth(1920);
        $this->assertSame(1920, $this->thumbnail->getWidth());
        $this->assertSame($this->thumbnail, $result);
    }

    public function testSetGetHeight(): void
    {
        $result = $this->thumbnail->setHeight(1080);
        $this->assertSame(1080, $this->thumbnail->getHeight());
        $this->assertSame($this->thumbnail, $result);
    }

    public function testSetGetSize(): void
    {
        $result = $this->thumbnail->setSize(50000);
        $this->assertSame(50000, $this->thumbnail->getSize());
        $this->assertSame($this->thumbnail, $result);
    }

    public function testSetGetImage(): void
    {
        $image = new Image();
        $result = $this->thumbnail->setImage($image);
        $this->assertSame($image, $this->thumbnail->getImage());
        $this->assertSame($this->thumbnail, $result);
    }

    public function testSetGetImageNull(): void
    {
        $image = new Image();
        $this->thumbnail->setImage($image);
        $this->thumbnail->setImage(null);
        $this->assertNull($this->thumbnail->getImage());
    }

    public function testSetGetProfile(): void
    {
        $profile = new ThumbnailProfile();
        $result = $this->thumbnail->setProfile($profile);
        $this->assertSame($profile, $this->thumbnail->getProfile());
        $this->assertSame($this->thumbnail, $result);
    }

    public function testSetGetCropData(): void
    {
        $crop = ['x' => 10, 'y' => 20, 'width' => 300, 'height' => 200, 'unit' => 'px'];
        $result = $this->thumbnail->setCropData($crop);
        $this->assertSame($crop, $this->thumbnail->getCropData());
        $this->assertSame($this->thumbnail, $result);
    }

    public function testSetGetCropDataNull(): void
    {
        $this->thumbnail->setCropData(['x' => 0]);
        $this->thumbnail->setCropData(null);
        $this->assertNull($this->thumbnail->getCropData());
    }

    public function testGetUrl(): void
    {
        $this->thumbnail->setPath('thumbnails/hero_big/image.webp');
        $this->assertSame('/media/thumbnails/hero_big/image.webp', $this->thumbnail->getUrl());
    }

    public function testGetUrlWithNullPath(): void
    {
        $this->assertSame('/media/', $this->thumbnail->getUrl());
    }

    public function testGetFormattedSizeBytes(): void
    {
        $this->thumbnail->setSize(500);
        $this->assertSame('500 B', $this->thumbnail->getFormattedSize());
    }

    public function testGetFormattedSizeKilobytes(): void
    {
        $this->thumbnail->setSize(2048);
        $this->assertSame('2 KB', $this->thumbnail->getFormattedSize());
    }

    public function testGetFormattedSizeMegabytes(): void
    {
        $this->thumbnail->setSize(1048576);
        $this->assertSame('1 MB', $this->thumbnail->getFormattedSize());
    }

    public function testGetFormattedSizeNullSize(): void
    {
        $this->assertSame('0 B', $this->thumbnail->getFormattedSize());
    }

    public function testGetAspectRatio(): void
    {
        $this->thumbnail->setWidth(1920);
        $this->thumbnail->setHeight(1080);
        $this->assertSame(1.78, $this->thumbnail->getAspectRatio());
    }

    public function testGetAspectRatioSquare(): void
    {
        $this->thumbnail->setWidth(500);
        $this->thumbnail->setHeight(500);
        $this->assertSame(1.0, $this->thumbnail->getAspectRatio());
    }

    public function testGetAspectRatioZeroHeight(): void
    {
        $this->thumbnail->setWidth(100);
        $this->thumbnail->setHeight(0);
        $this->assertSame(0.0, $this->thumbnail->getAspectRatio());
    }

    public function testGetAspectRatioNullHeight(): void
    {
        $this->thumbnail->setWidth(100);
        $this->assertSame(0.0, $this->thumbnail->getAspectRatio());
    }
}
