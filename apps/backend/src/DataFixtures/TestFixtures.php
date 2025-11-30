<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Test fixtures for PHPUnit tests.
 *
 * Creates test users with known credentials for authentication testing.
 * These fixtures are automatically loaded in test environment.
 *
 * Usage in tests:
 * - Use TestFixtures::ADMIN_USER_EMAIL with password 'admin123'
 * - Use TestFixtures::REGULAR_USER_EMAIL with password 'user123'
 */
class TestFixtures extends Fixture
{
    // Reference constants for accessing fixtures in tests
    public const ADMIN_USER_REFERENCE = 'admin-user';
    public const REGULAR_USER_REFERENCE = 'regular-user';
    public const EDITOR_USER_REFERENCE = 'editor-user';

    // Known credentials for test users
    public const ADMIN_USER_USERNAME = 'test_admin';
    public const ADMIN_USER_EMAIL = 'admin@test.com';
    public const ADMIN_USER_PASSWORD = 'admin123';

    public const REGULAR_USER_USERNAME = 'test_user';
    public const REGULAR_USER_EMAIL = 'user@test.com';
    public const REGULAR_USER_PASSWORD = 'user123';

    public const EDITOR_USER_USERNAME = 'test_editor';
    public const EDITOR_USER_EMAIL = 'editor@test.com';
    public const EDITOR_USER_PASSWORD = 'editor123';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create admin user with ROLE_ADMIN
        $adminUser = new User();
        $adminUser->setUsername(self::ADMIN_USER_USERNAME);
        $adminUser->setEmail(self::ADMIN_USER_EMAIL);
        $adminUser->setFirstName('Test');
        $adminUser->setLastName('Admin');
        $adminUser->setRoles(['ROLE_ADMIN']);
        $adminUser->setPassword(
            $this->passwordHasher->hashPassword($adminUser, self::ADMIN_USER_PASSWORD)
        );
        $manager->persist($adminUser);
        $this->addReference(self::ADMIN_USER_REFERENCE, $adminUser);

        // Create regular user with ROLE_USER (default)
        $regularUser = new User();
        $regularUser->setUsername(self::REGULAR_USER_USERNAME);
        $regularUser->setEmail(self::REGULAR_USER_EMAIL);
        $regularUser->setFirstName('Test');
        $regularUser->setLastName('User');
        $regularUser->setRoles([]); // Will get ROLE_USER automatically
        $regularUser->setPassword(
            $this->passwordHasher->hashPassword($regularUser, self::REGULAR_USER_PASSWORD)
        );
        $manager->persist($regularUser);
        $this->addReference(self::REGULAR_USER_REFERENCE, $regularUser);

        // Create editor user with ROLE_EDITOR
        $editorUser = new User();
        $editorUser->setUsername(self::EDITOR_USER_USERNAME);
        $editorUser->setEmail(self::EDITOR_USER_EMAIL);
        $editorUser->setFirstName('Test');
        $editorUser->setLastName('Editor');
        $editorUser->setRoles(['ROLE_EDITOR']);
        $editorUser->setPassword(
            $this->passwordHasher->hashPassword($editorUser, self::EDITOR_USER_PASSWORD)
        );
        $manager->persist($editorUser);
        $this->addReference(self::EDITOR_USER_REFERENCE, $editorUser);

        $manager->flush();
    }
}
