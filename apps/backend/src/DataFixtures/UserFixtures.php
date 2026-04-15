<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public static function getGroups(): array
    {
        return ['import', 'user'];
    }

    public function load(ObjectManager $manager): void
    {
        $users = [
            [
                'username' => 'admin',
                'email' => 'admin@news-app.local',
                'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            ],
            [
                'username' => 'editor',
                'email' => 'editor@news-app.local',
                'roles' => ['ROLE_EDITOR', 'ROLE_USER'],
            ],
            [
                'username' => 'ai_asistent',
                'email' => 'ai@news-app.local',
                'roles' => ['ROLE_EDITOR', 'ROLE_USER'],
            ],
        ];

        foreach ($users as $userData) {
            $user = new User();
            $user->setUsername($userData['username']);
            $user->setEmail($userData['email']);
            $user->setRoles($userData['roles']);
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, 'password')
            );

            $manager->persist($user);
        }

        $manager->flush();

        echo '✅ Created ' . \count($users) . " users (admin, editor, ai_asistent)\n";
    }
}
