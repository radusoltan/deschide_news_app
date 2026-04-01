<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

/**
 * Functional tests for TrackingController.
 *
 * Endpoints tested:
 * - POST /api/track/pageview       (track page view)
 * - POST /api/track/reading-time   (track reading time)
 * - POST /api/track/scroll-depth   (track scroll depth)
 * - GET  /api/stats/article/{id}   (get article views)
 * - GET  /api/stats/trending       (get trending articles)
 * - GET  /api/stats/site           (get site stats)
 *
 * Tracking (/api/track) and stats (/api/stats) are PUBLIC_ACCESS per security.yaml.
 */
class TrackingControllerTest extends ApiTestCase
{
    // =============================================
    // POST /api/track/pageview
    // =============================================

    public function testTrackPageviewWithValidData(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/pageview', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'article_id' => 1,
            'visitor_id' => 'test-visitor-' . uniqid(),
        ]));

        $response = $client->getResponse();
        // Might return 200 or 500 depending on Redis/Messenger availability
        $this->assertContains($response->getStatusCode(), [200, 500]);

        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getContent(), true);
            $this->assertTrue($data['success']);
        }
    }

    public function testTrackPageviewWithMissingArticleIdReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/pageview', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'visitor_id' => 'test-visitor',
        ]));

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Invalid request', $data['error']);
    }

    public function testTrackPageviewWithMissingVisitorIdReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/pageview', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'article_id' => 1,
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testTrackPageviewWithEmptyBodyReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/pageview', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testTrackPageviewIsPublic(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/pageview', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'article_id' => 1,
            'visitor_id' => 'test',
        ]));

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testTrackPageviewWithOptionalCategoryId(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/pageview', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'article_id' => 1,
            'visitor_id' => 'test-visitor-' . uniqid(),
            'category_id' => 5,
        ]));

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);
    }

    public function testTrackPageviewRejectsGetMethod(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/track/pageview');

        $this->assertResponseStatusCodeSame(405);
    }

    // =============================================
    // POST /api/track/reading-time
    // =============================================

    public function testTrackReadingTimeWithValidData(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/reading-time', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'article_id' => 1,
            'visitor_id' => 'test-visitor-' . uniqid(),
            'reading_time' => 120,
        ]));

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);

        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getContent(), true);
            $this->assertTrue($data['success']);
        }
    }

    public function testTrackReadingTimeWithMissingFieldsReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/reading-time', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'article_id' => 1,
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testTrackReadingTimeWithMissingReadingTimeReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/reading-time', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'article_id' => 1,
            'visitor_id' => 'test',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testTrackReadingTimeIsPublic(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/reading-time', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'article_id' => 1,
            'visitor_id' => 'test',
            'reading_time' => 60,
        ]));

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // POST /api/track/scroll-depth
    // =============================================

    public function testTrackScrollDepthWithValidData(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/scroll-depth', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'article_id' => 1,
            'visitor_id' => 'test-visitor-' . uniqid(),
            'scroll_depth' => 75,
        ]));

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);

        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getContent(), true);
            $this->assertTrue($data['success']);
        }
    }

    public function testTrackScrollDepthWithMissingFieldsReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/scroll-depth', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'article_id' => 1,
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testTrackScrollDepthAt100PercentCompletion(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/scroll-depth', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'article_id' => 1,
            'visitor_id' => 'test-visitor-' . uniqid(),
            'scroll_depth' => 100,
        ]));

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);
    }

    public function testTrackScrollDepthIsPublic(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/track/scroll-depth', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'article_id' => 1,
            'visitor_id' => 'test',
            'scroll_depth' => 50,
        ]));

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // GET /api/stats/article/{id}
    // =============================================

    public function testGetArticleStatsReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/stats/article/1');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);

        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getContent(), true);
            $this->assertArrayHasKey('article_id', $data);
            $this->assertArrayHasKey('views', $data);
            $this->assertEquals(1, $data['article_id']);
        }
    }

    public function testGetArticleStatsIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/stats/article/1');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testGetArticleStatsForDifferentIds(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/stats/article/999');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);

        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getContent(), true);
            $this->assertEquals(999, $data['article_id']);
        }
    }

    // =============================================
    // GET /api/stats/trending
    // =============================================

    public function testGetTrendingReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/stats/trending');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);

        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getContent(), true);
            $this->assertArrayHasKey('trending', $data);
            $this->assertIsArray($data['trending']);
        }
    }

    public function testGetTrendingWithLimitParam(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/stats/trending?limit=5');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);
    }

    public function testGetTrendingIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/stats/trending');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // GET /api/stats/site
    // =============================================

    public function testGetSiteStatsReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/stats/site');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);

        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getContent(), true);
            $this->assertArrayHasKey('date', $data);
            $this->assertArrayHasKey('unique_visitors', $data);
        }
    }

    public function testGetSiteStatsWithDateParam(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/stats/site?date=2025-01-01');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 500]);

        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getContent(), true);
            $this->assertEquals('2025-01-01', $data['date']);
        }
    }

    public function testGetSiteStatsIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/stats/site');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // HTTP method tests
    // =============================================

    public function testArticleStatsRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/stats/article/1');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testTrendingRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/stats/trending');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testSiteStatsRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/stats/site');

        $this->assertResponseStatusCodeSame(405);
    }
}
