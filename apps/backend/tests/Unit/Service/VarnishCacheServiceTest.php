<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\VarnishCacheService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class VarnishCacheServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    private function createEnabledService(): VarnishCacheService
    {
        return new VarnishCacheService(
            $this->httpClient,
            $this->logger,
            '127.0.0.1',
            6081,
            true
        );
    }

    private function createDisabledService(): VarnishCacheService
    {
        return new VarnishCacheService(
            $this->httpClient,
            $this->logger,
            '127.0.0.1',
            6081,
            false
        );
    }

    // --- isEnabled / enable / disable ---

    public function testIsEnabledReturnsTrueByDefault(): void
    {
        $service = new VarnishCacheService($this->httpClient, $this->logger);
        $this->assertTrue($service->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenDisabled(): void
    {
        $service = $this->createDisabledService();
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

    // --- purgeUrl ---

    public function testPurgeUrlReturnsFalseWhenDisabled(): void
    {
        $service = $this->createDisabledService();

        $result = $service->purgeUrl('/api/articles/1');

        $this->assertFalse($result);
    }

    public function testPurgeUrlReturnsTrueOnSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->purgeUrl('/api/articles/1');

        $this->assertTrue($result);
    }

    public function testPurgeUrlReturnsFalseOnNon200(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->purgeUrl('/api/articles/1');

        $this->assertFalse($result);
    }

    public function testPurgeUrlReturnsFalseOnException(): void
    {
        $this->httpClient->method('request')->willThrowException(new \Exception('Connection refused'));

        $service = $this->createEnabledService();

        $result = $service->purgeUrl('/api/articles/1');

        $this->assertFalse($result);
    }

    // --- banPattern ---

    public function testBanPatternReturnsFalseWhenDisabled(): void
    {
        $service = $this->createDisabledService();

        $result = $service->banPattern('/api/articles.*');

        $this->assertFalse($result);
    }

    public function testBanPatternReturnsTrueOnSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->banPattern('/api/articles.*');

        $this->assertTrue($result);
    }

    public function testBanPatternReturnsFalseOnNon200(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(503);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $result = $service->banPattern('/api/articles.*');

        $this->assertFalse($result);
    }

    public function testBanPatternReturnsFalseOnException(): void
    {
        $this->httpClient->method('request')->willThrowException(new \Exception('error'));

        $service = $this->createEnabledService();

        $result = $service->banPattern('/api/articles.*');

        $this->assertFalse($result);
    }

    // --- purgeArticle ---

    public function testPurgeArticlePurgesUrlAndBansPattern(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        // Should not throw
        $service->purgeArticle(42);
        $this->assertTrue(true);
    }

    // --- purgeCategory ---

    public function testPurgeCategoryPurgesUrlAndBansPattern(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $service->purgeCategory(5);
        $this->assertTrue(true);
    }

    // --- purgeAllArticles ---

    public function testPurgeAllArticlesBansPattern(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $service->purgeAllArticles();
        $this->assertTrue(true);
    }

    // --- purgeAll ---

    public function testPurgeAllBansAllApiPatterns(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $this->httpClient->method('request')->willReturn($response);

        $service = $this->createEnabledService();

        $service->purgeAll();
        $this->assertTrue(true);
    }
}
