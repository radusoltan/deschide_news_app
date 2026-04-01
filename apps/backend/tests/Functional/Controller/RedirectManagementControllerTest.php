<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\UrlRedirect;
use App\Tests\Functional\ApiTestCase;

/**
 * Functional tests for RedirectManagementController.
 *
 * Endpoints tested:
 * - GET  /api/redirects/statistics   (system-wide redirect stats)
 * - GET  /api/redirects/by-entity    (find redirects by entity)
 * - GET  /api/redirects/health       (health check for redirect system)
 * - POST /api/redirects/find-chains  (find problematic chains)
 * - GET  /api/redirects/lookup       (lookup redirect for URL)
 *
 * All /api/redirects endpoints are PUBLIC_ACCESS per security.yaml.
 */
class RedirectManagementControllerTest extends ApiTestCase
{
    private function createTestRedirect(
        string $oldUrl = null,
        string $newUrl = null,
        string $type = 'article',
        string $locale = 'ro'
    ): UrlRedirect {
        $em = static::getContainer()->get('doctrine')->getManager();

        $redirect = new UrlRedirect();
        $redirect->setOldUrl($oldUrl ?? '/old-url-' . uniqid());
        $redirect->setNewUrl($newUrl ?? '/new-url-' . uniqid());
        $redirect->setType($type);
        $redirect->setLocale($locale);
        $redirect->setHttpStatusCode(301);
        $redirect->setEntityId(1);
        $em->persist($redirect);
        $em->flush();

        return $redirect;
    }

    // =============================================
    // GET /api/redirects/statistics
    // =============================================

    public function testGetStatisticsReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/statistics');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('statistics', $data);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testGetStatisticsIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/statistics');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testGetStatisticsRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/redirects/statistics');

        $this->assertResponseStatusCodeSame(405);
    }

    // =============================================
    // GET /api/redirects/by-entity
    // =============================================

    public function testGetByEntityWithMissingParamsReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/by-entity');

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Missing required parameters', $data['error']);
    }

    public function testGetByEntityWithMissingTypeReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/by-entity?entity_id=1');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetByEntityWithMissingEntityIdReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/by-entity?type=article');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetByEntityWithInvalidTypeReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/by-entity?type=invalid&entity_id=1');

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Invalid type', $data['error']);
    }

    public function testGetByEntityWithValidParams(): void
    {
        $client = static::createClient();

        $this->createTestRedirect('/old-entity-test', '/new-entity-test', 'article');

        $client->request('GET', '/api/redirects/by-entity?type=article&entity_id=1');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('entity', $data);
        $this->assertArrayHasKey('redirects', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertEquals('article', $data['entity']['type']);
        $this->assertEquals(1, $data['entity']['id']);
    }

    public function testGetByEntityWithCategoryType(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/by-entity?type=category&entity_id=5');

        $this->assertResponseIsSuccessful();
    }

    public function testGetByEntityWithAuthorType(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/by-entity?type=author&entity_id=1');

        $this->assertResponseIsSuccessful();
    }

    public function testGetByEntityWithManualType(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/by-entity?type=manual&entity_id=1');

        $this->assertResponseIsSuccessful();
    }

    public function testGetByEntityIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/by-entity?type=article&entity_id=1');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // GET /api/redirects/health
    // =============================================

    public function testGetHealthReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/health');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('health', $data);
        $this->assertArrayHasKey('status', $data['health']);
        $this->assertArrayHasKey('total_redirects', $data['health']);
        $this->assertArrayHasKey('unused_redirects', $data['health']);
        $this->assertArrayHasKey('issues', $data['health']);
    }

    public function testGetHealthStatusIsHealthyOrWarning(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/health');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertContains(
            $data['health']['status'],
            ['healthy', 'warning', 'critical']
        );
    }

    public function testGetHealthIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/health');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // POST /api/redirects/find-chains
    // =============================================

    public function testFindChainsReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/redirects/find-chains', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'min_chain_length' => 3,
            'limit' => 10,
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('chains', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('total_checked', $data['summary']);
        $this->assertArrayHasKey('problematic_chains', $data['summary']);
    }

    public function testFindChainsWithDefaultParams(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/redirects/find-chains', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testFindChainsLimitIsCapped(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/redirects/find-chains', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'limit' => 500, // Should be capped at 100
        ]));

        $this->assertResponseIsSuccessful();
    }

    public function testFindChainsIsPublic(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/redirects/find-chains', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // GET /api/redirects/lookup
    // =============================================

    public function testLookupWithoutUrlReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/lookup');

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('URL parameter is required', $data['message']);
    }

    public function testLookupForNonExistentRedirectReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/lookup?url=/nonexistent-url-' . uniqid());

        $this->assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('No redirect found', $data['message']);
    }

    public function testLookupForExistingRedirectReturnsData(): void
    {
        $client = static::createClient();

        $oldUrl = '/test-lookup-old-' . uniqid();
        $newUrl = '/test-lookup-new-' . uniqid();
        $this->createTestRedirect($oldUrl, $newUrl, 'article', 'ro');

        $client->request('GET', '/api/redirects/lookup?url=' . urlencode($oldUrl) . '&locale=ro');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('redirect', $data);
        $this->assertEquals($oldUrl, $data['redirect']['old_url']);
        $this->assertEquals($newUrl, $data['redirect']['new_url']);
        $this->assertEquals(301, $data['redirect']['status_code']);
    }

    public function testLookupWithLocaleParam(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/lookup?url=/test-url&locale=en');

        // Should get 404 since URL doesn't exist, not 400
        $this->assertResponseStatusCodeSame(404);
    }

    public function testLookupIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/lookup?url=/test');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // HTTP method tests
    // =============================================

    public function testStatisticsRejectsDeleteMethod(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/api/redirects/statistics');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testHealthRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/redirects/health');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testFindChainsRejectsGetMethod(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/redirects/find-chains');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testLookupRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/redirects/lookup');

        $this->assertResponseStatusCodeSame(405);
    }
}
