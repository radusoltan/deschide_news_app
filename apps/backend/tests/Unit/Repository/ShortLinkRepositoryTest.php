<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\ShortLink;
use App\Repository\ShortLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ShortLinkRepository.
 *
 * All query methods are covered in integration tests.
 */
class ShortLinkRepositoryTest extends TestCase
{
    private ShortLinkRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(ShortLink::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new ShortLinkRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ShortLinkRepository::class, $this->repository);
    }
}
