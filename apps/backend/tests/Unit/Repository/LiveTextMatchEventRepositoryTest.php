<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\LiveTextMatchEvent;
use App\Repository\LiveTextMatchEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LiveTextMatchEventRepository.
 *
 * All query methods are covered in integration tests.
 */
class LiveTextMatchEventRepositoryTest extends TestCase
{
    private LiveTextMatchEventRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(LiveTextMatchEvent::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new LiveTextMatchEventRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(LiveTextMatchEventRepository::class, $this->repository);
    }
}
