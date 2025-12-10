<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\UrlRedirect;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\UrlRedirectRepository;
use App\Service\ElasticService;
use App\Service\SlugLookupService;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SlugLookupServiceTest extends TestCase
{
    private SlugLookupService $service;

    private ElasticService $elasticService;

    private ArticleRepository $articleRepository;

    private CategoryRepository $categoryRepository;

    private UrlRedirectRepository $redirectRepository;

    private EntityManagerInterface $entityManager;

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->elasticService = $this->createMock(ElasticService::class);
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->redirectRepository = $this->createMock(UrlRedirectRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new SlugLookupService(
            $this->elasticService,
            $this->articleRepository,
            $this->categoryRepository,
            $this->redirectRepository,
            $this->entityManager,
            $this->logger
        );
    }

    #[Test]
    public function itReturnsListOfReservedSlugs(): void
    {
        $reservedSlugs = $this->service->getReservedSlugs();

        $this->assertIsArray($reservedSlugs);
        $this->assertCount(18, $reservedSlugs);  // 17 + 's' for short links
        $this->assertContains('admin', $reservedSlugs);
        $this->assertContains('s', $reservedSlugs);
        $this->assertContains('api', $reservedSlugs);
        $this->assertContains('search', $reservedSlugs);
    }

    #[Test]
    #[DataProvider('reservedSlugsProvider')]
    public function itDetectsReservedSlugs(string $slug): void
    {
        $isReserved = $this->service->isSlugReserved($slug);

        $this->assertTrue($isReserved, "Slug '{$slug}' should be reserved");
    }

    public static function reservedSlugsProvider(): Generator
    {
        yield 'all' => ['all'];
        yield 'search' => ['search'];
        yield 'trending' => ['trending'];
        yield 'admin' => ['admin'];
        yield 'api' => ['api'];
        yield 'login' => ['login'];
    }

    #[Test]
    #[DataProvider('nonReservedSlugsProvider')]
    public function itAllowsNonReservedSlugs(string $slug): void
    {
        $isReserved = $this->service->isSlugReserved($slug);

        $this->assertFalse($isReserved, "Slug '{$slug}' should not be reserved");
    }

    public static function nonReservedSlugsProvider(): Generator
    {
        yield 'politica' => ['politica'];
        yield 'economie' => ['economie'];
        yield 'sport' => ['sport'];
        yield 'cultura' => ['cultura'];
    }

    #[Test]
    public function itDetectsReservedSlugsCaseInsensitively(): void
    {
        $this->assertTrue($this->service->isSlugReserved('ADMIN'));
        $this->assertTrue($this->service->isSlugReserved('Admin'));
        $this->assertTrue($this->service->isSlugReserved('aDmIn'));
        $this->assertTrue($this->service->isSlugReserved('admin'));
    }

    #[Test]
    public function itChecksArticleSlugAvailabilityWhenNotUsed(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock(0));

        $available = $this->service->isSlugAvailable('unique-slug', 'article', 'ro');

        $this->assertTrue($available);
    }

    #[Test]
    public function itChecksArticleSlugAvailabilityWhenUsed(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock(1));

        $available = $this->service->isSlugAvailable('existing-slug', 'article', 'ro');

        $this->assertFalse($available);
    }

    #[Test]
    public function itExcludesIdWhenCheckingSlugAvailability(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock(0));

        $available = $this->service->isSlugAvailable('slug', 'article', 'ro', 123);

        $this->assertTrue($available);
    }

    #[Test]
    public function itChecksCategorySlugAvailability(): void
    {
        $this->categoryRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock(0));

        $available = $this->service->isSlugAvailable('category-slug', 'category', 'ro');

        $this->assertTrue($available);
    }

    #[Test]
    public function itResolvesRedirectChain(): void
    {
        $redirect1 = $this->createRedirect('/old1', '/old2', 10);
        $redirect2 = $this->createRedirect('/old2', '/old3', 5);
        $redirect3 = $this->createRedirect('/old3', '/final', 3);

        $this->redirectRepository
            ->method('findByOldUrl')
            ->willReturnCallback(function ($url) use ($redirect1, $redirect2, $redirect3) {
                return match ($url) {
                    '/old1' => $redirect1,
                    '/old2' => $redirect2,
                    '/old3' => $redirect3,
                    default => null,
                };
            });

        $chain = $this->service->getRedirectChain('/old1');

        $this->assertCount(3, $chain['redirects']);
        $this->assertEquals('/final', $chain['final_url']);
        $this->assertEquals(3, $chain['chain_length']);
        $this->assertFalse($chain['circular']);
    }

    #[Test]
    public function itDetectsCircularRedirects(): void
    {
        $redirect1 = $this->createRedirect('/a', '/b', 1);
        $redirect2 = $this->createRedirect('/b', '/c', 1);
        $redirect3 = $this->createRedirect('/c', '/a', 1); // Circular!

        $this->redirectRepository
            ->method('findByOldUrl')
            ->willReturnCallback(function ($url) use ($redirect1, $redirect2, $redirect3) {
                return match ($url) {
                    '/a' => $redirect1,
                    '/b' => $redirect2,
                    '/c' => $redirect3,
                    default => null,
                };
            });

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Circular redirect detected', $this->anything());

        $chain = $this->service->getRedirectChain('/a');

        $this->assertTrue($chain['circular']);
        $this->assertNull($chain['final_url']);
    }

    #[Test]
    public function itPreventsInfiniteLoopsWithMaxDepth(): void
    {
        // Create a very long chain (20 redirects)
        $this->redirectRepository
            ->method('findByOldUrl')
            ->willReturn($this->createRedirect('/current', '/next', 1));

        $chain = $this->service->getRedirectChain('/start');

        // Should stop at max depth (10)
        $this->assertLessThanOrEqual(10, $chain['chain_length']);
    }

    #[Test]
    public function itReturnsEmptyChainWhenNoRedirectsFound(): void
    {
        $this->redirectRepository
            ->method('findByOldUrl')
            ->willReturn(null);

        $chain = $this->service->getRedirectChain('/no-redirect');

        $this->assertEmpty($chain['redirects']);
        $this->assertNull($chain['final_url']);
        $this->assertEquals(0, $chain['chain_length']);
        $this->assertFalse($chain['circular']);
    }

    #[Test]
    public function itBulkValidatesMultipleSlugs(): void
    {
        $slugs = ['available-slug', 'used-slug', 'admin'];

        // Mock to return: available-slug=0 (available), used-slug=1 (used), admin=0 (available but reserved)
        // Note: isSlugAvailable is called twice per slug (once for 'available', once for 'valid')
        $callIndex = 0;
        $this->articleRepository
            ->method('createQueryBuilder')
            ->willReturnCallback(function () use (&$callIndex) {
                $count = match ($callIndex++) {
                    0 => 0, // available-slug: available (1st call)
                    1 => 0, // available-slug: available (2nd call for valid)
                    2 => 1, // used-slug: not available (1st call)
                    3 => 1, // used-slug: not available (2nd call for valid)
                    4 => 0, // admin: available in DB (1st call)
                    5 => 0, // admin: available in DB (2nd call for valid)
                    default => 0,
                };

                return $this->createQueryBuilderMock($count);
            });

        $results = $this->service->bulkValidate($slugs, 'article', 'ro');

        // Check available-slug: available and valid
        $this->assertArrayHasKey('available-slug', $results);
        $this->assertTrue($results['available-slug']['available']);
        $this->assertFalse($results['available-slug']['reserved']);
        $this->assertTrue($results['available-slug']['valid']);

        // Check used-slug: not available and not valid
        $this->assertArrayHasKey('used-slug', $results);
        $this->assertFalse($results['used-slug']['available']);
        $this->assertFalse($results['used-slug']['reserved']);
        $this->assertFalse($results['used-slug']['valid']);

        // Check admin: available in DB, but reserved, so invalid
        $this->assertArrayHasKey('admin', $results);
        $this->assertTrue($results['admin']['available']); // Can be available in DB
        $this->assertTrue($results['admin']['reserved']);  // But is reserved
        $this->assertFalse($results['admin']['valid']);    // So invalid overall
    }

    #[Test]
    public function itGeneratesSlugSuggestionsFromTitle(): void
    {
        // All suggestions should be available
        $this->articleRepository
            ->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock(0));

        $suggestions = $this->service->generateSlugSuggestions(
            'Reforma Sistemului de Sănătate',
            'article',
            'ro',
            5
        );

        $this->assertIsArray($suggestions);
        $this->assertCount(5, $suggestions);

        // First suggestion should be base slug
        $this->assertEquals('reforma-sistemului-de-sanatate', $suggestions[0]['slug']);
        $this->assertTrue($suggestions[0]['available']);
        $this->assertFalse($suggestions[0]['reserved']);
    }

    #[Test]
    public function itGeneratesAlternativeSlugsWhenBaseUnavailable(): void
    {
        // First call: base slug unavailable; others available
        $this->articleRepository
            ->method('createQueryBuilder')
            ->willReturnCallback(function () {
                static $callCount = 0;
                ++$callCount;

                return $this->createQueryBuilderMock($callCount === 1 ? 1 : 0);
            });

        $suggestions = $this->service->generateSlugSuggestions(
            'Test Title',
            'article',
            'ro',
            3
        );

        $this->assertCount(3, $suggestions);
        $this->assertFalse($suggestions[0]['available']); // base unavailable
        $this->assertTrue($suggestions[1]['available']); // -1 available
        $this->assertTrue($suggestions[2]['available']); // -2 available
    }

    // Helper methods

    private function createQueryBuilderMock(int $count)
    {
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getSingleScalarResult')->willReturn($count);
        $query->method('setHint')->willReturnSelf();

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('select')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    private function createRedirect(string $oldUrl, string $newUrl, int $hitCount): UrlRedirect
    {
        $redirect = new UrlRedirect();
        $redirect->setOldUrl($oldUrl);
        $redirect->setNewUrl($newUrl);
        $redirect->setLocale('ro');
        $redirect->setType('article');
        $redirect->setHitCount($hitCount);

        return $redirect;
    }
}
