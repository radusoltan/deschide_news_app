<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Thumbnail;
use App\State\ThumbnailProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ThumbnailProviderTest extends TestCase
{
    private ThumbnailProvider $provider;
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->repository = $this->createStub(EntityRepository::class);

        $this->entityManager->method('getRepository')
            ->with(Thumbnail::class)
            ->willReturn($this->repository);

        $this->provider = new ThumbnailProvider($this->entityManager);
    }

    #[Test]
    public function itRetrievesSingleThumbnailById(): void
    {
        $thumbnail = new Thumbnail();

        $this->repository->method('find')->with(1)->willReturn($thumbnail);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(Thumbnail::class, $result);
    }

    #[Test]
    public function itReturnsNullWhenThumbnailNotFound(): void
    {
        $this->repository->method('find')->with(999)->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itRetrievesCollectionOrderedByCreatedAtDesc(): void
    {
        $thumbnail1 = new Thumbnail();
        $thumbnail2 = new Thumbnail();

        $this->repository->method('findBy')
            ->with([], ['createdAt' => 'DESC'])
            ->willReturn([$thumbnail1, $thumbnail2]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function itReturnsEmptyCollectionWhenNoThumbnails(): void
    {
        $this->repository->method('findBy')
            ->with([], ['createdAt' => 'DESC'])
            ->willReturn([]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
