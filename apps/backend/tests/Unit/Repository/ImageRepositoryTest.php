<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\Image;
use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ImageRepository.
 *
 * All query methods are covered in integration tests.
 */
class ImageRepositoryTest extends TestCase
{
    private ImageRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(Image::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new ImageRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ImageRepository::class, $this->repository);
    }
}
