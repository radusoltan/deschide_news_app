<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['import', 'user'];
    }
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create admin user
        $admin = new User();
        $admin->setUsername('admin');
        $admin->setEmail('admin@deschide.local');
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword(
            $admin,
            'password'
        );
        $admin->setPassword($hashedPassword);

        $manager->persist($admin);

        // Create a regular editor user for testing
        $editor = new User();
        $editor->setUsername('editor');
        $editor->setEmail('editor@deschide.local');
        $editor->setRoles(['ROLE_EDITOR', 'ROLE_USER']);

        $hashedPassword = $this->passwordHasher->hashPassword(
            $editor,
            'password'
        );
        $editor->setPassword($hashedPassword);

        $manager->persist($editor);

        // Create a regular user for testing
        $user = new User();
        $user->setUsername('user');
        $user->setEmail('user@deschide.local');
        $user->setRoles(['ROLE_USER']);

        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            'password'
        );
        $user->setPassword($hashedPassword);

        $manager->persist($user);

        $manager->flush();
    }
}
