<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\DataFixtures\TestFixtures;
use App\Entity\PressRelease;
use App\Entity\User;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PressReleaseFetchContentControllerTest extends WebTestCase
{
    private function createAuthenticatedClient(string $role = 'ROLE_EDITOR'): KernelBrowser
    {
        $client = static::createClient();

        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $username = match ($role) {
            'ROLE_ADMIN' => TestFixtures::ADMIN_USER_USERNAME,
            'ROLE_EDITOR' => TestFixtures::EDITOR_USER_USERNAME,
            default => TestFixtures::REGULAR_USER_USERNAME,
        };
        $password = match ($role) {
            'ROLE_ADMIN' => TestFixtures::ADMIN_USER_PASSWORD,
            'ROLE_EDITOR' => TestFixtures::EDITOR_USER_PASSWORD,
            default => TestFixtures::REGULAR_USER_PASSWORD,
        };
        $roles = match ($role) {
            'ROLE_ADMIN' => ['ROLE_ADMIN'],
            'ROLE_EDITOR' => ['ROLE_EDITOR'],
            default => [],
        };
        $email = match ($role) {
            'ROLE_ADMIN' => TestFixtures::ADMIN_USER_EMAIL,
            'ROLE_EDITOR' => TestFixtures::EDITOR_USER_EMAIL,
            default => TestFixtures::REGULAR_USER_EMAIL,
        };

        $userRepo = $em->getRepository(User::class);
        if (!$userRepo->findOneBy(['username' => $username])) {
            $user = new User();
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setFirstName('Test');
            $user->setLastName('User');
            $user->setRoles($roles);
            $user->setPassword($hasher->hashPassword($user, $password));
            $em->persist($user);
            $em->flush();
        }

        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $username,
            'password' => $password,
        ]));

        $data = json_decode($client->getResponse()->getContent(), true);
        $token = $data['token'] ?? throw new \RuntimeException('Failed to get JWT token');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $client;
    }

    public function testRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/press-releases/1/fetch-content');

        self::assertResponseStatusCodeSame(401);
    }

    public function testReturns404ForMissingPressRelease(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/press-releases/999999/fetch-content');

        self::assertResponseStatusCodeSame(404);
    }

    public function testReturns422ForPressReleaseWithoutSourceUrl(): void
    {
        $client = $this->createAuthenticatedClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine')->getManager();

        $pr = new PressRelease();
        $pr->setTitle('Test PR without URL');
        $pr->setContent('Short');
        $pr->setCategorySlug('societate');
        $pr->setSourceType(SourceType::AGGREGATOR);
        $pr->setStatus(PressReleaseStatus::PENDING);
        $em->persist($pr);
        $em->flush();

        $client->request('POST', '/api/press-releases/' . $pr->getId() . '/fetch-content');

        self::assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertStringContainsString('sourceUrl', $data['error']);
    }

    public function testReturns422WhenContentAlreadyExists(): void
    {
        $client = $this->createAuthenticatedClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine')->getManager();

        $pr = new PressRelease();
        $pr->setTitle('Test PR with content');
        $pr->setContent(str_repeat('Long content paragraph. ', 50));
        $pr->setCategorySlug('societate');
        $pr->setSourceType(SourceType::AGGREGATOR);
        $pr->setSourceUrl('https://example.com/article');
        $pr->setStatus(PressReleaseStatus::PENDING);
        $em->persist($pr);
        $em->flush();

        $client->request('POST', '/api/press-releases/' . $pr->getId() . '/fetch-content');

        self::assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertStringContainsString('already has content', $data['error']);
    }
}
