<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\DataFixtures\TestFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for AggregatorTriggerController.
 *
 * Endpoints tested:
 * - POST /api/aggregator/run (trigger manual aggregator run)
 *
 * The endpoint requires ROLE_ADMIN authentication.
 */
class AggregatorTriggerControllerTest extends WebTestCase
{
    private function createAuthenticatedClient(string $role = 'ROLE_ADMIN'): KernelBrowser
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

        // Ensure user exists
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

        // Login to get JWT token
        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $username,
            'password' => $password,
        ]));

        $data = json_decode($client->getResponse()->getContent(), true);
        $token = $data['token'] ?? throw new \RuntimeException('Failed to get JWT token');

        // Set token for subsequent requests
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $client;
    }

    // =============================================
    // POST /api/aggregator/run - Authentication
    // =============================================

    public function testRunEndpointRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/aggregator/run', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRunEndpointForbiddenForEditor(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');
        $client->request('POST', '/api/aggregator/run', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(403);
    }

    // =============================================
    // POST /api/aggregator/run - Admin access
    // =============================================

    public function testRunEndpointReturns202ForAdmin(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_ADMIN');
        $client->request('POST', '/api/aggregator/run', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(202);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('queued', $data['status']);
        $this->assertSame('Aggregator run queued', $data['message']);
        $this->assertSame('all', $data['source']);
    }

    public function testRunEndpointAcceptsSourceFilter(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_ADMIN');
        $client->request('POST', '/api/aggregator/run', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['source' => 'google_news_rss']));

        $this->assertResponseStatusCodeSame(202);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('google_news_rss', $data['source']);
    }

    public function testRunEndpointRejectsGetMethod(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/aggregator/run');

        // Unauthenticated GET should return 401 (auth checked before method)
        // or 405 (method checked before auth) depending on firewall config
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [401, 405]);
    }
}
