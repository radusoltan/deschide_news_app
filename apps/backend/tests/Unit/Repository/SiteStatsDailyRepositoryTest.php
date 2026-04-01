<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\SiteStatsDaily;
use App\Repository\SiteStatsDailyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SiteStatsDailyRepository.
 *
 * All query methods are covered in integration tests.
 */
class SiteStatsDailyRepositoryTest extends TestCase
{
    private SiteStatsDailyRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(SiteStatsDaily::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new SiteStatsDailyRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(SiteStatsDailyRepository::class, $this->repository);
    }
}
