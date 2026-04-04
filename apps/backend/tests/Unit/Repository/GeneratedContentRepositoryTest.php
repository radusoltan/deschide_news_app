<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\GeneratedContent;
use App\Repository\GeneratedContentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GeneratedContentRepository.
 *
 * Verifies repository construction and entity class binding.
 * Full query tests require a live DB (see Integration tests).
 */
class GeneratedContentRepositoryTest extends TestCase
{
    private GeneratedContentRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(GeneratedContent::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new GeneratedContentRepository($registry);
    }

    public function testRepositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(GeneratedContentRepository::class, $this->repository);
    }

    public function testEntityClassName(): void
    {
        $this->assertSame(GeneratedContent::class, $this->repository->getClassName());
    }

    public function testFindLatestByType_methodExists(): void
    {
        $this->assertTrue(
            method_exists($this->repository, 'findLatestByType'),
            'Repository must have findLatestByType method',
        );
    }

    public function testFindByDateRange_methodExists(): void
    {
        $this->assertTrue(
            method_exists($this->repository, 'findByDateRange'),
            'Repository must have findByDateRange method',
        );
    }

    public function testFindLatestBriefing_methodExists(): void
    {
        $this->assertTrue(
            method_exists($this->repository, 'findLatestBriefing'),
            'Repository must have findLatestBriefing method',
        );
    }
}
