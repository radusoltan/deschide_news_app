<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Tag;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for Tag API endpoints.
 *
 * Tests HTTP requests/responses for tag CRUD operations and custom endpoints
 * according to Symfony and API Platform testing best practices.
 */
class TagApiTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    // ======================
    // GET Collection Tests
    // ======================

    public function testGetTagsCollectionReturnsSuccess(): void
    {
        $this->client->request('GET', '/api/tags', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('member', $data);
        $this->assertArrayHasKey('totalItems', $data);
    }

    public function testGetTagsCollectionWithPagination(): void
    {
        $this->client->request('GET', '/api/tags?page=1&itemsPerPage=5', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('member', $data);
        $this->assertLessThanOrEqual(5, \count($data['member']));
    }

    // ======================
    // GET Single Item Tests
    // ======================

    public function testGetSingleTagReturnsSuccess(): void
    {
        // Create a test tag
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $tag = new Tag();
        $tag->setName('Test Tag');
        $tag->setSlug('test-tag');
        $tag->setDescription('Test description');

        $entityManager->persist($tag);
        $entityManager->flush();

        $tagId = $tag->getId();

        // Test GET
        $this->client->request('GET', "/api/tags/{$tagId}", [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals($tagId, $data['id']);
        $this->assertEquals('Test Tag', $data['name']);
        $this->assertEquals('test-tag', $data['slug']);
        $this->assertEquals('Test description', $data['description']);

        // Cleanup
        $entityManager->remove($tag);
        $entityManager->flush();
    }

    public function testGetNonExistentTagReturns404(): void
    {
        $this->client->request('GET', '/api/tags/99999', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ======================
    // Custom Endpoint Tests
    // ======================

    public function testGetPopularTagsReturnsSuccess(): void
    {
        $this->client->request('GET', '/api/tags/popular?limit=10', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertArrayHasKey('hydra:totalItems', $data);
        $this->assertLessThanOrEqual(10, \count($data['hydra:member']));
    }

    public function testSearchTagsReturnsSuccess(): void
    {
        // Create test tag
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $tag = new Tag();
        $tag->setName('Searchable Tag');
        $tag->setSlug('searchable-tag');

        $entityManager->persist($tag);
        $entityManager->flush();

        // Test search
        $this->client->request('GET', '/api/tags/search?q=search', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertArrayHasKey('query', $data);
        $this->assertEquals('search', $data['query']);

        // Cleanup
        $entityManager->remove($tag);
        $entityManager->flush();
    }

    public function testSearchTagsWithoutQueryReturns400(): void
    {
        $this->client->request('GET', '/api/tags/search', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('hydra:description', $data);
    }

    public function testGetTagStatsReturnsSuccess(): void
    {
        // Create test tag
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $tag = new Tag();
        $tag->setName('Stats Tag');
        $tag->setSlug('stats-tag');
        $tag->setUsageCount(5);

        $entityManager->persist($tag);
        $entityManager->flush();

        $tagId = $tag->getId();

        // Test stats endpoint
        $this->client->request('GET', "/api/tags/{$tagId}/stats", [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals($tagId, $data['id']);
        $this->assertEquals('Stats Tag', $data['name']);
        $this->assertEquals(5, $data['usageCount']);
        $this->assertArrayHasKey('articleCount', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayHasKey('updatedAt', $data);

        // Cleanup
        $entityManager->remove($tag);
        $entityManager->flush();
    }

    public function testGetRelatedTagsReturnsSuccess(): void
    {
        // Create test tag
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $tag = new Tag();
        $tag->setName('Related Tag');
        $tag->setSlug('related-tag');

        $entityManager->persist($tag);
        $entityManager->flush();

        $tagId = $tag->getId();

        // Test related endpoint
        $this->client->request('GET', "/api/tags/{$tagId}/related?limit=5", [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertArrayHasKey('tag', $data);
        $this->assertEquals($tagId, $data['tag']['id']);

        // Cleanup
        $entityManager->remove($tag);
        $entityManager->flush();
    }

    public function testGetUnusedTagsReturnsSuccess(): void
    {
        $this->client->request('GET', '/api/tags/unused?limit=20', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertLessThanOrEqual(20, \count($data['hydra:member']));

        // All returned tags should have usageCount = 0
        foreach ($data['hydra:member'] as $tag) {
            $this->assertEquals(0, $tag['usageCount']);
        }
    }

    // ======================
    // Locale Tests
    // ======================

    public function testTagsRespectAcceptLanguageHeader(): void
    {
        $this->client->request('GET', '/api/tags', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('vary', 'Accept, Accept-Language');
    }

    // ======================
    // Cache Tests
    // ======================

    public function testPopularTagsHasCacheHeaders(): void
    {
        $this->client->request('GET', '/api/tags/popular', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHasHeader('Cache-Control');
        $this->assertResponseHeaderSame('Cache-Control', 'public, max-age=600');
        $this->assertResponseHeaderSame('Vary', 'Accept-Language');
    }

    public function testSearchTagsHasCacheHeaders(): void
    {
        $this->client->request('GET', '/api/tags/search?q=test', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHasHeader('Cache-Control');
    }
}
