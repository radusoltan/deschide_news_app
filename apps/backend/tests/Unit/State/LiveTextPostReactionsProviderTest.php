<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use App\Entity\LiveTextPost;
use App\Repository\LiveTextPostRepository;
use App\Repository\LiveTextReactionRepository;
use App\State\LiveTextPostReactionsProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LiveTextPostReactionsProviderTest extends TestCase
{
    private LiveTextPostReactionsProvider $provider;
    private LiveTextPostRepository $postRepository;
    private LiveTextReactionRepository $reactionRepository;

    protected function setUp(): void
    {
        $this->postRepository = $this->createStub(LiveTextPostRepository::class);
        $this->reactionRepository = $this->createStub(LiveTextReactionRepository::class);

        $this->provider = new LiveTextPostReactionsProvider(
            $this->postRepository,
            $this->reactionRepository
        );
    }

    #[Test]
    public function itReturnsReactionStatsForExistingPost(): void
    {
        $post = $this->createStub(LiveTextPost::class);
        $this->postRepository->method('find')->with(1)->willReturn($post);

        $this->reactionRepository->method('getReactionCountsByPost')
            ->with($post)
            ->willReturn(['like' => 10, 'love' => 5, 'wow' => 2]);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertIsObject($result);
        $this->assertEquals(1, $result->postId);
        $this->assertEquals(17, $result->total);
        $this->assertEquals(['like' => 10, 'love' => 5, 'wow' => 2], $result->counts);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoPostId(): void
    {
        $operation = new Get();
        $result = $this->provider->provide($operation, []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function itReturnsNullWhenPostNotFound(): void
    {
        $this->postRepository->method('find')->with(999)->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itReturnsZeroTotalWhenNoReactions(): void
    {
        $post = $this->createStub(LiveTextPost::class);
        $this->postRepository->method('find')->with(1)->willReturn($post);

        $this->reactionRepository->method('getReactionCountsByPost')
            ->with($post)
            ->willReturn([]);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertEquals(0, $result->total);
        $this->assertEmpty($result->counts);
    }
}
