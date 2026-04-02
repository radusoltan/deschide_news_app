<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\LiveText;

use App\Dto\LiveText\PostCreatedEventDto;
use App\Entity\LiveText;
use App\Entity\LiveTextPost;
use App\Entity\User;
use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PostCreatedEventDtoTest extends TestCase
{
    #[Test]
    public function itConstructsWithAllParameters(): void
    {
        $timestamp = new DateTime('2026-01-15 10:30:00');
        $publishedAt = new DateTime('2026-01-15 10:00:00');

        $dto = new PostCreatedEventDto(
            liveTextId: 5,
            postId: 42,
            content: 'Test content',
            contentHtml: '<p>Test content</p>',
            author: ['id' => 1, 'username' => 'admin', 'email' => 'admin@test.com'],
            isKeyPoint: true,
            position: 3,
            publishedAt: $publishedAt,
            timestamp: $timestamp
        );

        $this->assertSame('post.created', $dto->type);
        $this->assertSame(5, $dto->liveTextId);
        $this->assertSame(42, $dto->postId);
        $this->assertSame('Test content', $dto->content);
        $this->assertSame('<p>Test content</p>', $dto->contentHtml);
        $this->assertSame(['id' => 1, 'username' => 'admin', 'email' => 'admin@test.com'], $dto->author);
        $this->assertTrue($dto->isKeyPoint);
        $this->assertSame(3, $dto->position);
        $this->assertSame($publishedAt, $dto->publishedAt);
        $this->assertSame($timestamp, $dto->timestamp);
    }

    #[Test]
    public function itConvertsToArray(): void
    {
        $timestamp = new DateTime('2026-01-15 10:30:00');
        $publishedAt = new DateTime('2026-01-15 10:00:00');

        $dto = new PostCreatedEventDto(
            liveTextId: 5,
            postId: 42,
            content: 'Breaking news',
            contentHtml: '<strong>Breaking news</strong>',
            author: ['id' => 1, 'username' => 'reporter', 'email' => 'reporter@test.com'],
            isKeyPoint: false,
            position: 1,
            publishedAt: $publishedAt,
            timestamp: $timestamp
        );

        $array = $dto->toArray();

        $this->assertSame('post.created', $array['type']);
        $this->assertSame(5, $array['liveTextId']);
        $this->assertSame($timestamp->format(DateTimeInterface::ATOM), $array['timestamp']);
        $this->assertSame(42, $array['post']['id']);
        $this->assertSame('Breaking news', $array['post']['content']);
        $this->assertSame('<strong>Breaking news</strong>', $array['post']['contentHtml']);
        $this->assertSame(['id' => 1, 'username' => 'reporter', 'email' => 'reporter@test.com'], $array['post']['author']);
        $this->assertFalse($array['post']['isKeyPoint']);
        $this->assertSame(1, $array['post']['position']);
        $this->assertSame($publishedAt->format(DateTimeInterface::ATOM), $array['post']['publishedAt']);
    }

    #[Test]
    public function itCreatesFromEntity(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(7);
        $user->method('getUsername')->willReturn('journalist');
        $user->method('getEmail')->willReturn('journalist@example.com');

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(10);

        $publishedAt = new DateTime('2026-03-15 08:00:00');

        $post = $this->createStub(LiveTextPost::class);
        $post->method('getId')->willReturn(100);
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getContent')->willReturn('Match update');
        $post->method('getContentHtml')->willReturn('<p>Match update</p>');
        $post->method('getAuthor')->willReturn($user);
        $post->method('isKeyPoint')->willReturn(true);
        $post->method('getPosition')->willReturn(5);
        $post->method('getPublishedAt')->willReturn($publishedAt);

        $dto = PostCreatedEventDto::fromEntity($post);

        $this->assertSame('post.created', $dto->type);
        $this->assertSame(10, $dto->liveTextId);
        $this->assertSame(100, $dto->postId);
        $this->assertSame('Match update', $dto->content);
        $this->assertSame('<p>Match update</p>', $dto->contentHtml);
        $this->assertSame(['id' => 7, 'username' => 'journalist', 'email' => 'journalist@example.com'], $dto->author);
        $this->assertTrue($dto->isKeyPoint);
        $this->assertSame(5, $dto->position);
        $this->assertSame($publishedAt, $dto->publishedAt);
    }

    #[Test]
    public function itCreatesFromEntityWithNullContentHtml(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getUsername')->willReturn('admin');
        $user->method('getEmail')->willReturn('admin@test.com');

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);

        $post = $this->createStub(LiveTextPost::class);
        $post->method('getId')->willReturn(1);
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getContent')->willReturn('Text only');
        $post->method('getContentHtml')->willReturn(null);
        $post->method('getAuthor')->willReturn($user);
        $post->method('isKeyPoint')->willReturn(false);
        $post->method('getPosition')->willReturn(0);
        $post->method('getPublishedAt')->willReturn(new DateTime());

        $dto = PostCreatedEventDto::fromEntity($post);

        $this->assertSame('', $dto->contentHtml);
    }

    #[Test]
    public function itToArrayContainsAllRequiredKeys(): void
    {
        $dto = new PostCreatedEventDto(
            liveTextId: 1,
            postId: 1,
            content: 'c',
            contentHtml: '<p>c</p>',
            author: ['id' => 1],
            isKeyPoint: false,
            position: 0,
            publishedAt: new DateTime(),
            timestamp: new DateTime()
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('liveTextId', $array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('post', $array);
        $this->assertArrayHasKey('id', $array['post']);
        $this->assertArrayHasKey('content', $array['post']);
        $this->assertArrayHasKey('contentHtml', $array['post']);
        $this->assertArrayHasKey('author', $array['post']);
        $this->assertArrayHasKey('isKeyPoint', $array['post']);
        $this->assertArrayHasKey('position', $array['post']);
        $this->assertArrayHasKey('publishedAt', $array['post']);
    }
}
