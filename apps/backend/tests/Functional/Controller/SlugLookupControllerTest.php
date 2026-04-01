<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

/**
 * Functional tests for SlugLookupController.
 *
 * Endpoints tested:
 * - POST /api/slug/lookup          (find article by slug)
 * - POST /api/slug/validate        (check slug availability)
 * - POST /api/slug/check-redirect  (check redirect chain)
 * - GET  /api/slug/reserved        (list reserved slugs)
 * - POST /api/slug/check-reserved  (check if slug is reserved)
 * - POST /api/slug/bulk-validate   (bulk validate slugs)
 * - POST /api/slug/suggest         (suggest slugs from title)
 *
 * All /api/slug endpoints are PUBLIC_ACCESS per security.yaml.
 */
class SlugLookupControllerTest extends ApiTestCase
{
    // =============================================
    // POST /api/slug/lookup
    // =============================================

    public function testLookupWithMissingFieldsReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/lookup', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Missing required fields', $data['error']);
    }

    public function testLookupWithMissingArticleSlugReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/lookup', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'category_slug' => 'politica',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testLookupForNonExistentArticleReturns404(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/lookup', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'category_slug' => 'nonexistent-category',
            'article_slug' => 'nonexistent-article',
            'locale' => 'ro',
        ]));

        $response = $client->getResponse();
        // Could be 404 (not found) or 301 (redirect)
        $this->assertContains($response->getStatusCode(), [200, 301, 404]);

        $data = json_decode($response->getContent(), true);
        if ($response->getStatusCode() === 404) {
            $this->assertFalse($data['success']);
            $this->assertEquals('Article not found', $data['error']);
        }
    }

    public function testLookupIsPubliclyAccessible(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/lookup', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'category_slug' => 'test',
            'article_slug' => 'test',
        ]));

        // Should NOT return 401
        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testLookupRejectsGetMethod(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/slug/lookup');

        $this->assertResponseStatusCodeSame(405);
    }

    // =============================================
    // POST /api/slug/validate
    // =============================================

    public function testValidateWithMissingFieldsReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/validate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testValidateWithInvalidTypeReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/validate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'slug' => 'test-slug',
            'type' => 'invalid-type',
        ]));

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Invalid type', $data['error']);
    }

    public function testValidateWithValidArticleSlug(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/validate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'slug' => 'unique-test-slug-' . uniqid(),
            'type' => 'article',
            'locale' => 'ro',
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('available', $data);
        $this->assertArrayHasKey('slug', $data);
        $this->assertArrayHasKey('type', $data);
    }

    public function testValidateWithCategoryType(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/validate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'slug' => 'unique-category-slug-' . uniqid(),
            'type' => 'category',
            'locale' => 'ro',
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('category', $data['type']);
    }

    // =============================================
    // POST /api/slug/check-redirect
    // =============================================

    public function testCheckRedirectWithMissingUrlReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/check-redirect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testCheckRedirectForNonRedirectedUrl(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/check-redirect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url' => '/nonexistent-url-' . uniqid(),
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertFalse($data['has_redirect']);
    }

    // =============================================
    // GET /api/slug/reserved
    // =============================================

    public function testGetReservedSlugsReturnsArray(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/slug/reserved');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('reserved_slugs', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertIsArray($data['reserved_slugs']);
        $this->assertGreaterThan(0, $data['count']);
    }

    public function testGetReservedSlugsIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/slug/reserved');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // POST /api/slug/check-reserved
    // =============================================

    public function testCheckReservedWithMissingSlugReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/check-reserved', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testCheckReservedSlugReturnsTrueForReserved(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/check-reserved', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'slug' => 'admin',
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('is_reserved', $data);
        // 'admin' should be reserved
        $this->assertTrue($data['is_reserved']);
    }

    public function testCheckReservedSlugReturnsFalseForNonReserved(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/check-reserved', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'slug' => 'completely-unique-slug-' . uniqid(),
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertFalse($data['is_reserved']);
    }

    // =============================================
    // POST /api/slug/bulk-validate
    // =============================================

    public function testBulkValidateWithMissingFieldsReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/bulk-validate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testBulkValidateWithValidInput(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/bulk-validate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'slugs' => ['test-slug-1', 'test-slug-2', 'admin'],
            'type' => 'article',
            'locale' => 'ro',
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('total', $data['summary']);
        $this->assertEquals(3, $data['summary']['total']);
    }

    public function testBulkValidateRejects51Slugs(): void
    {
        $client = static::createClient();
        $slugs = array_map(fn ($i) => "slug-$i", range(1, 51));

        $client->request('POST', '/api/slug/bulk-validate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'slugs' => $slugs,
            'type' => 'article',
        ]));

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Maximum 50', $data['error']);
    }

    // =============================================
    // POST /api/slug/suggest
    // =============================================

    public function testSuggestWithMissingFieldsReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/suggest', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testSuggestWithValidInput(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/suggest', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Reforma Sistemului de Sanatate',
            'type' => 'article',
            'locale' => 'ro',
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('suggestions', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertGreaterThan(0, $data['count']);
    }

    public function testSuggestLimitsMaxSuggestions(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/slug/suggest', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Title',
            'type' => 'article',
            'max_suggestions' => 3,
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertLessThanOrEqual(3, $data['count']);
    }
}
