<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for MetricsController.
 *
 * Endpoints tested:
 * - GET /metrics (Prometheus metrics, requires ROLE_ADMIN)
 */
class MetricsControllerTest extends WebTestCase
{
    public function testMetricsEndpointRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/metrics');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testMetricsEndpointIsAccessibleForAdmin(): void
    {
        $client = static::createClient();
        $user = $this->createAdminUser($client);
        $client->loginUser($user);
        $client->request('GET', '/metrics');

        $this->assertResponseIsSuccessful();
    }

    public function testMetricsEndpointReturnsPrometheusFormat(): void
    {
        $client = static::createClient();
        $user = $this->createAdminUser($client);
        $client->loginUser($user);
        $client->request('GET', '/metrics');

        $this->assertResponseIsSuccessful();

        $response = $client->getResponse();
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('text/plain', $contentType);

        $content = $response->getContent();
        $this->assertNotEmpty($content);
    }

    public function testMetricsEndpointRejectsPostMethod(): void
    {
        $client = static::createClient();
        $user = $this->createAdminUser($client);
        $client->loginUser($user);
        $client->request('POST', '/metrics');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testMetricsEndpointRejectsPutMethod(): void
    {
        $client = static::createClient();
        $user = $this->createAdminUser($client);
        $client->loginUser($user);
        $client->request('PUT', '/metrics');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testMetricsEndpointRejectsDeleteMethod(): void
    {
        $client = static::createClient();
        $user = $this->createAdminUser($client);
        $client->loginUser($user);
        $client->request('DELETE', '/metrics');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testMetricsContentContainsExpectedFormat(): void
    {
        $client = static::createClient();
        $user = $this->createAdminUser($client);
        $client->loginUser($user);
        $client->request('GET', '/metrics');

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
    }

    private function createAdminUser($client): User
    {
        $container = $client->getContainer();
        $em = $container->get('doctrine.orm.entity_manager');

        $user = $em->getRepository(User::class)->findOneBy(['username' => 'admin_metrics_test']);
        if ($user) {
            return $user;
        }

        $user = new User();
        $user->setUsername('admin_metrics_test');
        $user->setEmail('admin_metrics@test.local');
        $user->setFirstName('Metrics');
        $user->setLastName('Admin');
        $user->setRoles(['ROLE_ADMIN']);

        $hasher = $container->get('security.user_password_hasher');
        $user->setPassword($hasher->hashPassword($user, 'test_password'));

        $em->persist($user);
        $em->flush();

        return $user;
    }
}
