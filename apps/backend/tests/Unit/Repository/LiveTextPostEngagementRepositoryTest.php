<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\LiveTextPostEngagement;
use App\Repository\LiveTextPostEngagementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LiveTextPostEngagementRepository.
 *
 * All query methods are covered in integration tests.
 */
class LiveTextPostEngagementRepositoryTest extends TestCase
{
    private LiveTextPostEngagementRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(LiveTextPostEngagement::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new LiveTextPostEngagementRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(LiveTextPostEngagementRepository::class, $this->repository);
    }
}
