<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\ArticleLock;
use App\Repository\ArticleLockRepository;
use App\State\ArticleLockProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ArticleLockProviderTest extends TestCase
{
    private ArticleLockProvider $provider;
    private ArticleLockRepository $lockRepository;

    protected function setUp(): void
    {
        $this->lockRepository = $this->createStub(ArticleLockRepository::class);
        $this->provider = new ArticleLockProvider($this->lockRepository);
    }

    #[Test]
    public function itRetrievesSingleLockById(): void
    {
        $lock = new ArticleLock();

        $this->lockRepository->method('find')->with(1)->willReturn($lock);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(ArticleLock::class, $result);
    }

    #[Test]
    public function itReturnsNullWhenLockNotFound(): void
    {
        $this->lockRepository->method('find')->with(999)->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itRetrievesAllLocks(): void
    {
        $lock1 = new ArticleLock();
        $lock2 = new ArticleLock();

        $this->lockRepository->method('findAll')->willReturn([$lock1, $lock2]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoLocks(): void
    {
        $this->lockRepository->method('findAll')->willReturn([]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
