<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\DataFixtures\TestFixtures;
use App\Entity\AggregatorRun;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for AggregatorRun API endpoints.
 *
 * Tests read-only GET operations, security (ROLE_EDITOR required),
 * and filter support for the /api/aggregator-runs resource.
 */
class AggregatorRunApiTest extends WebTestCase
{
    /**
     * Create an authenticated client with JWT token.
     */
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

        // Ensure user exists
        $userRepo = $em->getRepository(User::class);
        if (!$userRepo->findOneBy(['username' => $username])) {
            $user = new User();
            $user->setUsername($username);
            $user->setEmail($username . '@test.local');
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

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $client;
    }

    /**
     * Helper: create an AggregatorRun entity directly in the database.
     */
    private function createAggregatorRun(
        string $source = 'google_news_rss',
        string $status = 'completed',
        string $triggeredBy = 'scheduler',
        int $articlesFound = 5,
    ): AggregatorRun {
        $em = static::getContainer()->get('doctrine')->getManager();

        $run = new AggregatorRun();
        $run->setSource($source);
        $run->setTriggeredBy($triggeredBy);
        $run->setArticlesFound($articlesFound);

        if ($status === 'completed') {
            $run->markCompleted();
        } elseif ($status === 'failed') {
            $run->markFailed('Test failure');
        }

        $em->persist($run);
        $em->flush();

        return $run;
    }

    // ==============================
    // Security Tests
    // ==============================

    public function testGetCollectionRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/aggregator-runs', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetSingleRequiresAuthentication(): void
    {
        $client = static::createClient();

        // Use a fake UUID -- will fail auth before 404
        $client->request('GET', '/api/aggregator-runs/00000000-0000-7000-8000-000000000001', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    // ==============================
    // GET Collection Tests
    // ==============================

    public function testGetCollectionAsEditorReturnsSuccess(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $this->createAggregatorRun();

        $client->request('GET', '/api/aggregator-runs', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('member', $data);
        $this->assertArrayHasKey('totalItems', $data);
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testGetCollectionReturnsHydraFormat(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $this->createAggregatorRun('test_source', 'completed', 'manual', 10);

        $client->request('GET', '/api/aggregator-runs', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@id', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertArrayHasKey('totalItems', $data);
        $this->assertArrayHasKey('member', $data);

        // Verify member structure
        $member = $data['member'][0] ?? null;
        $this->assertNotNull($member, 'Expected at least one member');
        $this->assertArrayHasKey('source', $member);
        $this->assertArrayHasKey('status', $member);
        $this->assertArrayHasKey('startedAt', $member);
        $this->assertArrayHasKey('triggeredBy', $member);
        $this->assertArrayHasKey('articlesFound', $member);
    }

    // ==============================
    // GET Single Item Tests
    // ==============================

    public function testGetSingleRunReturnsSuccess(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $run = $this->createAggregatorRun('google_alerts', 'completed', 'manual', 7);
        $id = $run->getId()->toRfc4122();

        $client->request('GET', "/api/aggregator-runs/{$id}", [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('google_alerts', $data['source']);
        $this->assertEquals('completed', $data['status']);
        $this->assertEquals('manual', $data['triggeredBy']);
        $this->assertEquals(7, $data['articlesFound']);
        $this->assertArrayHasKey('startedAt', $data);
        $this->assertArrayHasKey('finishedAt', $data);
    }

    public function testGetNonExistentRunReturns404(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $client->request('GET', '/api/aggregator-runs/01966a00-0000-7000-8000-000000000099', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ==============================
    // Filter Tests
    // ==============================

    public function testFilterBySourceExact(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $this->createAggregatorRun('filter_source_a', 'completed');
        $this->createAggregatorRun('filter_source_b', 'completed');

        $client->request('GET', '/api/aggregator-runs?source=filter_source_a', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertGreaterThanOrEqual(1, $data['totalItems']);

        foreach ($data['member'] as $member) {
            $this->assertEquals('filter_source_a', $member['source']);
        }
    }

    public function testFilterByStatusExact(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $this->createAggregatorRun('status_test', 'completed');
        $this->createAggregatorRun('status_test', 'failed');

        $client->request('GET', '/api/aggregator-runs?status=failed', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertGreaterThanOrEqual(1, $data['totalItems']);

        foreach ($data['member'] as $member) {
            $this->assertEquals('failed', $member['status']);
        }
    }

    public function testFilterByTriggeredByExact(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $this->createAggregatorRun('trigger_test', 'completed', 'manual');
        $this->createAggregatorRun('trigger_test', 'completed', 'scheduler');

        $client->request('GET', '/api/aggregator-runs?triggeredBy=manual', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertGreaterThanOrEqual(1, $data['totalItems']);

        foreach ($data['member'] as $member) {
            $this->assertEquals('manual', $member['triggeredBy']);
        }
    }

    // ==============================
    // Read-Only Enforcement Tests
    // ==============================

    public function testPostIsNotAllowed(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_ADMIN');

        $client->request('POST', '/api/aggregator-runs', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'source' => 'test',
            'triggeredBy' => 'manual',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testDeleteIsNotAllowed(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_ADMIN');

        $run = $this->createAggregatorRun();
        $id = $run->getId()->toRfc4122();

        $client->request('DELETE', "/api/aggregator-runs/{$id}", [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }
}
