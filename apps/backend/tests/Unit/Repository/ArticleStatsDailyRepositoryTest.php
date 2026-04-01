<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\ArticleStatsDaily;
use App\Repository\ArticleStatsDailyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ArticleStatsDailyRepository.
 *
 * All query methods are covered in integration tests.
 */
class ArticleStatsDailyRepositoryTest extends TestCase
{
    private ArticleStatsDailyRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(ArticleStatsDaily::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new ArticleStatsDailyRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ArticleStatsDailyRepository::class, $this->repository);
    }
}
