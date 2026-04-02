<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\VideoShow;
use App\Entity\YouTubeVideo;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class VideoShowTest extends TestCase
{
    private VideoShow $show;

    protected function setUp(): void
    {
        $this->show = new VideoShow();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->show->getId());
        $this->assertSame('', $this->show->getName());
        $this->assertSame('', $this->show->getSlug());
        $this->assertNull($this->show->getDescription());
        $this->assertNull($this->show->getYoutubePlaylistId());
        $this->assertNull($this->show->getYoutubeChannelId());
        $this->assertNull($this->show->getThumbnailUrl());
        $this->assertNull($this->show->getColor());
        $this->assertTrue($this->show->isActive());
        $this->assertSame(0, $this->show->getPosition());
        $this->assertInstanceOf(Collection::class, $this->show->getVideos());
        $this->assertCount(0, $this->show->getVideos());
    }

    public function testConstructorSetsTimestamps(): void
    {
        $this->assertInstanceOf(DateTimeImmutable::class, $this->show->getCreatedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $this->show->getUpdatedAt());
    }

    public function testSetGetName(): void
    {
        $result = $this->show->setName('Deschide LIVE');
        $this->assertSame('Deschide LIVE', $this->show->getName());
        $this->assertSame($this->show, $result);
    }

    public function testSetGetSlug(): void
    {
        $result = $this->show->setSlug('deschide-live');
        $this->assertSame('deschide-live', $this->show->getSlug());
        $this->assertSame($this->show, $result);
    }

    public function testSetGetDescription(): void
    {
        $result = $this->show->setDescription('Live video show');
        $this->assertSame('Live video show', $this->show->getDescription());
        $this->assertSame($this->show, $result);
    }

    public function testSetGetDescriptionNull(): void
    {
        $this->show->setDescription('test');
        $this->show->setDescription(null);
        $this->assertNull($this->show->getDescription());
    }

    public function testSetGetYoutubePlaylistId(): void
    {
        $result = $this->show->setYoutubePlaylistId('PLxxxxxxx');
        $this->assertSame('PLxxxxxxx', $this->show->getYoutubePlaylistId());
        $this->assertSame($this->show, $result);
    }

    public function testSetGetYoutubeChannelId(): void
    {
        $result = $this->show->setYoutubeChannelId('UCxxxxxxx');
        $this->assertSame('UCxxxxxxx', $this->show->getYoutubeChannelId());
        $this->assertSame($this->show, $result);
    }

    public function testSetGetThumbnailUrl(): void
    {
        $result = $this->show->setThumbnailUrl('https://example.com/thumb.jpg');
        $this->assertSame('https://example.com/thumb.jpg', $this->show->getThumbnailUrl());
        $this->assertSame($this->show, $result);
    }

    public function testSetGetColor(): void
    {
        $result = $this->show->setColor('#FF0000');
        $this->assertSame('#FF0000', $this->show->getColor());
        $this->assertSame($this->show, $result);
    }

    public function testSetGetIsActive(): void
    {
        $result = $this->show->setIsActive(false);
        $this->assertFalse($this->show->isActive());
        $this->assertSame($this->show, $result);
    }

    public function testSetGetPosition(): void
    {
        $result = $this->show->setPosition(3);
        $this->assertSame(3, $this->show->getPosition());
        $this->assertSame($this->show, $result);
    }

    public function testSetTranslatableLocale(): void
    {
        $result = $this->show->setTranslatableLocale('ro');
        $this->assertSame($this->show, $result);
    }

    public function testAddVideo(): void
    {
        $video = new YouTubeVideo();
        $result = $this->show->addVideo($video);
        $this->assertCount(1, $this->show->getVideos());
        $this->assertTrue($this->show->getVideos()->contains($video));
        $this->assertSame($this->show, $video->getVideoShow());
        $this->assertSame($this->show, $result);
    }

    public function testAddVideoDoesNotDuplicate(): void
    {
        $video = new YouTubeVideo();
        $this->show->addVideo($video);
        $this->show->addVideo($video);
        $this->assertCount(1, $this->show->getVideos());
    }

    public function testRemoveVideo(): void
    {
        $video = new YouTubeVideo();
        $this->show->addVideo($video);
        $result = $this->show->removeVideo($video);
        $this->assertCount(0, $this->show->getVideos());
        $this->assertNull($video->getVideoShow());
        $this->assertSame($this->show, $result);
    }

    public function testGetVideosCount(): void
    {
        $this->assertSame(0, $this->show->getVideosCount());
        $this->show->addVideo(new YouTubeVideo());
        $this->assertSame(1, $this->show->getVideosCount());
        $video2 = new YouTubeVideo();
        $this->show->addVideo($video2);
        $this->assertSame(2, $this->show->getVideosCount());
    }

    public function testOnPreUpdate(): void
    {
        $oldUpdated = $this->show->getUpdatedAt();
        usleep(1000);
        $this->show->onPreUpdate();
        $this->assertGreaterThanOrEqual($oldUpdated, $this->show->getUpdatedAt());
    }
}
