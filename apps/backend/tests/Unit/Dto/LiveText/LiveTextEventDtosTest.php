<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\LiveText;

use App\Dto\LiveText\PostDeletedEventDto;
use App\Dto\LiveText\PostUpdatedEventDto;
use App\Dto\LiveText\StatusChangedEventDto;
use App\Dto\LiveText\ViewersCountEventDto;
use App\Entity\LiveText;
use App\Entity\LiveTextPost;
use App\Entity\User;
use App\Enum\LiveTextStatus;
use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LiveTextEventDtosTest extends TestCase
{
    // ========================
    // PostDeletedEventDto
    // ========================

    #[Test]
    public function postDeletedDtoConstructsCorrectly(): void
    {
        $timestamp = new DateTime('2026-03-20 12:00:00');
        $dto = new PostDeletedEventDto(5, 42, $timestamp);

        $this->assertSame('post.deleted', $dto->type);
        $this->assertSame(5, $dto->liveTextId);
        $this->assertSame(42, $dto->postId);
        $this->assertSame($timestamp, $dto->timestamp);
    }

    #[Test]
    public function postDeletedDtoToArray(): void
    {
        $timestamp = new DateTime('2026-03-20 12:00:00');
        $dto = new PostDeletedEventDto(5, 42, $timestamp);

        $array = $dto->toArray();

        $this->assertSame('post.deleted', $array['type']);
        $this->assertSame(5, $array['liveTextId']);
        $this->assertSame(42, $array['postId']);
        $this->assertSame($timestamp->format(DateTimeInterface::ATOM), $array['timestamp']);
    }

    // ========================
    // PostUpdatedEventDto
    // ========================

    #[Test]
    public function postUpdatedDtoConstructsCorrectly(): void
    {
        $timestamp = new DateTime('2026-03-20 14:00:00');
        $dto = new PostUpdatedEventDto(
            liveTextId: 10,
            postId: 55,
            content: 'Updated content',
            contentHtml: '<p>Updated content</p>',
            isKeyPoint: true,
            position: 3,
            timestamp: $timestamp
        );

        $this->assertSame('post.updated', $dto->type);
        $this->assertSame(10, $dto->liveTextId);
        $this->assertSame(55, $dto->postId);
        $this->assertSame('Updated content', $dto->content);
        $this->assertSame('<p>Updated content</p>', $dto->contentHtml);
        $this->assertTrue($dto->isKeyPoint);
        $this->assertSame(3, $dto->position);
    }

    #[Test]
    public function postUpdatedDtoToArray(): void
    {
        $timestamp = new DateTime('2026-03-20 14:00:00');
        $dto = new PostUpdatedEventDto(10, 55, 'Updated', '<p>Updated</p>', false, 1, $timestamp);

        $array = $dto->toArray();

        $this->assertSame('post.updated', $array['type']);
        $this->assertSame(10, $array['liveTextId']);
        $this->assertSame(55, $array['post']['id']);
        $this->assertSame('Updated', $array['post']['content']);
        $this->assertSame('<p>Updated</p>', $array['post']['contentHtml']);
        $this->assertFalse($array['post']['isKeyPoint']);
        $this->assertSame(1, $array['post']['position']);
    }

    #[Test]
    public function postUpdatedDtoFromEntity(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(7);

        $user = $this->createStub(User::class);

        $post = $this->createStub(LiveTextPost::class);
        $post->method('getId')->willReturn(33);
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getContent')->willReturn('Match update');
        $post->method('getContentHtml')->willReturn('<p>Match update</p>');
        $post->method('isKeyPoint')->willReturn(false);
        $post->method('getPosition')->willReturn(2);
        $post->method('getAuthor')->willReturn($user);

        $dto = PostUpdatedEventDto::fromEntity($post);

        $this->assertSame('post.updated', $dto->type);
        $this->assertSame(7, $dto->liveTextId);
        $this->assertSame(33, $dto->postId);
        $this->assertSame('Match update', $dto->content);
        $this->assertSame('<p>Match update</p>', $dto->contentHtml);
    }

    #[Test]
    public function postUpdatedDtoFromEntityWithNullHtml(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);

        $post = $this->createStub(LiveTextPost::class);
        $post->method('getId')->willReturn(1);
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getContent')->willReturn('Text');
        $post->method('getContentHtml')->willReturn(null);
        $post->method('isKeyPoint')->willReturn(false);
        $post->method('getPosition')->willReturn(0);
        $post->method('getAuthor')->willReturn($this->createStub(User::class));

        $dto = PostUpdatedEventDto::fromEntity($post);

        $this->assertSame('', $dto->contentHtml);
    }

    // ========================
    // StatusChangedEventDto
    // ========================

    #[Test]
    public function statusChangedDtoConstructsCorrectly(): void
    {
        $timestamp = new DateTime('2026-03-20 16:00:00');
        $dto = new StatusChangedEventDto(3, 'live', 'Breaking News', $timestamp);

        $this->assertSame('status.changed', $dto->type);
        $this->assertSame(3, $dto->liveTextId);
        $this->assertSame('live', $dto->status);
        $this->assertSame('Breaking News', $dto->title);
    }

    #[Test]
    public function statusChangedDtoToArray(): void
    {
        $timestamp = new DateTime('2026-03-20 16:00:00');
        $dto = new StatusChangedEventDto(3, 'ended', 'Match Over', $timestamp);

        $array = $dto->toArray();

        $this->assertSame('status.changed', $array['type']);
        $this->assertSame(3, $array['liveTextId']);
        $this->assertSame('ended', $array['status']);
        $this->assertSame('Match Over', $array['title']);
        $this->assertSame($timestamp->format(DateTimeInterface::ATOM), $array['timestamp']);
    }

    #[Test]
    public function statusChangedDtoFromEntity(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(15);
        $liveText->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $liveText->method('getTitle')->willReturn('Live Coverage');

        $dto = StatusChangedEventDto::fromEntity($liveText);

        $this->assertSame('status.changed', $dto->type);
        $this->assertSame(15, $dto->liveTextId);
        $this->assertSame('live', $dto->status);
        $this->assertSame('Live Coverage', $dto->title);
    }

    // ========================
    // ViewersCountEventDto
    // ========================

    #[Test]
    public function viewersCountDtoConstructsCorrectly(): void
    {
        $timestamp = new DateTime('2026-03-20 18:00:00');
        $dto = new ViewersCountEventDto(8, 150, $timestamp);

        $this->assertSame('viewers.count', $dto->type);
        $this->assertSame(8, $dto->liveTextId);
        $this->assertSame(150, $dto->count);
    }

    #[Test]
    public function viewersCountDtoToArray(): void
    {
        $timestamp = new DateTime('2026-03-20 18:00:00');
        $dto = new ViewersCountEventDto(8, 250, $timestamp);

        $array = $dto->toArray();

        $this->assertSame('viewers.count', $array['type']);
        $this->assertSame(8, $array['liveTextId']);
        $this->assertSame(250, $array['count']);
        $this->assertSame($timestamp->format(DateTimeInterface::ATOM), $array['timestamp']);
    }

    #[Test]
    public function viewersCountDtoHandlesZeroViewers(): void
    {
        $dto = new ViewersCountEventDto(1, 0, new DateTime());

        $array = $dto->toArray();
        $this->assertSame(0, $array['count']);
    }
}
