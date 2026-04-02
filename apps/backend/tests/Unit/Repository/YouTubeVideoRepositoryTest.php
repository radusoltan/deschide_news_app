<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\YouTubeVideo;
use App\Repository\YouTubeVideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for YouTubeVideoRepository.
 *
 * All query methods are covered in integration tests.
 */
class YouTubeVideoRepositoryTest extends TestCase
{
    private YouTubeVideoRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(YouTubeVideo::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new YouTubeVideoRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(YouTubeVideoRepository::class, $this->repository);
    }
}
