<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ThumbnailProfile;
use App\Enum\ThumbnailCategory;
use App\Enum\ThumbnailMode;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class ThumbnailProfileTest extends TestCase
{
    private ThumbnailProfile $profile;

    protected function setUp(): void
    {
        $this->profile = new ThumbnailProfile();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->profile->getId());
        $this->assertNull($this->profile->getName());
        $this->assertNull($this->profile->getDisplayName());
        $this->assertNull($this->profile->getDescription());
        $this->assertNull($this->profile->getWidth());
        $this->assertNull($this->profile->getHeight());
        $this->assertNull($this->profile->getAspectRatio());
        $this->assertSame(ThumbnailMode::CROP, $this->profile->getMode());
        $this->assertSame(85, $this->profile->getQuality());
        $this->assertTrue($this->profile->isActive());
        $this->assertSame(ThumbnailCategory::GENERAL, $this->profile->getCategory());
        $this->assertNull($this->profile->getCreatedAt());
        $this->assertNull($this->profile->getUpdatedAt());
        $this->assertNull($this->profile->getLocale());
        $this->assertInstanceOf(Collection::class, $this->profile->getThumbnails());
        $this->assertCount(0, $this->profile->getThumbnails());
    }

    public function testSetGetName(): void
    {
        $result = $this->profile->setName('hero_big');
        $this->assertSame('hero_big', $this->profile->getName());
        $this->assertSame($this->profile, $result);
    }

    public function testSetGetDisplayName(): void
    {
        $result = $this->profile->setDisplayName('Hero Big');
        $this->assertSame('Hero Big', $this->profile->getDisplayName());
        $this->assertSame($this->profile, $result);
    }

    public function testSetGetDescription(): void
    {
        $result = $this->profile->setDescription('Hero section main image');
        $this->assertSame('Hero section main image', $this->profile->getDescription());
        $this->assertSame($this->profile, $result);
    }

    public function testSetGetDescriptionNull(): void
    {
        $this->profile->setDescription('test');
        $this->profile->setDescription(null);
        $this->assertNull($this->profile->getDescription());
    }

    public function testSetGetWidth(): void
    {
        $result = $this->profile->setWidth(1920);
        $this->assertSame(1920, $this->profile->getWidth());
        $this->assertSame($this->profile, $result);
    }

    public function testSetGetHeight(): void
    {
        $result = $this->profile->setHeight(1080);
        $this->assertSame(1080, $this->profile->getHeight());
        $this->assertSame($this->profile, $result);
    }

    public function testSetGetAspectRatio(): void
    {
        $result = $this->profile->setAspectRatio('16:9');
        $this->assertSame('16:9', $this->profile->getAspectRatio());
        $this->assertSame($this->profile, $result);
    }

    public function testSetGetMode(): void
    {
        $result = $this->profile->setMode(ThumbnailMode::CONTAIN);
        $this->assertSame(ThumbnailMode::CONTAIN, $this->profile->getMode());
        $this->assertSame($this->profile, $result);
    }

    public function testSetGetModeAllValues(): void
    {
        foreach (ThumbnailMode::cases() as $mode) {
            $this->profile->setMode($mode);
            $this->assertSame($mode, $this->profile->getMode());
        }
    }

    public function testSetGetQuality(): void
    {
        $result = $this->profile->setQuality(95);
        $this->assertSame(95, $this->profile->getQuality());
        $this->assertSame($this->profile, $result);
    }

    public function testSetGetIsActive(): void
    {
        $result = $this->profile->setIsActive(false);
        $this->assertFalse($this->profile->isActive());
        $this->assertSame($this->profile, $result);
    }

    public function testSetGetCategory(): void
    {
        $result = $this->profile->setCategory(ThumbnailCategory::ARTICLE);
        $this->assertSame(ThumbnailCategory::ARTICLE, $this->profile->getCategory());
        $this->assertSame($this->profile, $result);
    }

    public function testSetGetCategoryAllValues(): void
    {
        foreach (ThumbnailCategory::cases() as $category) {
            $this->profile->setCategory($category);
            $this->assertSame($category, $this->profile->getCategory());
        }
    }

    public function testSetTranslatableLocale(): void
    {
        $this->profile->setTranslatableLocale('en');
        $this->assertSame('en', $this->profile->getLocale());
    }

    public function testGetCalculatedAspectRatioWithExplicitRatio(): void
    {
        $this->profile->setAspectRatio('16:9');
        $this->profile->setWidth(1920);
        $this->profile->setHeight(1080);
        $this->assertSame('16:9', $this->profile->getCalculatedAspectRatio());
    }

    public function testGetCalculatedAspectRatioFromDimensions(): void
    {
        $this->profile->setWidth(1920);
        $this->profile->setHeight(1080);
        $this->assertSame('16:9', $this->profile->getCalculatedAspectRatio());
    }

    public function testGetCalculatedAspectRatioSquare(): void
    {
        $this->profile->setWidth(500);
        $this->profile->setHeight(500);
        $this->assertSame('1:1', $this->profile->getCalculatedAspectRatio());
    }

    public function testGetCalculatedAspectRatioNoDimensions(): void
    {
        $this->assertSame('0:0', $this->profile->getCalculatedAspectRatio());
    }

    public function testGetDimensionsLabel(): void
    {
        $this->profile->setWidth(1920);
        $this->profile->setHeight(1080);
        $this->assertSame('1920x1080 (16:9)', $this->profile->getDimensionsLabel());
    }

    public function testGetDimensionsLabelNullDimensions(): void
    {
        $this->assertSame('0x0 (0:0)', $this->profile->getDimensionsLabel());
    }
}
