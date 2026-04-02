<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for UserRepository.
 */
class UserRepositoryTest extends KernelTestCase
{
    private UserRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(UserRepository::class);
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // =====================================================================
    // upgradePassword
    // =====================================================================

    public function testUpgradePasswordUpdatesPasswordInDatabase(): void
    {
        $suffix = uniqid('upgpwd-', true);

        $user = new User();
        $user->setUsername('user' . $suffix);
        $user->setEmail($suffix . '@test.example');
        $user->setPassword('old-hashed-password');
        $this->em->persist($user);
        $this->em->flush();

        $this->repository->upgradePassword($user, 'new-hashed-password');

        // Verify the password was persisted
        $this->em->clear();
        $reloaded = $this->repository->find($user->getId());

        $this->assertNotNull($reloaded);
        $this->assertSame('new-hashed-password', $reloaded->getPassword());
    }

    // =====================================================================
    // findByRoles
    // =====================================================================

    public function testFindByRolesReturnsUsersWithMatchingRole(): void
    {
        $suffix = uniqid('role-', true);

        $adminUser = new User();
        $adminUser->setUsername('admin' . $suffix);
        $adminUser->setEmail('admin' . $suffix . '@test.example');
        $adminUser->setPassword('hashed');
        $adminUser->setRoles(['ROLE_ADMIN']);
        $this->em->persist($adminUser);

        $regularUser = new User();
        $regularUser->setUsername('regular' . $suffix);
        $regularUser->setEmail('regular' . $suffix . '@test.example');
        $regularUser->setPassword('hashed');
        $regularUser->setRoles(['ROLE_USER']);
        $this->em->persist($regularUser);

        $this->em->flush();

        $results = $this->repository->findByRoles(['ROLE_ADMIN']);

        $this->assertIsArray($results);
        $ids = array_map(fn (User $u) => $u->getId(), $results);
        $this->assertContains($adminUser->getId(), $ids);
    }

    public function testFindByRolesReturnsEmptyForNoMatch(): void
    {
        $results = $this->repository->findByRoles(['ROLE_NONEXISTENT_' . uniqid()]);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testFindByRolesAcceptsMultipleRoles(): void
    {
        $suffix = uniqid('multi-', true);

        $editor = new User();
        $editor->setUsername('editor' . $suffix);
        $editor->setEmail('editor' . $suffix . '@test.example');
        $editor->setPassword('hashed');
        $editor->setRoles(['ROLE_EDITOR']);
        $this->em->persist($editor);
        $this->em->flush();

        $results = $this->repository->findByRoles(['ROLE_ADMIN', 'ROLE_EDITOR']);

        $this->assertIsArray($results);
        // Should include the editor user
        $ids = array_map(fn (User $u) => $u->getId(), $results);
        $this->assertContains($editor->getId(), $ids);
    }
}
