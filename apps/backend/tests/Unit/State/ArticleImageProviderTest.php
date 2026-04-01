<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\ArticleImage;
use App\State\ArticleImageProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ArticleImageProviderTest extends TestCase
{
    private ArticleImageProvider $provider;
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->repository = $this->createStub(EntityRepository::class);

        $this->entityManager->method('getRepository')
            ->with(ArticleImage::class)
            ->willReturn($this->repository);

        $this->provider = new ArticleImageProvider($this->entityManager);
    }

    #[Test]
    public function itRetrievesSingleArticleImageById(): void
    {
        $articleImage = new ArticleImage();

        $this->repository->method('find')->with(1)->willReturn($articleImage);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(ArticleImage::class, $result);
    }

    #[Test]
    public function itReturnsNullWhenArticleImageNotFound(): void
    {
        $this->repository->method('find')->with(999)->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itRetrievesCollectionOrderedByPosition(): void
    {
        $articleImage1 = new ArticleImage();
        $articleImage2 = new ArticleImage();

        $this->repository->method('findBy')
            ->with([], ['position' => 'ASC'])
            ->willReturn([$articleImage1, $articleImage2]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function itFiltersCollectionByArticleId(): void
    {
        $articleImage = new ArticleImage();

        $this->repository->method('findBy')
            ->with(['article' => '5'], ['position' => 'ASC'])
            ->willReturn([$articleImage]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation, [], ['filters' => ['article.id' => '5']]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function itReturnsEmptyCollectionWithNoFilters(): void
    {
        $this->repository->method('findBy')
            ->with([], ['position' => 'ASC'])
            ->willReturn([]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
