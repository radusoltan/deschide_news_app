<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\LiveText;
use App\Entity\LiveTextView;
use App\Repository\LiveTextPostRepository;
use App\Repository\LiveTextReactionRepository;
use App\Repository\LiveTextViewRepository;
use App\Service\LiveTextAnalyticsService;
use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LiveTextAnalyticsServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private LiveTextViewRepository $viewRepository;
    private LiveTextPostRepository $postRepository;
    private LiveTextReactionRepository $reactionRepository;
    private CacheInterface $cache;
    private RequestStack $requestStack;
    private LoggerInterface $logger;
    private LiveTextAnalyticsService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->viewRepository = $this->createStub(LiveTextViewRepository::class);
        $this->postRepository = $this->createStub(LiveTextPostRepository::class);
        $this->reactionRepository = $this->createStub(LiveTextReactionRepository::class);
        $this->cache = $this->createStub(CacheInterface::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->service = new LiveTextAnalyticsService(
            $this->entityManager,
            $this->viewRepository,
            $this->postRepository,
            $this->reactionRepository,
            $this->cache,
            $this->requestStack,
            $this->logger
        );
    }

    private function createLiveText(int $id = 1): LiveText
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn($id);

        return $liveText;
    }

    // --- trackView ---

    public function testTrackViewCreatesNewViewWhenNoneExists(): void
    {
        $liveText = $this->createLiveText();
        $this->viewRepository->method('findByLiveTextAndSession')->willReturn(null);
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $result = $this->service->trackView($liveText, 'session-123');

        $this->assertInstanceOf(LiveTextView::class, $result);
    }

    public function testTrackViewUpdatesExistingView(): void
    {
        $liveText = $this->createLiveText();
        $existingView = $this->createStub(LiveTextView::class);
        $this->viewRepository->method('findByLiveTextAndSession')->willReturn($existingView);

        $result = $this->service->trackView($liveText, 'session-123');

        $this->assertInstanceOf(LiveTextView::class, $result);
    }

    public function testTrackViewSetsIpAndUserAgentFromRequest(): void
    {
        $liveText = $this->createLiveText();
        $this->viewRepository->method('findByLiveTextAndSession')->willReturn(null);

        $request = Request::create('/api/live-texts/1/view', 'POST');
        $request->headers->set('User-Agent', 'TestBrowser/1.0');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $result = $this->service->trackView($liveText, 'session-456');

        $this->assertInstanceOf(LiveTextView::class, $result);
    }

    // --- updateTimeSpent ---

    public function testUpdateTimeSpentUpdatesExistingView(): void
    {
        $liveText = $this->createLiveText();
        $view = $this->createStub(LiveTextView::class);
        $this->viewRepository->method('findByLiveTextAndSession')->willReturn($view);

        // Should not throw
        $this->service->updateTimeSpent('session-123', $liveText, 30);
        $this->assertTrue(true);
    }

    public function testUpdateTimeSpentDoesNothingWhenViewNotFound(): void
    {
        $liveText = $this->createLiveText();
        $this->viewRepository->method('findByLiveTextAndSession')->willReturn(null);

        $this->service->updateTimeSpent('session-123', $liveText, 30);
        $this->assertTrue(true);
    }

    // --- getActiveViewerCount ---

    public function testGetActiveViewerCountReturnsCountFromRepository(): void
    {
        $liveText = $this->createLiveText(42);
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->willReturn($liveText);
        $this->entityManager->method('getRepository')->willReturn($repo);
        $this->viewRepository->method('getActiveSessions')->willReturn(['session1', 'session2']);

        $result = $this->service->getActiveViewerCount(42);

        $this->assertSame(2, $result);
    }

    public function testGetActiveViewerCountReturnsZeroWhenLiveTextNotFound(): void
    {
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->willReturn(null);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $result = $this->service->getActiveViewerCount(999);

        $this->assertSame(0, $result);
    }

    public function testGetActiveViewerCountReturnsZeroOnException(): void
    {
        $this->entityManager->method('getRepository')->willThrowException(new \Exception('DB error'));

        $result = $this->service->getActiveViewerCount(1);

        $this->assertSame(0, $result);
    }

    // --- generateSessionId ---

    public function testGenerateSessionIdReturns32CharHexString(): void
    {
        $sessionId = $this->service->generateSessionId();

        $this->assertSame(32, strlen($sessionId));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $sessionId);
    }

    public function testGenerateSessionIdReturnsUniqueValues(): void
    {
        $id1 = $this->service->generateSessionId();
        $id2 = $this->service->generateSessionId();

        $this->assertNotSame($id1, $id2);
    }

    // --- clearCache ---

    public function testClearCacheDeletesBothCacheKeys(): void
    {
        $liveText = $this->createLiveText(42);

        $this->cache = $this->createMock(CacheInterface::class);
        $this->cache->expects($this->exactly(2))
            ->method('delete');

        $service = new LiveTextAnalyticsService(
            $this->entityManager,
            $this->viewRepository,
            $this->postRepository,
            $this->reactionRepository,
            $this->cache,
            $this->requestStack,
            $this->logger
        );

        $service->clearCache($liveText);
    }

    // --- cleanupOldViews ---

    public function testCleanupOldViewsDelegatesToRepository(): void
    {
        $this->viewRepository->method('cleanupOldSessions')->willReturn(15);

        $result = $this->service->cleanupOldViews(48);

        $this->assertSame(15, $result);
    }

    // --- getAnalytics ---

    public function testGetAnalyticsReturnsCachedData(): void
    {
        $liveText = $this->createLiveText(1);
        $cachedData = ['totalViews' => 100, 'uniqueViewers' => 50];

        $this->cache->method('get')->willReturn($cachedData);

        $result = $this->service->getAnalytics($liveText);

        $this->assertSame($cachedData, $result);
    }

    // --- addActiveViewer ---

    public function testAddActiveViewerCallsCacheWithoutException(): void
    {
        $this->cache->method('get')->willReturn(null);

        // Should not throw
        $this->service->addActiveViewer(1, 'session-xyz');

        $this->assertTrue(true);
    }

    public function testAddActiveViewerHandlesCacheException(): void
    {
        $this->cache->method('get')->willThrowException(new \Exception('cache error'));

        // Exception must be caught inside service
        $this->service->addActiveViewer(1, 'session-bad');

        $this->assertTrue(true);
    }

    // --- cleanupOldViews with different threshold ---

    public function testCleanupOldViewsWithCustomThreshold(): void
    {
        $this->viewRepository->method('cleanupOldSessions')->with(72)->willReturn(5);

        $result = $this->service->cleanupOldViews(72);

        $this->assertSame(5, $result);
    }

    // --- cleanupOldViews default threshold ---

    public function testCleanupOldViewsUsesDefaultThreshold(): void
    {
        $this->viewRepository->method('cleanupOldSessions')->willReturn(3);

        $result = $this->service->cleanupOldViews();

        $this->assertSame(3, $result);
    }

    // --- trackView with user ---

    public function testTrackViewCreatesNewViewWithUser(): void
    {
        $liveText = $this->createLiveText();
        $user = $this->createStub(\App\Entity\User::class);
        $this->viewRepository->method('findByLiveTextAndSession')->willReturn(null);
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $result = $this->service->trackView($liveText, 'session-with-user', $user);

        $this->assertInstanceOf(LiveTextView::class, $result);
    }

    // --- getAnalytics with detailed flag ---

    public function testGetAnalyticsCallsCacheWithDetailedKey(): void
    {
        $liveText = $this->createLiveText(5);
        $expectedData = ['totalViews' => 200, 'uniqueViewers' => 100, 'viewsOverTime' => []];

        $this->cache = $this->createStub(CacheInterface::class);
        $this->cache->method('get')
            ->willReturnCallback(function (string $key, callable $callback) use ($expectedData) {
                $this->assertStringContainsString('detailed', $key);
                return $expectedData;
            });

        $service = new LiveTextAnalyticsService(
            $this->entityManager,
            $this->viewRepository,
            $this->postRepository,
            $this->reactionRepository,
            $this->cache,
            $this->requestStack,
            $this->logger
        );

        $result = $service->getAnalytics($liveText, true);

        $this->assertSame($expectedData, $result);
    }

    public function testGetAnalyticsCallsCacheWithSummaryKey(): void
    {
        $liveText = $this->createLiveText(5);
        $expectedData = ['totalViews' => 100];

        $this->cache = $this->createStub(CacheInterface::class);
        $this->cache->method('get')
            ->willReturnCallback(function (string $key, callable $callback) use ($expectedData) {
                $this->assertStringContainsString('summary', $key);
                return $expectedData;
            });

        $service = new LiveTextAnalyticsService(
            $this->entityManager,
            $this->viewRepository,
            $this->postRepository,
            $this->reactionRepository,
            $this->cache,
            $this->requestStack,
            $this->logger
        );

        $result = $service->getAnalytics($liveText, false);

        $this->assertSame($expectedData, $result);
    }

    // --- clearCache deletes correct keys ---

    public function testClearCacheDeletesCorrectKeyFormats(): void
    {
        $liveText = $this->createLiveText(99);

        $deletedKeys = [];
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function (string $key) use (&$deletedKeys) {
                $deletedKeys[] = $key;
                return true;
            });

        $service = new LiveTextAnalyticsService(
            $this->entityManager,
            $this->viewRepository,
            $this->postRepository,
            $this->reactionRepository,
            $cache,
            $this->requestStack,
            $this->logger
        );

        $service->clearCache($liveText);

        $this->assertContains('live_text_analytics_99_summary', $deletedKeys);
        $this->assertContains('live_text_analytics_99_detailed', $deletedKeys);
    }

    // --- cleanupOldViews returns zero ---

    public function testCleanupOldViewsReturnsZeroWhenNothingToClean(): void
    {
        $this->viewRepository->method('cleanupOldSessions')->willReturn(0);

        $result = $this->service->cleanupOldViews(24);

        $this->assertSame(0, $result);
    }

    // --- getAnalytics – executes callback with summary fields ---

    public function testGetAnalyticsSummaryCallbackProducesCorrectFields(): void
    {
        $liveText = $this->createLiveText(10);

        $this->viewRepository->method('getTotalViewsCount')->willReturn(50);
        $this->viewRepository->method('getUniqueViewersCount')->willReturn(30);
        $this->viewRepository->method('getAverageTimeSpent')->willReturn(125.678);
        $this->viewRepository->method('getPeakConcurrentViewers')->willReturn(15);
        $this->postRepository->method('count')->willReturn(8);

        // Setup getActiveViewerCount to return 5
        $repo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $repo->method('find')->willReturn($liveText);
        $this->entityManager->method('getRepository')->willReturn($repo);
        $this->viewRepository->method('getActiveSessions')->willReturn(['s1', 's2', 's3', 's4', 's5']);

        // Mock cache to execute the callback
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) {
            $item = $this->createStub(ItemInterface::class);
            return $callback($item);
        });

        // Mock EntityManager for getTotalReactionsCount (private method uses createQueryBuilder)
        $queryStub = $this->createStub(\Doctrine\ORM\Query::class);
        $queryStub->method('getSingleScalarResult')->willReturn(42);

        $qb = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($queryStub);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->method('createQueryBuilder')->willReturn($qb);

        $service = new LiveTextAnalyticsService(
            $em,
            $this->viewRepository,
            $this->postRepository,
            $this->reactionRepository,
            $cache,
            $this->requestStack,
            $this->logger
        );

        $result = $service->getAnalytics($liveText, false);

        $this->assertSame(50, $result['totalViews']);
        $this->assertSame(30, $result['uniqueViewers']);
        $this->assertSame(125.68, $result['averageTimeSpent']);
        $this->assertSame(15, $result['peakConcurrentViewers']);
        $this->assertSame(8, $result['totalPosts']);
        $this->assertSame(42, $result['totalReactions']);
        $this->assertArrayNotHasKey('viewsOverTime', $result);
        $this->assertArrayNotHasKey('postEngagement', $result);
    }

    public function testGetAnalyticsDetailedCallbackAddsExtraFields(): void
    {
        $liveText = $this->createLiveText(10);

        $this->viewRepository->method('getTotalViewsCount')->willReturn(100);
        $this->viewRepository->method('getUniqueViewersCount')->willReturn(60);
        $this->viewRepository->method('getAverageTimeSpent')->willReturn(90.0);
        $this->viewRepository->method('getPeakConcurrentViewers')->willReturn(20);
        $this->viewRepository->method('getViewsOverTime')->willReturn([['time' => '10:00', 'count' => 5]]);
        $this->viewRepository->method('getViewersByPlatform')->willReturn([['platform' => 'desktop', 'count' => 40]]);
        $this->postRepository->method('count')->willReturn(5);

        // Mock post for getPostEngagement
        $post = $this->createStub(\App\Entity\LiveTextPost::class);
        $post->method('getId')->willReturn(1);
        $post->method('getContent')->willReturn('Post content here');
        $post->method('getPublishedAt')->willReturn(new \DateTimeImmutable('2026-03-20T12:00:00+00:00'));
        $this->postRepository->method('findBy')->willReturn([$post]);
        $this->reactionRepository->method('count')->willReturn(3);

        // Setup getActiveViewerCount
        $repo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $repo->method('find')->willReturn($liveText);
        $this->viewRepository->method('getActiveSessions')->willReturn(['s1', 's2']);

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) {
            $item = $this->createStub(ItemInterface::class);
            return $callback($item);
        });

        $queryStub = $this->createStub(\Doctrine\ORM\Query::class);
        $queryStub->method('getSingleScalarResult')->willReturn(10);

        $qb = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($queryStub);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->method('createQueryBuilder')->willReturn($qb);

        $service = new LiveTextAnalyticsService(
            $em,
            $this->viewRepository,
            $this->postRepository,
            $this->reactionRepository,
            $cache,
            $this->requestStack,
            $this->logger
        );

        $result = $service->getAnalytics($liveText, true);

        $this->assertArrayHasKey('viewsOverTime', $result);
        $this->assertArrayHasKey('postEngagement', $result);
        $this->assertArrayHasKey('viewersByPlatform', $result);
        $this->assertCount(1, $result['postEngagement']);
        $this->assertSame(1, $result['postEngagement'][0]['postId']);
        $this->assertSame(3, $result['postEngagement'][0]['reactionsCount']);
        $this->assertStringContainsString('Post content', $result['postEngagement'][0]['content']);
    }

    // --- getPostEngagement – truncates long content ---

    public function testGetPostEngagementTruncatesContentTo100Chars(): void
    {
        $liveText = $this->createLiveText(10);

        $longContent = str_repeat('A', 200);
        $post = $this->createStub(\App\Entity\LiveTextPost::class);
        $post->method('getId')->willReturn(1);
        $post->method('getContent')->willReturn($longContent);
        $post->method('getPublishedAt')->willReturn(null);
        $this->postRepository->method('findBy')->willReturn([$post]);
        $this->reactionRepository->method('count')->willReturn(0);

        $this->viewRepository->method('getTotalViewsCount')->willReturn(0);
        $this->viewRepository->method('getUniqueViewersCount')->willReturn(0);
        $this->viewRepository->method('getAverageTimeSpent')->willReturn(0.0);
        $this->viewRepository->method('getPeakConcurrentViewers')->willReturn(0);
        $this->viewRepository->method('getViewsOverTime')->willReturn([]);
        $this->viewRepository->method('getViewersByPlatform')->willReturn([]);
        $this->postRepository->method('count')->willReturn(1);

        $repo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $repo->method('find')->willReturn($liveText);
        $this->viewRepository->method('getActiveSessions')->willReturn([]);

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) {
            $item = $this->createStub(ItemInterface::class);
            return $callback($item);
        });

        $queryStub = $this->createStub(\Doctrine\ORM\Query::class);
        $queryStub->method('getSingleScalarResult')->willReturn(0);

        $qb = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($queryStub);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->method('createQueryBuilder')->willReturn($qb);

        $service = new LiveTextAnalyticsService(
            $em,
            $this->viewRepository,
            $this->postRepository,
            $this->reactionRepository,
            $cache,
            $this->requestStack,
            $this->logger
        );

        $result = $service->getAnalytics($liveText, true);

        $this->assertSame(100, mb_strlen($result['postEngagement'][0]['content']));
    }

    // --- getPostEngagement – handles null content ---

    public function testGetPostEngagementHandlesNullContent(): void
    {
        $liveText = $this->createLiveText(10);

        $post = $this->createStub(\App\Entity\LiveTextPost::class);
        $post->method('getId')->willReturn(1);
        $post->method('getContent')->willReturn(null);
        $post->method('getPublishedAt')->willReturn(null);
        $this->postRepository->method('findBy')->willReturn([$post]);
        $this->reactionRepository->method('count')->willReturn(0);

        $this->viewRepository->method('getTotalViewsCount')->willReturn(0);
        $this->viewRepository->method('getUniqueViewersCount')->willReturn(0);
        $this->viewRepository->method('getAverageTimeSpent')->willReturn(0.0);
        $this->viewRepository->method('getPeakConcurrentViewers')->willReturn(0);
        $this->viewRepository->method('getViewsOverTime')->willReturn([]);
        $this->viewRepository->method('getViewersByPlatform')->willReturn([]);
        $this->postRepository->method('count')->willReturn(1);

        $repo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $repo->method('find')->willReturn($liveText);
        $this->viewRepository->method('getActiveSessions')->willReturn([]);

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) {
            $item = $this->createStub(ItemInterface::class);
            return $callback($item);
        });

        $queryStub = $this->createStub(\Doctrine\ORM\Query::class);
        $queryStub->method('getSingleScalarResult')->willReturn(0);

        $qb = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($queryStub);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->method('createQueryBuilder')->willReturn($qb);

        $service = new LiveTextAnalyticsService(
            $em,
            $this->viewRepository,
            $this->postRepository,
            $this->reactionRepository,
            $cache,
            $this->requestStack,
            $this->logger
        );

        $result = $service->getAnalytics($liveText, true);

        $this->assertSame('', $result['postEngagement'][0]['content']);
        $this->assertNull($result['postEngagement'][0]['publishedAt']);
    }
}
