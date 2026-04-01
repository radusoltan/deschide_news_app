<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\Thumbnail;
use App\Repository\ThumbnailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ThumbnailRepository.
 *
 * All query methods are covered in integration tests.
 */
class ThumbnailRepositoryTest extends TestCase
{
    private ThumbnailRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(Thumbnail::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new ThumbnailRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ThumbnailRepository::class, $this->repository);
    }
}
