<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\ShortLink;
use App\Tests\Functional\ApiTestCase;

/**
 * Functional tests for ShortLinkRedirectController.
 *
 * Endpoints tested:
 * - GET /s/{code}         (redirect to original URL)
 * - GET /s/{code}/preview (preview short link without redirect)
 *
 * These endpoints are under the "main" firewall (not /api),
 * so they are publicly accessible.
 */
class ShortLinkRedirectControllerTest extends ApiTestCase
{
    private function createTestShortLink(string $code = null): ShortLink
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        $shortLink = new ShortLink();
        $shortLink->setCode($code ?? 'test-' . uniqid());
        $shortLink->setOriginalUrl('https://example.com/test-article');
        $shortLink->setTitle('Test Short Link');
        $em->persist($shortLink);
        $em->flush();

        return $shortLink;
    }

    // =============================================
    // GET /s/{code} - Redirect
    // =============================================

    public function testRedirectWithValidCodeReturns301(): void
    {
        // Create client first, then create test data
        $client = static::createClient();

        $shortLink = $this->createTestShortLink();

        $client->request('GET', '/s/' . $shortLink->getCode());

        $this->assertResponseStatusCodeSame(301);
        $this->assertResponseHeaderSame('Location', 'https://example.com/test-article');
    }

    public function testRedirectWithNonExistentCodeReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/s/nonexistent-code-' . uniqid());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testRedirectHasNoCacheHeaders(): void
    {
        $client = static::createClient();

        $shortLink = $this->createTestShortLink();

        $client->request('GET', '/s/' . $shortLink->getCode());

        $response = $client->getResponse();
        $this->assertResponseStatusCodeSame(301);

        // Verify no-store cache policy for accurate stats
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function testRedirectRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/s/test-code');

        $this->assertResponseStatusCodeSame(405);
    }

    // =============================================
    // GET /s/{code}/preview - Preview
    // =============================================

    public function testPreviewWithValidCodeReturnsJson(): void
    {
        $client = static::createClient();

        $shortLink = $this->createTestShortLink();

        $client->request('GET', '/s/' . $shortLink->getCode() . '/preview');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotNull($data);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('originalUrl', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('clickCount', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertEquals($shortLink->getCode(), $data['code']);
        $this->assertEquals('https://example.com/test-article', $data['originalUrl']);
    }

    public function testPreviewWithNonExistentCodeReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/s/nonexistent-code-' . uniqid() . '/preview');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPreviewDoesNotRedirect(): void
    {
        $client = static::createClient();

        $shortLink = $this->createTestShortLink();

        $client->request('GET', '/s/' . $shortLink->getCode() . '/preview');

        // Should return 200 JSON, not 301 redirect
        $this->assertResponseStatusCodeSame(200);
    }

    public function testPreviewShowsArticleInfoWhenLinked(): void
    {
        $client = static::createClient();

        $shortLink = $this->createTestShortLink();

        $client->request('GET', '/s/' . $shortLink->getCode() . '/preview');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        // article field should be null since we didn't link to an article
        $this->assertNull($data['article']);
    }
}
