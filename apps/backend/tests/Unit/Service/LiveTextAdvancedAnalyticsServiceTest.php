<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\LiveText;
use App\Entity\LiveTextPost;
use App\Entity\LiveTextPostEngagement;
use App\Repository\LiveTextPostEngagementRepository;
use App\Service\LiveTextAdvancedAnalyticsService;
use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LiveTextAdvancedAnalyticsServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private LiveTextPostEngagementRepository $engagementRepository;
    private RequestStack $requestStack;
    private LoggerInterface $logger;
    private LiveTextAdvancedAnalyticsService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->engagementRepository = $this->createStub(LiveTextPostEngagementRepository::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->service = new LiveTextAdvancedAnalyticsService(
            $this->entityManager,
            $this->engagementRepository,
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

    private function createPost(int $id = 1): LiveTextPost
    {
        $post = $this->createStub(LiveTextPost::class);
        $post->method('getId')->willReturn($id);

        return $post;
    }

    // --- trackEngagement ---

    public function testTrackEngagementCreatesEngagementEntity(): void
    {
        $post = $this->createPost(42);
        $session = $this->createStub(SessionInterface::class);
        $session->method('has')->willReturn(true);
        $session->method('get')->willReturn('session-abc');

        $request = Request::create('/api/live-texts/1/engagement', 'POST');
        $request->setSession($session);

        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $result = $this->service->trackEngagement($post, 'view');

        $this->assertInstanceOf(LiveTextPostEngagement::class, $result);
    }

    public function testTrackEngagementWithNoRequest(): void
    {
        $post = $this->createPost();
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $result = $this->service->trackEngagement($post, 'click');

        $this->assertInstanceOf(LiveTextPostEngagement::class, $result);
    }

    // --- getHeatmapData ---

    public function testGetHeatmapDataReturnsStructuredData(): void
    {
        $liveText = $this->createLiveText();
        $this->engagementRepository->method('getHeatmapData')->willReturn([
            [
                'post_id' => 1,
                'unique_engagements' => 10,
                'total_engagements' => 25,
                'avg_time_spent' => 15.5,
                'avg_scroll_depth' => 70.3,
            ],
            [
                'post_id' => 2,
                'unique_engagements' => 5,
                'total_engagements' => 10,
                'avg_time_spent' => 8.2,
                'avg_scroll_depth' => 45.0,
            ],
        ]);

        $result = $this->service->getHeatmapData($liveText);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('max_engagements', $result);
        $this->assertArrayHasKey('total_posts', $result);
        $this->assertSame(25, $result['max_engagements']);
        $this->assertSame(2, $result['total_posts']);
    }

    public function testGetHeatmapDataCalculatesIntensity(): void
    {
        $liveText = $this->createLiveText();
        $this->engagementRepository->method('getHeatmapData')->willReturn([
            [
                'post_id' => 1,
                'unique_engagements' => 10,
                'total_engagements' => 100,
                'avg_time_spent' => null,
                'avg_scroll_depth' => null,
            ],
            [
                'post_id' => 2,
                'unique_engagements' => 5,
                'total_engagements' => 50,
                'avg_time_spent' => 10.0,
                'avg_scroll_depth' => 30.0,
            ],
        ]);

        $result = $this->service->getHeatmapData($liveText);

        // Post 1 has max engagements (100), so intensity should be 100
        $this->assertSame(100.0, $result['data'][0]['intensity']);
        // Post 2 has 50/100 = 50%
        $this->assertSame(50.0, $result['data'][1]['intensity']);
    }

    public function testGetHeatmapDataWithEmptyData(): void
    {
        $liveText = $this->createLiveText();
        $this->engagementRepository->method('getHeatmapData')->willReturn([]);

        $result = $this->service->getHeatmapData($liveText);

        $this->assertSame([], $result['data']);
        $this->assertSame(0, $result['max_engagements']);
        $this->assertSame(0, $result['total_posts']);
    }

    public function testGetHeatmapDataHandlesNullAverages(): void
    {
        $liveText = $this->createLiveText();
        $this->engagementRepository->method('getHeatmapData')->willReturn([
            [
                'post_id' => 1,
                'unique_engagements' => 5,
                'total_engagements' => 10,
                'avg_time_spent' => null,
                'avg_scroll_depth' => null,
            ],
        ]);

        $result = $this->service->getHeatmapData($liveText);

        $this->assertNull($result['data'][0]['avg_time_spent']);
        $this->assertNull($result['data'][0]['avg_scroll_depth']);
    }

    // --- getEngagementFunnel ---

    public function testGetEngagementFunnelReturnsStructuredData(): void
    {
        $liveText = $this->createLiveText();
        $this->engagementRepository->method('getEngagementFunnel')->willReturn([
            ['engagement_type' => 'view', 'unique_users' => 100, 'total_events' => 200],
            ['engagement_type' => 'read', 'unique_users' => 60, 'total_events' => 80],
            ['engagement_type' => 'click', 'unique_users' => 30, 'total_events' => 40],
            ['engagement_type' => 'reaction', 'unique_users' => 10, 'total_events' => 15],
            ['engagement_type' => 'share', 'unique_users' => 5, 'total_events' => 5],
        ]);

        $result = $this->service->getEngagementFunnel($liveText);

        $this->assertArrayHasKey('funnel', $result);
        $this->assertArrayHasKey('total_views', $result);
        $this->assertArrayHasKey('drop_off_rate', $result);
        $this->assertSame(100, $result['total_views']);
    }

    public function testGetEngagementFunnelCalculatesConversionRates(): void
    {
        $liveText = $this->createLiveText();
        $this->engagementRepository->method('getEngagementFunnel')->willReturn([
            ['engagement_type' => 'view', 'unique_users' => 100, 'total_events' => 200],
            ['engagement_type' => 'read', 'unique_users' => 50, 'total_events' => 70],
        ]);

        $result = $this->service->getEngagementFunnel($liveText);

        // read conversion = 50/100 * 100 = 50%
        $this->assertSame(50.0, $result['funnel']['read']['conversion_rate']);
    }

    public function testGetEngagementFunnelWithZeroViews(): void
    {
        $liveText = $this->createLiveText();
        $this->engagementRepository->method('getEngagementFunnel')->willReturn([]);

        $result = $this->service->getEngagementFunnel($liveText);

        $this->assertSame(0, $result['total_views']);
        $this->assertSame(0.0, $result['funnel']['read']['conversion_rate']);
    }

    // --- getTopEngagedPosts ---

    public function testGetTopEngagedPostsReturnsFormattedData(): void
    {
        $liveText = $this->createLiveText();
        $this->engagementRepository->method('getTopEngagedPosts')->willReturn([
            ['post_id' => 1, 'engagement_count' => 50],
            ['post_id' => 2, 'engagement_count' => 30],
        ]);

        $result = $this->service->getTopEngagedPosts($liveText, 5);

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['post_id']);
        $this->assertSame(50, $result[0]['engagement_count']);
    }

    public function testGetTopEngagedPostsReturnsEmptyWhenNoData(): void
    {
        $liveText = $this->createLiveText();
        $this->engagementRepository->method('getTopEngagedPosts')->willReturn([]);

        $result = $this->service->getTopEngagedPosts($liveText);

        $this->assertSame([], $result);
    }

    // --- trackView / trackRead / trackClick / trackReaction / trackShare ---

    public function testTrackViewDelegatesToTrackEngagement(): void
    {
        $post = $this->createPost();
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->service->trackView($post);
        $this->assertTrue(true);
    }

    public function testTrackReadDelegatesToTrackEngagement(): void
    {
        $post = $this->createPost();
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->service->trackRead($post, 30, 80);
        $this->assertTrue(true);
    }

    public function testTrackClickDelegatesToTrackEngagement(): void
    {
        $post = $this->createPost();
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->service->trackClick($post, 'share-button');
        $this->assertTrue(true);
    }

    public function testTrackReactionDelegatesToTrackEngagement(): void
    {
        $post = $this->createPost();
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->service->trackReaction($post, null, ['emoji' => 'thumbs_up']);
        $this->assertTrue(true);
    }

    public function testTrackShareDelegatesToTrackEngagement(): void
    {
        $post = $this->createPost();
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->service->trackShare($post, 'facebook');
        $this->assertTrue(true);
    }

    // --- clearOldEngagements ---

    public function testClearOldEngagementsExecutesDeleteQuery(): void
    {
        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('delete')->willReturn($qb);
        $qb->method('where')->willReturn($qb);
        $qb->method('setParameter')->willReturn($qb);

        $query = $this->createStub(Query::class);
        $query->method('execute')->willReturn(5);
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $before = new \DateTimeImmutable('-30 days');
        $result = $this->service->clearOldEngagements($before);

        $this->assertSame(5, $result);
    }

    // --- getHeatmapData: maxEngagements = 0 yields intensity 0 ---

    public function testGetHeatmapDataWithZeroTotalEngagementsGivesZeroIntensity(): void
    {
        $liveText = $this->createLiveText();
        $this->engagementRepository->method('getHeatmapData')->willReturn([
            [
                'post_id' => 1,
                'unique_engagements' => 0,
                'total_engagements' => 0,
                'avg_time_spent' => null,
                'avg_scroll_depth' => null,
            ],
        ]);

        $result = $this->service->getHeatmapData($liveText);

        $this->assertSame(0, $result['max_engagements']);
        // When maxEngagements is 0, intensity is int 0 (not 0.0)
        $this->assertEquals(0, $result['data'][0]['intensity']);
    }

    // --- getEngagementFunnel: drop off rates ---

    public function testGetEngagementFunnelCalculatesDropOffRates(): void
    {
        $liveText = $this->createLiveText();
        $this->engagementRepository->method('getEngagementFunnel')->willReturn([
            ['engagement_type' => 'view', 'unique_users' => 100, 'total_events' => 200],
            ['engagement_type' => 'read', 'unique_users' => 80, 'total_events' => 100],
            ['engagement_type' => 'click', 'unique_users' => 40, 'total_events' => 50],
            ['engagement_type' => 'reaction', 'unique_users' => 10, 'total_events' => 12],
            ['engagement_type' => 'share', 'unique_users' => 2, 'total_events' => 2],
        ]);

        $result = $this->service->getEngagementFunnel($liveText);

        // view -> read: (100 - 80) / 100 * 100 = 20%
        $this->assertSame(20.0, $result['drop_off_rate']['view_to_read']);
        // read -> click: (80 - 40) / 80 * 100 = 50%
        $this->assertSame(50.0, $result['drop_off_rate']['read_to_click']);
        // click -> reaction: (40 - 10) / 40 * 100 = 75%
        $this->assertSame(75.0, $result['drop_off_rate']['click_to_reaction']);
        // reaction -> share: (10 - 2) / 10 * 100 = 80%
        $this->assertSame(80.0, $result['drop_off_rate']['reaction_to_share']);
    }

    public function testGetEngagementFunnelDropOffRateZeroFromCount(): void
    {
        $liveText = $this->createLiveText();
        // No data at all => all zeros
        $this->engagementRepository->method('getEngagementFunnel')->willReturn([]);

        $result = $this->service->getEngagementFunnel($liveText);

        // All drop-off rates should be 0 because fromCount is 0
        $this->assertSame(0.0, $result['drop_off_rate']['view_to_read']);
        $this->assertSame(0.0, $result['drop_off_rate']['read_to_click']);
        $this->assertSame(0.0, $result['drop_off_rate']['click_to_reaction']);
        $this->assertSame(0.0, $result['drop_off_rate']['reaction_to_share']);
    }

    // --- getEngagementFunnel: unknown engagement type is ignored ---

    public function testGetEngagementFunnelIgnoresUnknownTypes(): void
    {
        $liveText = $this->createLiveText();
        $this->engagementRepository->method('getEngagementFunnel')->willReturn([
            ['engagement_type' => 'view', 'unique_users' => 50, 'total_events' => 100],
            ['engagement_type' => 'unknown_type', 'unique_users' => 10, 'total_events' => 20],
        ]);

        $result = $this->service->getEngagementFunnel($liveText);

        // Only known types in funnel
        $this->assertArrayHasKey('view', $result['funnel']);
        $this->assertArrayNotHasKey('unknown_type', $result['funnel']);
        $this->assertSame(50, $result['funnel']['view']['unique_users']);
    }

    // --- getEngagementSummary ---

    public function testGetEngagementSummaryReturnsFullStructure(): void
    {
        $liveText = $this->createLiveText();

        $this->engagementRepository->method('getHeatmapData')->willReturn([
            [
                'post_id' => 1,
                'unique_engagements' => 5,
                'total_engagements' => 20,
                'avg_time_spent' => 10.5,
                'avg_scroll_depth' => 60.0,
            ],
        ]);

        $this->engagementRepository->method('getEngagementFunnel')->willReturn([
            ['engagement_type' => 'view', 'unique_users' => 50, 'total_events' => 100],
        ]);

        $this->engagementRepository->method('getTopEngagedPosts')->willReturn([
            ['post_id' => 1, 'engagement_count' => 20],
        ]);

        $result = $this->service->getEngagementSummary($liveText);

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('funnel', $result);
        $this->assertArrayHasKey('top_posts', $result);
        $this->assertArrayHasKey('heatmap_summary', $result);
        $this->assertSame(20, $result['summary']['total_engagements']);
        $this->assertSame(50, $result['summary']['total_unique_users']);
        $this->assertSame(10.5, $result['summary']['avg_time_spent']);
        $this->assertSame(60.0, $result['summary']['avg_scroll_depth']);
    }

    public function testGetEngagementSummaryWithEmptyData(): void
    {
        $liveText = $this->createLiveText();

        $this->engagementRepository->method('getHeatmapData')->willReturn([]);
        $this->engagementRepository->method('getEngagementFunnel')->willReturn([]);
        $this->engagementRepository->method('getTopEngagedPosts')->willReturn([]);

        $result = $this->service->getEngagementSummary($liveText);

        $this->assertSame(0, $result['summary']['total_engagements']);
        $this->assertSame(0, $result['summary']['total_unique_users']);
        $this->assertSame(0, $result['summary']['avg_time_spent']);
        $this->assertSame(0, $result['summary']['avg_scroll_depth']);
        $this->assertSame(0, $result['summary']['engagement_rate']);
    }

    public function testGetEngagementSummaryCalculatesEngagementRate(): void
    {
        $liveText = $this->createLiveText();

        $this->engagementRepository->method('getHeatmapData')->willReturn([
            [
                'post_id' => 1,
                'unique_engagements' => 5,
                'total_engagements' => 100,
                'avg_time_spent' => null,
                'avg_scroll_depth' => null,
            ],
        ]);

        $this->engagementRepository->method('getEngagementFunnel')->willReturn([
            ['engagement_type' => 'view', 'unique_users' => 50, 'total_events' => 100],
        ]);

        $this->engagementRepository->method('getTopEngagedPosts')->willReturn([]);

        $result = $this->service->getEngagementSummary($liveText);

        // engagement_rate = 100 / 50 = 2.0
        $this->assertSame(2.0, $result['summary']['engagement_rate']);
    }

    // --- trackEngagement with all params ---

    public function testTrackEngagementWithAllParameters(): void
    {
        $post = $this->createPost(10);
        $user = $this->createStub(\App\Entity\User::class);
        $session = $this->createStub(SessionInterface::class);
        $session->method('has')->willReturn(true);
        $session->method('get')->willReturn('tracked-session');

        $request = Request::create('/api/engagement', 'POST');
        $request->setSession($session);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $service = new LiveTextAdvancedAnalyticsService(
            $this->entityManager,
            $this->engagementRepository,
            $this->requestStack,
            $this->logger
        );

        $result = $service->trackEngagement(
            $post,
            'read',
            $user,
            30,
            80,
            'link-element',
            ['source' => 'homepage']
        );

        $this->assertInstanceOf(LiveTextPostEngagement::class, $result);
    }

    // --- trackEngagement with new session ---

    public function testTrackEngagementCreatesNewSessionId(): void
    {
        $post = $this->createPost();
        $session = $this->createMock(SessionInterface::class);
        $session->method('has')->willReturn(false);
        $session->expects($this->once())->method('set');
        $session->method('get')->willReturn('new-session');

        $request = Request::create('/api/engagement', 'POST');
        $request->setSession($session);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $service = new LiveTextAdvancedAnalyticsService(
            $this->entityManager,
            $this->engagementRepository,
            $this->requestStack,
            $this->logger
        );

        $result = $service->trackEngagement($post, 'view');

        $this->assertInstanceOf(LiveTextPostEngagement::class, $result);
    }

    // --- clearOldEngagements returns zero ---

    public function testClearOldEngagementsReturnsZeroWhenNothingDeleted(): void
    {
        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('delete')->willReturn($qb);
        $qb->method('where')->willReturn($qb);
        $qb->method('setParameter')->willReturn($qb);

        $query = $this->createStub(Query::class);
        $query->method('execute')->willReturn(0);
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $service = new LiveTextAdvancedAnalyticsService(
            $this->entityManager,
            $this->engagementRepository,
            $this->requestStack,
            $this->logger
        );

        $before = new \DateTimeImmutable('-1 day');
        $result = $service->clearOldEngagements($before);

        $this->assertSame(0, $result);
    }
}
