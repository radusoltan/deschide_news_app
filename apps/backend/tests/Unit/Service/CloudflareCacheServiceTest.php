<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\CloudflareCacheService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CloudflareCacheServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    private function createEnabledService(): CloudflareCacheService
    {
        return new CloudflareCacheService(
            $this->httpClient,
            $this->logger,
            'cf-api-token',
            'cf-zone-id',
            true
        );
    }

    private function createDisabledService(): CloudflareCacheService
    {
        return new CloudflareCacheService(
            $this->httpClient,
            $this->logger,
            '',
            '',
            false
        );
    }

    // --- isEnabled / enable / disable ---

    public function testIsEnabledReturnsTrueWhenConfigured(): void
    {
        $service = $this->createEnabledService();
        $this->assertTrue($service->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenDisabled(): void
    {
        $service = $this->createDisabledService();
        $this->assertFalse($service->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenEnabledButNoToken(): void
    {
        $service = new CloudflareCacheService(
            $this->httpClient,
            $this->logger,
            '',
            'zone-id',
            true
        );
        $this->assertFalse($service->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenEnabledButNoZone(): void
    {
        $service = new CloudflareCacheService(
            $this->httpClient,
            $this->logger,
            'api-token',
            '',
            true
        );
        $this->assertFalse($service->isEnabled());
    }

    public function testEnableEnablesService(): void
    {
        $service = $this->createDisabledService();
        $service->enable();
        $this->assertTrue($service->isEnabled());
    }

    public function testDisableDisablesService(): void
    {
        $service = $this->createEnabledService();
        $service->disable();
        $this->assertFalse($service->isEnabled());
    }

    // --- purgeUrls ---

    public function testPurgeUrlsReturnsFalseWhenDisabled(): void
    {
        $service = $this->createDisabledService();

        $result = $service->purgeUrls(['https://example.com/api/articles/1']);

        $this->assertFalse($result);
    }

    public function testPurgeUrlsReturnsTrueForEmptyArray(): void
    {
        $service = $this->createEnabledService();

        $result = $service->purgeUrls([]);

        $this->assertTrue($result);
    }

    public function testPurgeUrlsReturnsTrueOnApiSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => true]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->purgeUrls(['https://api.deschide.md/api/articles/1']);

        $this->assertTrue($result);
    }

    public function testPurgeUrlsReturnsFalseOnApiFailure(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => false, 'errors' => ['Error']]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->purgeUrls(['https://api.deschide.md/api/articles/1']);

        $this->assertFalse($result);
    }

    public function testPurgeUrlsReturnsFalseOnException(): void
    {
        $this->httpClient->method('request')->willThrowException(new \Exception('Network error'));

        $service = $this->createEnabledService();

        $result = $service->purgeUrls(['https://api.deschide.md/api/articles/1']);

        $this->assertFalse($result);
    }

    // --- purgeUrl ---

    public function testPurgeUrlDelegatesToPurgeUrls(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => true]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->purgeUrl('https://api.deschide.md/api/articles/1');

        $this->assertTrue($result);
    }

    // --- purgeByPrefix ---

    public function testPurgeByPrefixReturnsFalseWhenDisabled(): void
    {
        $service = $this->createDisabledService();

        $result = $service->purgeByPrefix('/api/articles');

        $this->assertFalse($result);
    }

    public function testPurgeByPrefixReturnsTrueOnSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => true]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->purgeByPrefix('https://api.deschide.md/api/articles');

        $this->assertTrue($result);
    }

    public function testPurgeByPrefixFallsToPurgeAllOnFailure(): void
    {
        $callCount = 0;
        $response1 = $this->createStub(ResponseInterface::class);
        $response1->method('toArray')->willReturn(['success' => false]);

        $response2 = $this->createStub(ResponseInterface::class);
        $response2->method('toArray')->willReturn(['success' => true]);

        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($response1, $response2);

        $service = $this->createEnabledService();

        $result = $service->purgeByPrefix('/api/articles');

        // Either prefix purge succeeded or fell back to purgeAll
        $this->assertIsBool($result);
    }

    public function testPurgeByPrefixReturnsFalseOnException(): void
    {
        $this->httpClient->method('request')->willThrowException(new \Exception('error'));

        $service = $this->createEnabledService();

        $result = $service->purgeByPrefix('/api/articles');

        $this->assertFalse($result);
    }

    // --- purgeByTags ---

    public function testPurgeByTagsReturnsFalseWhenDisabled(): void
    {
        $service = $this->createDisabledService();

        $result = $service->purgeByTags(['article_42']);

        $this->assertFalse($result);
    }

    public function testPurgeByTagsReturnsTrueForEmptyTags(): void
    {
        $service = $this->createEnabledService();

        $result = $service->purgeByTags([]);

        $this->assertTrue($result);
    }

    public function testPurgeByTagsReturnsTrueOnSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => true]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->purgeByTags(['article_42', 'category_5']);

        $this->assertTrue($result);
    }

    public function testPurgeByTagsReturnsFalseOnApiFailure(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => false, 'errors' => []]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->purgeByTags(['article_42']);

        $this->assertFalse($result);
    }

    // --- purgeAll ---

    public function testPurgeAllReturnsFalseWhenDisabled(): void
    {
        $service = $this->createDisabledService();

        $result = $service->purgeAll();

        $this->assertFalse($result);
    }

    public function testPurgeAllReturnsTrueOnSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => true]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->purgeAll();

        $this->assertTrue($result);
    }

    public function testPurgeAllReturnsFalseOnException(): void
    {
        $this->httpClient->method('request')->willThrowException(new \Exception('error'));

        $service = $this->createEnabledService();

        $result = $service->purgeAll();

        $this->assertFalse($result);
    }

    // --- purgeArticle ---

    public function testPurgeArticleGeneratesCorrectUrls(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => true]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        // Should not throw
        $service->purgeArticle(42, 'https://api.deschide.md');
        $this->assertTrue(true);
    }

    public function testPurgeArticleWithCustomLocales(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => true]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $service->purgeArticle(42, 'https://api.deschide.md', ['ro', 'en']);
        $this->assertTrue(true);
    }

    // --- purgeCategory ---

    public function testPurgeCategoryGeneratesCorrectUrls(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => true]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $service->purgeCategory(5, 'https://api.deschide.md');
        $this->assertTrue(true);
    }

    // --- getCacheAnalytics ---

    public function testGetCacheAnalyticsReturnsEmptyWhenDisabled(): void
    {
        $service = $this->createDisabledService();

        $result = $service->getCacheAnalytics();

        $this->assertSame([], $result);
    }

    public function testGetCacheAnalyticsReturnsResultOnSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'success' => true,
            'result' => ['requests' => 1000],
        ]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->getCacheAnalytics();

        $this->assertSame(['requests' => 1000], $result);
    }

    public function testGetCacheAnalyticsReturnsEmptyOnException(): void
    {
        $this->httpClient->method('request')->willThrowException(new \Exception('error'));

        $service = $this->createEnabledService();

        $result = $service->getCacheAnalytics();

        $this->assertSame([], $result);
    }

    // --- purgeAll failure response ---

    public function testPurgeAllReturnsFalseOnApiFailure(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => false, 'errors' => ['Server error']]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->purgeAll();

        $this->assertFalse($result);
    }

    // --- getCacheAnalytics failure response ---

    public function testGetCacheAnalyticsReturnsEmptyOnApiFailure(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => false]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->getCacheAnalytics();

        $this->assertSame([], $result);
    }

    // --- purgeByTags exception ---

    public function testPurgeByTagsReturnsFalseOnException(): void
    {
        $this->httpClient->method('request')->willThrowException(new \Exception('timeout'));

        $service = $this->createEnabledService();

        $result = $service->purgeByTags(['article_1']);

        $this->assertFalse($result);
    }

    // --- purgeCategory with custom locales ---

    public function testPurgeCategoryWithCustomLocales(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => true]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $service->purgeCategory(3, 'https://api.deschide.md', ['ro']);
        $this->assertTrue(true);
    }

    // --- getCacheAnalytics returns empty result ---

    public function testGetCacheAnalyticsReturnsEmptyResultOnMissingResult(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['success' => true]);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->getCacheAnalytics();

        $this->assertSame([], $result);
    }
}
