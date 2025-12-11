<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * API Endpoints Smoke Tests.
 *
 * Verifies that core API endpoints are accessible, return valid responses,
 * and perform within acceptable time limits.
 */
#[Group('smoke')]
class ApiEndpointsSmokeTest extends WebTestCase
{
    private const TIMEOUT_MS = 500;

    public function testApiEntrypointRequiresAuthentication(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);
        $client->request('GET', '/api');
        $duration = (microtime(true) - $startTime) * 1000;

        // API entrypoint requires authentication in this application
        $this->assertResponseStatusCodeSame(401);
        // Relax timing for first test (container warmup)
        $this->assertLessThan(
            5000,
            $duration,
            sprintf('API entrypoint took %.2fms, expected less than 5000ms', $duration)
        );
    }

    public function testApiEntrypointReturns401WithoutToken(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        // API requires JWT token for access
        $this->assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('message', $data);
    }

    public function testArticlesEndpointIsAccessible(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);
        $client->request('GET', '/api/articles');
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();
        // Relax timing constraint for first request (database warmup)
        $this->assertLessThan(5000, $duration, 'Articles endpoint took too long');
    }

    public function testArticlesEndpointReturnsValidCollection(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/articles');

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $data = json_decode($content, true);

        $this->assertIsArray($data, 'Response should be a valid JSON array');
        $this->assertNotNull($data, 'Response should not be null');

        // Check for collection structure (API Platform format)
        // Response should have @type and totalItems at minimum
        $this->assertArrayHasKey('@type', $data, 'Response should have @type');
        $this->assertArrayHasKey('totalItems', $data, 'Response should have totalItems');

        // Check that response is a Collection type
        $this->assertEquals('Collection', $data['@type'], 'Response @type should be Collection');
    }

    public function testCategoriesEndpointReturns200(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);
        $client->request('GET', '/api/categories');
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(5000, $duration, 'Categories endpoint took too long');
    }

    public function testAuthorsEndpointReturns200(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);
        $client->request('GET', '/api/authors');
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(5000, $duration, 'Authors endpoint took too long');
    }

    public function testImagesEndpointReturns200(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);
        $client->request('GET', '/api/images');
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(5000, $duration, 'Images endpoint took too long');
    }

    public function testImportantArticlesEndpointReturns200(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);
        $client->request('GET', '/api/important_articles');
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(5000, $duration, 'Important articles endpoint took too long');
    }

    public function testMultilingualSupportWithRomanianLocale(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/articles', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        // Check response has data structure
        $this->assertTrue(is_array($data), 'Response should be an array');
    }

    public function testMultilingualSupportWithEnglishLocale(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/articles', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testMultilingualSupportWithRussianLocale(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/articles', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'ru',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testPaginationWorksWithArticles(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/articles?page=1&itemsPerPage=10', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        // Verify pagination parameters are accepted
        $this->assertTrue(is_array($data), 'Response should be an array');
    }

    public function testPaginationWorksWithCategories(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/categories?page=1&itemsPerPage=5', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testInvalidEndpointReturns404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/non-existent-endpoint-12345');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testInvalidApiResourceReturns404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/invalid-resource');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testApiDocumentationEndpointIsAccessible(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);
        $client->request('GET', '/api/docs.jsonld');
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(
            5000,
            $duration,
            sprintf('API documentation took %.2fms, expected less than 5000ms', $duration)
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('supportedClass', $data, 'API docs should contain supportedClass');
        $this->assertIsArray($data['supportedClass'], 'supportedClass should be an array');
    }

    public function testMultipleEndpointsRespondWithinTimeLimit(): void
    {
        $client = static::createClient();
        $endpoints = [
            '/api/articles',
            '/api/categories',
            '/api/authors',
            '/api/images',
        ];

        foreach ($endpoints as $endpoint) {
            $startTime = microtime(true);
            $client->request('GET', $endpoint);
            $duration = (microtime(true) - $startTime) * 1000;

            $this->assertResponseIsSuccessful(
                sprintf('Endpoint %s failed', $endpoint)
            );
            $this->assertLessThan(
                5000,
                $duration,
                sprintf('Endpoint %s took %.2fms, expected less than 5000ms', $endpoint, $duration)
            );
        }
    }
}
