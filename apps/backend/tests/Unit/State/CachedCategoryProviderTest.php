<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Category;
use App\Service\Cache\CacheService;
use App\State\CachedCategoryProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CachedCategoryProviderTest extends TestCase
{
    private CachedCategoryProvider $provider;
    private ProviderInterface $decorated;
    private CacheService $performance;
    private RequestStack $requestStack;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(ProviderInterface::class);
        $this->performance = $this->createMock(CacheService::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->provider = new CachedCategoryProvider(
            $this->decorated,
            $this->performance,
            $this->requestStack,
            $this->logger
        );
    }

    // ========================
    // Single Item Cache Tests
    // ========================

    #[Test]
    public function itReturnsCachedCategoryOnHit(): void
    {
        $this->setupRequest('ro');

        $cachedCategory = new Category();
        $this->performance->method('getCached')
            ->with('api:categories:1:ro')
            ->willReturn($cachedCategory);

        $this->decorated->expects($this->never())->method('provide');

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(Category::class, $result);
    }

    #[Test]
    public function itFetchesAndCachesCategoryOnMiss(): void
    {
        $this->setupRequest('ro');

        $this->performance->method('getCached')
            ->with('api:categories:1:ro')
            ->willReturn(null);

        $category = new Category();
        $this->decorated->method('provide')->willReturn($category);

        $this->performance->expects($this->once())
            ->method('setCached')
            ->with('api:categories:1:ro', $category, 3600);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(Category::class, $result);
    }

    #[Test]
    public function itDoesNotCacheNullCategory(): void
    {
        $this->setupRequest('ro');

        $this->performance->method('getCached')->willReturn(null);
        $this->decorated->method('provide')->willReturn(null);

        $this->performance->expects($this->never())->method('setCached');

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itUsesLocaleInCacheKey(): void
    {
        $this->setupRequest('en');

        $this->performance->expects($this->once())
            ->method('getCached')
            ->with('api:categories:1:en')
            ->willReturn(null);

        $this->decorated->method('provide')->willReturn(new Category());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ========================
    // Collection Cache Tests
    // ========================

    #[Test]
    public function itReturnsCachedCollectionOnHit(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['page' => '1']);

        $cachedResults = [new Category(), new Category()];
        $this->performance->method('getCached')->willReturn($cachedResults);

        $this->decorated->expects($this->never())->method('provide');

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function itFetchesAndCachesCollectionOnMiss(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag();

        $this->performance->method('getCached')->willReturn(null);

        $categories = [new Category()];
        $this->decorated->method('provide')->willReturn($categories);

        $this->performance->expects($this->once())
            ->method('setCached')
            ->with(
                $this->stringContains('api:categories:list:page1:ro:'),
                $categories,
                3600
            );

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
    }

    #[Test]
    public function itIncludesFiltersInCollectionCacheKey(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['status' => 'active', 'onFrontPage' => 'true']);

        $this->performance->expects($this->once())
            ->method('getCached')
            ->with($this->matchesRegularExpression('/api:categories:list:page1:ro:[a-f0-9]+/'))
            ->willReturn(null);

        $this->decorated->method('provide')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    // ========================
    // Locale Tests
    // ========================

    #[Test]
    public function itExtractsLocaleFromAcceptLanguageHeader(): void
    {
        $this->setupRequest('en-US');

        $this->performance->expects($this->once())
            ->method('getCached')
            ->with('api:categories:1:en')
            ->willReturn(null);

        $this->decorated->method('provide')->willReturn(new Category());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itDefaultsToRoLocaleWhenNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->performance->expects($this->once())
            ->method('getCached')
            ->with('api:categories:1:ro')
            ->willReturn(null);

        $this->decorated->method('provide')->willReturn(new Category());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ========================
    // Logging Tests
    // ========================

    #[Test]
    public function itLogsCacheHitForSingleCategory(): void
    {
        $this->setupRequest('ro');

        $this->performance->method('getCached')->willReturn(new Category());

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Cache HIT for category', $this->anything());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itLogsCacheMissForSingleCategory(): void
    {
        $this->setupRequest('ro');

        $this->performance->method('getCached')->willReturn(null);
        $this->decorated->method('provide')->willReturn(new Category());

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Cache MISS for category', $this->anything());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ========================
    // Helper Methods
    // ========================

    private function setupRequest(string $locale): Request
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => $locale]);
        $request->query = new InputBag();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        return $request;
    }
}
