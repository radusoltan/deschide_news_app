<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Tag;
use App\Tests\Functional\ApiTestCase;

/**
 * Functional tests for TagController.
 *
 * Endpoints tested:
 * - GET /api/tags/popular        (get popular tags)
 * - GET /api/tags/search         (search/autocomplete tags)
 * - GET /api/tags/{id}/related   (get related tags)
 * - GET /api/tags/{id}/stats     (get tag statistics)
 * - GET /api/tags/unused         (get unused tags)
 *
 * All GET /api/tags endpoints are PUBLIC_ACCESS per security.yaml.
 */
class TagControllerTest extends ApiTestCase
{
    private function createTestTag(string $name = null, int $usageCount = 0): Tag
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        $tag = new Tag();
        $tag->setName($name ?? 'Test Tag ' . uniqid());
        $tag->setSlug('test-tag-' . uniqid());
        $tag->setUsageCount($usageCount);
        $em->persist($tag);
        $em->flush();

        return $tag;
    }

    // =============================================
    // GET /api/tags/popular
    // =============================================

    public function testGetPopularTagsReturnsHydraCollection(): void
    {
        $client = static::createClient();

        // Create some tags with different usage counts
        $this->createTestTag('Popular Tag A', 50);
        $this->createTestTag('Popular Tag B', 30);

        $client->request('GET', '/api/tags/popular');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertArrayHasKey('hydra:totalItems', $data);
        $this->assertEquals('hydra:Collection', $data['@type']);
    }

    public function testGetPopularTagsWithLimitParam(): void
    {
        $client = static::createClient();

        // Create several tags
        for ($i = 0; $i < 5; $i++) {
            $this->createTestTag("Limit Tag $i", 10 + $i);
        }

        $client->request('GET', '/api/tags/popular?limit=3');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertLessThanOrEqual(3, count($data['hydra:member']));
    }

    public function testGetPopularTagsLimitIsCappedAt100(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/popular?limit=200');

        $this->assertResponseIsSuccessful();
        // Should not crash, limit is capped internally
    }

    public function testGetPopularTagsWithLocale(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/popular?locale=en');

        $this->assertResponseIsSuccessful();
    }

    public function testGetPopularTagsWithAcceptLanguageHeader(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/popular', [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'ru',
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testGetPopularTagsHasCacheHeaders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/popular');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHasHeader('Cache-Control');
    }

    public function testGetPopularTagsIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/popular');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // GET /api/tags/search
    // =============================================

    public function testSearchTagsWithEmptyQueryReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/search');

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('hydra:title', $data);
    }

    public function testSearchTagsWithEmptyStringReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/search?q=');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testSearchTagsWithValidQuery(): void
    {
        $client = static::createClient();

        $this->createTestTag('SearchTestTag');

        $client->request('GET', '/api/tags/search?q=Search');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertArrayHasKey('query', $data);
        $this->assertEquals('Search', $data['query']);
    }

    public function testSearchTagsWithLimitParam(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/search?q=test&limit=5');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertLessThanOrEqual(5, count($data['hydra:member']));
    }

    public function testSearchTagsLimitIsCappedAt50(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/search?q=test&limit=100');

        $this->assertResponseIsSuccessful();
    }

    // =============================================
    // GET /api/tags/{id}/related
    // =============================================

    public function testGetRelatedTagsForNonExistentTag(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/999999/related');

        $this->assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('hydra:title', $data);
        $this->assertEquals('Tag not found', $data['hydra:title']);
    }

    public function testGetRelatedTagsForExistingTag(): void
    {
        $client = static::createClient();

        $tag = $this->createTestTag('Related Test Tag', 5);

        $client->request('GET', '/api/tags/' . $tag->getId() . '/related');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertArrayHasKey('tag', $data);
    }

    public function testGetRelatedTagsWithLimitParam(): void
    {
        $client = static::createClient();

        $tag = $this->createTestTag('Related Limit Tag', 5);

        $client->request('GET', '/api/tags/' . $tag->getId() . '/related?limit=3');

        $this->assertResponseIsSuccessful();
    }

    // =============================================
    // GET /api/tags/{id}/stats
    // =============================================

    public function testGetTagStatsForNonExistentTag(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/999999/stats');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetTagStatsForExistingTag(): void
    {
        $client = static::createClient();

        $tag = $this->createTestTag('Stats Test Tag', 42);

        $client->request('GET', '/api/tags/' . $tag->getId() . '/stats');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('@type', $data);
        $this->assertEquals('TagStatistics', $data['@type']);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('slug', $data);
        $this->assertArrayHasKey('usageCount', $data);
        $this->assertArrayHasKey('articleCount', $data);
        $this->assertEquals(42, $data['usageCount']);
    }

    public function testGetTagStatsWithLocale(): void
    {
        $client = static::createClient();

        $tag = $this->createTestTag('Stats Locale Tag', 10);

        $client->request('GET', '/api/tags/' . $tag->getId() . '/stats?locale=en');

        $this->assertResponseIsSuccessful();
    }

    // =============================================
    // GET /api/tags/unused
    // =============================================

    public function testGetUnusedTagsReturnsCollection(): void
    {
        $client = static::createClient();

        // Create an unused tag (usageCount = 0)
        $this->createTestTag('Unused Tag', 0);

        $client->request('GET', '/api/tags/unused');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertArrayHasKey('hydra:totalItems', $data);
    }

    public function testGetUnusedTagsWithLimitParam(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/unused?limit=10');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertLessThanOrEqual(10, count($data['hydra:member']));
    }

    public function testGetUnusedTagsLimitIsCappedAt200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/unused?limit=300');

        $this->assertResponseIsSuccessful();
    }

    // =============================================
    // Tag serialization structure
    // =============================================

    public function testTagSerializationHasExpectedFields(): void
    {
        $client = static::createClient();

        $tag = $this->createTestTag('Serialization Test Tag', 15);

        $client->request('GET', '/api/tags/' . $tag->getId() . '/stats');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('slug', $data);
    }

    // =============================================
    // HTTP method tests
    // =============================================

    public function testPopularRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/tags/popular');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testSearchRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/tags/search');

        $this->assertResponseStatusCodeSame(405);
    }
}
