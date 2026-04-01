<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\UrlRedirect;
use App\Repository\UrlRedirectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UrlRedirectRepository.
 *
 * All query methods are covered in integration tests.
 */
class UrlRedirectRepositoryTest extends TestCase
{
    private UrlRedirectRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(UrlRedirect::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new UrlRedirectRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(UrlRedirectRepository::class, $this->repository);
    }
}
