<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\ShortLinkInteraction;
use App\Repository\ShortLinkInteractionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ShortLinkInteractionRepository.
 *
 * All query methods are covered in integration tests.
 */
class ShortLinkInteractionRepositoryTest extends TestCase
{
    private ShortLinkInteractionRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(ShortLinkInteraction::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new ShortLinkInteractionRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ShortLinkInteractionRepository::class, $this->repository);
    }
}
