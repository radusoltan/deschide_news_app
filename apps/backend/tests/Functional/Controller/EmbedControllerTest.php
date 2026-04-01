<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

/**
 * Functional tests for EmbedController.
 *
 * Endpoints tested:
 * - GET /api/embed/live-text/{id}          (get LiveText data for embedding)
 * - GET /api/embed/live-text/slug/{slug}   (get LiveText by slug)
 * - GET /api/embed/code/{id}               (get embed code HTML snippet)
 * - GET /api/embed/list                    (list embeddable live texts)
 *
 * All /api/embed endpoints are PUBLIC_ACCESS per security.yaml.
 */
class EmbedControllerTest extends ApiTestCase
{
    // =============================================
    // GET /api/embed/live-text/{id}
    // =============================================

    public function testGetLiveTextByIdNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/live-text/999999');

        $this->assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotNull($data);
        $this->assertEquals('LiveText not found', $data['error']);
    }

    public function testGetLiveTextByIdIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/live-text/999999');

        // Should NOT return 401
        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testGetLiveTextByIdWithLocaleParam(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/live-text/999999?locale=en');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetLiveTextByIdWithPaginationParams(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/live-text/999999?limit=10&offset=0');

        $this->assertResponseStatusCodeSame(404);
    }

    // =============================================
    // GET /api/embed/live-text/slug/{slug}
    // =============================================

    public function testGetLiveTextBySlugNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/live-text/slug/nonexistent-slug');

        $this->assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('LiveText not found', $data['error']);
    }

    public function testGetLiveTextBySlugIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/live-text/slug/any-slug');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // GET /api/embed/code/{id}
    // =============================================

    public function testGetEmbedCodeNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/code/999999');

        $this->assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('LiveText not found', $data['error']);
    }

    public function testGetEmbedCodeIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/code/999999');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testGetEmbedCodeWithThemeParam(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/code/999999?theme=dark');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetEmbedCodeWithDimensionParams(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/code/999999?width=800px&height=400px');

        $this->assertResponseStatusCodeSame(404);
    }

    // =============================================
    // GET /api/embed/list
    // =============================================

    public function testListEmbeddableLiveTextsReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/list');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotNull($data);
        $this->assertArrayHasKey('liveTexts', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertIsArray($data['liveTexts']);
    }

    public function testListEmbeddableLiveTextsIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/list');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testListEmbeddableLiveTextsWithLimitParam(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/list?limit=5');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertLessThanOrEqual(5, count($data['liveTexts']));
    }

    public function testListEmbeddableLiveTextsWithStatusFilter(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/list?status=active');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data['liveTexts']);
    }

    // =============================================
    // HTTP method tests
    // =============================================

    public function testListRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/embed/list');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testGetLiveTextRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/embed/live-text/1');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testGetEmbedCodeRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/embed/code/1');

        $this->assertResponseStatusCodeSame(405);
    }
}
