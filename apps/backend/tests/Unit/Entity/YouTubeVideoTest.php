<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\VideoShow;
use App\Entity\YouTubeVideo;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class YouTubeVideoTest extends TestCase
{
    private YouTubeVideo $video;

    protected function setUp(): void
    {
        $this->video = new YouTubeVideo();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->video->getId());
        $this->assertSame('', $this->video->getYoutubeId());
        $this->assertNull($this->video->getVideoShow());
        $this->assertSame('', $this->video->getTitle());
        $this->assertNull($this->video->getDescription());
        $this->assertNull($this->video->getThumbnailUrl());
        $this->assertNull($this->video->getThumbnailMedium());
        $this->assertNull($this->video->getDurationSeconds());
        $this->assertNull($this->video->getPublishedAt());
        $this->assertSame(0, $this->video->getViewCount());
        $this->assertSame(0, $this->video->getLikeCount());
        $this->assertFalse($this->video->isFeatured());
        $this->assertFalse($this->video->isHidden());
        $this->assertNull($this->video->getPosition());
        $this->assertNull($this->video->getSyncedAt());
    }

    public function testConstructorSetsTimestamps(): void
    {
        $this->assertInstanceOf(DateTimeImmutable::class, $this->video->getCreatedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $this->video->getUpdatedAt());
    }

    public function testSetGetYoutubeId(): void
    {
        $result = $this->video->setYoutubeId('dQw4w9WgXcQ');
        $this->assertSame('dQw4w9WgXcQ', $this->video->getYoutubeId());
        $this->assertSame($this->video, $result);
    }

    public function testSetGetVideoShow(): void
    {
        $show = new VideoShow();
        $result = $this->video->setVideoShow($show);
        $this->assertSame($show, $this->video->getVideoShow());
        $this->assertSame($this->video, $result);
    }

    public function testSetGetVideoShowNull(): void
    {
        $show = new VideoShow();
        $this->video->setVideoShow($show);
        $this->video->setVideoShow(null);
        $this->assertNull($this->video->getVideoShow());
    }

    public function testSetGetTitle(): void
    {
        $result = $this->video->setTitle('Test Video Title');
        $this->assertSame('Test Video Title', $this->video->getTitle());
        $this->assertSame($this->video, $result);
    }

    public function testSetGetDescription(): void
    {
        $result = $this->video->setDescription('Video description');
        $this->assertSame('Video description', $this->video->getDescription());
        $this->assertSame($this->video, $result);
    }

    public function testSetGetDescriptionNull(): void
    {
        $this->video->setDescription('test');
        $this->video->setDescription(null);
        $this->assertNull($this->video->getDescription());
    }

    public function testSetGetThumbnailUrl(): void
    {
        $result = $this->video->setThumbnailUrl('https://img.youtube.com/vi/abc/maxresdefault.jpg');
        $this->assertSame('https://img.youtube.com/vi/abc/maxresdefault.jpg', $this->video->getThumbnailUrl());
        $this->assertSame($this->video, $result);
    }

    public function testSetGetThumbnailMedium(): void
    {
        $result = $this->video->setThumbnailMedium('https://img.youtube.com/vi/abc/mqdefault.jpg');
        $this->assertSame('https://img.youtube.com/vi/abc/mqdefault.jpg', $this->video->getThumbnailMedium());
        $this->assertSame($this->video, $result);
    }

    public function testSetGetDurationSeconds(): void
    {
        $result = $this->video->setDurationSeconds(3600);
        $this->assertSame(3600, $this->video->getDurationSeconds());
        $this->assertSame($this->video, $result);
    }

    public function testSetGetPublishedAt(): void
    {
        $date = new DateTimeImmutable('2024-06-15');
        $result = $this->video->setPublishedAt($date);
        $this->assertSame($date, $this->video->getPublishedAt());
        $this->assertSame($this->video, $result);
    }

    public function testSetGetViewCount(): void
    {
        $result = $this->video->setViewCount(1000000);
        $this->assertSame(1000000, $this->video->getViewCount());
        $this->assertSame($this->video, $result);
    }

    public function testSetGetLikeCount(): void
    {
        $result = $this->video->setLikeCount(50000);
        $this->assertSame(50000, $this->video->getLikeCount());
        $this->assertSame($this->video, $result);
    }

    public function testSetGetIsFeatured(): void
    {
        $result = $this->video->setIsFeatured(true);
        $this->assertTrue($this->video->isFeatured());
        $this->assertSame($this->video, $result);
    }

    public function testSetGetIsHidden(): void
    {
        $result = $this->video->setIsHidden(true);
        $this->assertTrue($this->video->isHidden());
        $this->assertSame($this->video, $result);
    }

    public function testSetGetPosition(): void
    {
        $result = $this->video->setPosition(5);
        $this->assertSame(5, $this->video->getPosition());
        $this->assertSame($this->video, $result);
    }

    public function testSetGetSyncedAt(): void
    {
        $date = new DateTimeImmutable('2024-06-15 10:00:00');
        $result = $this->video->setSyncedAt($date);
        $this->assertSame($date, $this->video->getSyncedAt());
        $this->assertSame($this->video, $result);
    }

    public function testSetTranslatableLocale(): void
    {
        $result = $this->video->setTranslatableLocale('en');
        $this->assertSame($this->video, $result);
    }

    public function testGetDurationFormattedNull(): void
    {
        $this->assertNull($this->video->getDurationFormatted());
    }

    public function testGetDurationFormattedMinutesOnly(): void
    {
        $this->video->setDurationSeconds(150); // 2:30
        $this->assertSame('2:30', $this->video->getDurationFormatted());
    }

    public function testGetDurationFormattedWithHours(): void
    {
        $this->video->setDurationSeconds(5445); // 1:30:45
        $this->assertSame('1:30:45', $this->video->getDurationFormatted());
    }

    public function testGetDurationFormattedZeroSeconds(): void
    {
        $this->video->setDurationSeconds(0);
        $this->assertSame('0:00', $this->video->getDurationFormatted());
    }

    public function testGetDurationFormattedExactHour(): void
    {
        $this->video->setDurationSeconds(3600);
        $this->assertSame('1:00:00', $this->video->getDurationFormatted());
    }

    public function testGetYoutubeUrl(): void
    {
        $this->video->setYoutubeId('dQw4w9WgXcQ');
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $this->video->getYoutubeUrl());
    }

    public function testGetEmbedUrl(): void
    {
        $this->video->setYoutubeId('dQw4w9WgXcQ');
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $this->video->getEmbedUrl());
    }

    public function testOnPreUpdate(): void
    {
        $oldUpdated = $this->video->getUpdatedAt();
        usleep(1000);
        $this->video->onPreUpdate();
        $this->assertGreaterThanOrEqual($oldUpdated, $this->video->getUpdatedAt());
    }
}
