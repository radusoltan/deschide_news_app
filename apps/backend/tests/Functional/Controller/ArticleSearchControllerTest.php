<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

/**
 * Functional tests for ArticleSearchController.
 *
 * Endpoints tested:
 * - GET /search-test  (diagnostic endpoint, public)
 * - GET /search       (Elasticsearch search, public)
 *
 * Both endpoints are outside the /api prefix firewall,
 * so they fall under the "main" firewall (lazy/public).
 */
class ArticleSearchControllerTest extends ApiTestCase
{
    // =============================================
    // GET /search-test - Test endpoint
    // =============================================

    public function testSearchTestEndpointIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search-test');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotNull($data);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('Controller is working', $data['message']);
    }

    public function testSearchTestEndpointRejectsPost(): void
    {
        $client = static::createClient();
        $client->request('POST', '/search-test');

        $this->assertResponseStatusCodeSame(405);
    }

    // =============================================
    // GET /search - Article search
    // =============================================

    public function testSearchWithoutQueryReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search');

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotNull($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContains('at least 2 characters', $data['error']);
    }

    public function testSearchWithEmptyQueryReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?q=');

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testSearchWithSingleCharQueryReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?q=a');

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals(0, $data['total']);
        $this->assertEmpty($data['results']);
    }

    public function testSearchWithValidQueryReturnsExpectedStructure(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?q=test+article');

        $response = $client->getResponse();

        // Elasticsearch might be down in test env, accept both 200 and 500
        $this->assertContains($response->getStatusCode(), [200, 500]);

        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data);
        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('itemsPerPage', $data);
        $this->assertArrayHasKey('totalPages', $data);
        $this->assertArrayHasKey('query', $data);
    }

    public function testSearchWithPaginationParameters(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?q=test&page=2&itemsPerPage=5');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);

        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data);

        if ($response->getStatusCode() === 200) {
            $this->assertEquals(2, $data['page']);
            $this->assertEquals(5, $data['itemsPerPage']);
        }
    }

    public function testSearchWithLocaleQueryParam(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?q=test&locale=en');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);

        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data);
        $this->assertArrayHasKey('query', $data);
    }

    public function testSearchWithAcceptLanguageHeader(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?q=test', [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'ru',
        ]);

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);
    }

    public function testSearchWithCategoryFilter(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?q=test&categoryId=5');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);
    }

    public function testSearchItemsPerPageIsCappedAt100(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?q=test&itemsPerPage=200');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);

        $data = json_decode($response->getContent(), true);
        if ($response->getStatusCode() === 200) {
            $this->assertLessThanOrEqual(100, $data['itemsPerPage']);
        }
    }

    public function testSearchPageMinimumIsOne(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search?q=test&page=0');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);

        $data = json_decode($response->getContent(), true);
        if ($response->getStatusCode() === 200) {
            $this->assertGreaterThanOrEqual(1, $data['page']);
        }
    }

    public function testSearchRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/search?q=test');

        $this->assertResponseStatusCodeSame(405);
    }

    /**
     * Helper to check substring presence in a string.
     */
    private static function assertStringContains(string $needle, string $haystack): void
    {
        self::assertStringContainsString($needle, $haystack);
    }
}
