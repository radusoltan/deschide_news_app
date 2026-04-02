<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\AdminNotification;
use App\Repository\AdminNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AdminNotificationRepository.
 *
 * All query methods are covered in integration tests.
 */
class AdminNotificationRepositoryTest extends TestCase
{
    private AdminNotificationRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(AdminNotification::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new AdminNotificationRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(AdminNotificationRepository::class, $this->repository);
    }
}
