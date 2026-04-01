<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Unit tests for UserRepository.
 *
 * Tests the upgradePassword method's type-check guard clause.
 */
class UserRepositoryTest extends TestCase
{
    private UserRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->em->method('getClassMetadata')->willReturn(new ClassMetadata(User::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);

        $this->repository = new UserRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(UserRepository::class, $this->repository);
    }

    #[Test]
    public function upgradePasswordThrowsExceptionForNonUserInstance(): void
    {
        $nonUser = $this->createStub(PasswordAuthenticatedUserInterface::class);

        $this->expectException(UnsupportedUserException::class);

        $this->repository->upgradePassword($nonUser, 'new-hashed-password');
    }

    #[Test]
    public function upgradePasswordAcceptsUserInstanceAndPersists(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('old-password');

        // The em stub won't do anything, but the method should not throw
        $this->repository->upgradePassword($user, 'new-hashed-password');

        $this->assertSame('new-hashed-password', $user->getPassword());
    }

    #[Test]
    public function repositoryImplementsPasswordUpgraderInterface(): void
    {
        $this->assertInstanceOf(
            \Symfony\Component\Security\Core\User\PasswordUpgraderInterface::class,
            $this->repository,
        );
    }
}
