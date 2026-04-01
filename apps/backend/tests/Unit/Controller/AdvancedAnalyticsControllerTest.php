<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\AdvancedAnalyticsController;
use App\Entity\LiveText;
use App\Entity\LiveTextPost;
use App\Entity\LiveTextPostEngagement;
use App\Entity\User;
use App\Service\LiveTextAdvancedAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Unit tests for AdvancedAnalyticsController.
 *
 * Covers: trackEngagement, getHeatmap, getFunnel, getTopPosts, getSummary,
 *         trackView, trackRead, trackClick, trackShare, clearOldEngagements.
 */
class AdvancedAnalyticsControllerTest extends TestCase
{
    private LiveTextAdvancedAnalyticsService $analyticsService;
    private EntityManagerInterface $entityManager;
    private AdvancedAnalyticsController $controller;
    private EntityRepository $liveTextPostRepo;
    private EntityRepository $liveTextRepo;
    private TokenStorageInterface $tokenStorage;
    private TokenInterface $token;

    protected function setUp(): void
    {
        $this->analyticsService = $this->createStub(LiveTextAdvancedAnalyticsService::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);

        $this->liveTextPostRepo = $this->createStub(EntityRepository::class);
        $this->liveTextRepo = $this->createStub(EntityRepository::class);

        $this->entityManager->method('getRepository')->willReturnCallback(
            function (string $class) {
                return match ($class) {
                    LiveTextPost::class => $this->liveTextPostRepo,
                    LiveText::class => $this->liveTextRepo,
                    default => $this->createStub(EntityRepository::class),
                };
            }
        );

        $this->controller = new AdvancedAnalyticsController(
            $this->analyticsService,
            $this->entityManager
        );

        // Set up container for AbstractController dependencies (json(), getUser())
        $this->tokenStorage = $this->createStub(TokenStorageInterface::class);
        $this->token = $this->createStub(TokenInterface::class);

        $paramBag = $this->createStub(ContainerBagInterface::class);
        $paramBag->method('get')->willReturnMap([
            ['kernel.debug', false],
        ]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function (string $id): bool {
            return match ($id) {
                'parameter_bag', 'security.token_storage' => true,
                default => false,
            };
        });
        $container->method('get')->willReturnCallback(function (string $id) use ($paramBag) {
            return match ($id) {
                'parameter_bag' => $paramBag,
                'security.token_storage' => $this->tokenStorage,
                default => null,
            };
        });

        $this->controller->setContainer($container);
    }

    private function setCurrentUser(?User $user): void
    {
        if ($user === null) {
            $this->tokenStorage->method('getToken')->willReturn(null);
        } else {
            $this->token->method('getUser')->willReturn($user);
            $this->tokenStorage->method('getToken')->willReturn($this->token);
        }
    }

    private function createPost(int $id): LiveTextPost
    {
        $post = new LiveTextPost();
        $reflection = new \ReflectionClass($post);
        $idProp = $reflection->getProperty('id');
        $idProp->setValue($post, $id);

        return $post;
    }

    private function createLiveText(int $id): LiveText
    {
        $liveText = new LiveText();
        $reflection = new \ReflectionClass($liveText);
        $idProp = $reflection->getProperty('id');
        $idProp->setValue($liveText, $id);

        return $liveText;
    }

    private function createEngagement(int $id): LiveTextPostEngagement
    {
        $engagement = new LiveTextPostEngagement();
        $reflection = new \ReflectionClass($engagement);
        $idProp = $reflection->getProperty('id');
        $idProp->setValue($engagement, $id);

        return $engagement;
    }

    // =============================================
    // POST /api/analytics/track
    // =============================================

    #[Test]
    public function trackEngagementReturnsBadRequestWhenMissingPostId(): void
    {
        $request = Request::create('/api/analytics/track', 'POST', [], [], [], [], json_encode([
            'engagement_type' => 'view',
        ]));

        $response = $this->controller->trackEngagement($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('post_id', $data['error']);
    }

    #[Test]
    public function trackEngagementReturnsBadRequestWhenMissingEngagementType(): void
    {
        $request = Request::create('/api/analytics/track', 'POST', [], [], [], [], json_encode([
            'post_id' => 1,
        ]));

        $response = $this->controller->trackEngagement($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('engagement_type', $data['error']);
    }

    #[Test]
    public function trackEngagementReturnsBadRequestWhenBothFieldsMissing(): void
    {
        $request = Request::create('/api/analytics/track', 'POST', [], [], [], [], json_encode([]));

        $response = $this->controller->trackEngagement($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function trackEngagementReturnsNotFoundWhenPostDoesNotExist(): void
    {
        $this->liveTextPostRepo->method('find')->willReturn(null);
        $this->setCurrentUser(null);

        $request = Request::create('/api/analytics/track', 'POST', [], [], [], [], json_encode([
            'post_id' => 999,
            'engagement_type' => 'view',
        ]));

        $response = $this->controller->trackEngagement($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Post not found', $data['error']);
    }

    #[Test]
    public function trackEngagementReturnsCreatedOnSuccess(): void
    {
        $post = $this->createPost(1);
        $engagement = $this->createEngagement(42);

        $this->liveTextPostRepo->method('find')->willReturn($post);
        $this->analyticsService->method('trackEngagement')->willReturn($engagement);
        $this->setCurrentUser(null);

        $request = Request::create('/api/analytics/track', 'POST', [], [], [], [], json_encode([
            'post_id' => 1,
            'engagement_type' => 'view',
        ]));

        $response = $this->controller->trackEngagement($request);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(42, $data['engagement_id']);
    }

    #[Test]
    public function trackEngagementPassesOptionalFieldsToService(): void
    {
        $post = $this->createPost(1);
        $engagement = $this->createEngagement(10);

        $this->liveTextPostRepo->method('find')->willReturn($post);

        $analyticsService = $this->createMock(LiveTextAdvancedAnalyticsService::class);
        $analyticsService->expects($this->once())
            ->method('trackEngagement')
            ->with(
                $post,
                'click',
                $this->isNull(),
                120,
                80,
                'button.cta',
                ['source' => 'homepage']
            )
            ->willReturn($engagement);

        $controller = new AdvancedAnalyticsController($analyticsService, $this->entityManager);

        // Re-set container for the new controller
        $paramBag = $this->createStub(ContainerBagInterface::class);
        $paramBag->method('get')->willReturnMap([['kernel.debug', false]]);
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => match ($id) {
            'parameter_bag', 'security.token_storage' => true,
            default => false,
        });
        $container->method('get')->willReturnCallback(fn (string $id) => match ($id) {
            'parameter_bag' => $paramBag,
            'security.token_storage' => $tokenStorage,
            default => null,
        });
        $controller->setContainer($container);

        $request = Request::create('/api/analytics/track', 'POST', [], [], [], [], json_encode([
            'post_id' => 1,
            'engagement_type' => 'click',
            'time_spent' => 120,
            'scroll_depth' => 80,
            'clicked_element' => 'button.cta',
            'metadata' => ['source' => 'homepage'],
        ]));

        $response = $controller->trackEngagement($request);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    #[Test]
    public function trackEngagementPassesAuthenticatedUserToService(): void
    {
        $post = $this->createPost(1);
        $engagement = $this->createEngagement(5);
        $user = new User();

        $this->liveTextPostRepo->method('find')->willReturn($post);

        $analyticsService = $this->createMock(LiveTextAdvancedAnalyticsService::class);
        $analyticsService->expects($this->once())
            ->method('trackEngagement')
            ->with(
                $post,
                'view',
                $user,
                $this->isNull(),
                $this->isNull(),
                $this->isNull(),
                $this->isNull()
            )
            ->willReturn($engagement);

        $controller = new AdvancedAnalyticsController($analyticsService, $this->entityManager);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $paramBag = $this->createStub(ContainerBagInterface::class);
        $paramBag->method('get')->willReturnMap([['kernel.debug', false]]);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => match ($id) {
            'parameter_bag', 'security.token_storage' => true,
            default => false,
        });
        $container->method('get')->willReturnCallback(fn (string $id) => match ($id) {
            'parameter_bag' => $paramBag,
            'security.token_storage' => $tokenStorage,
            default => null,
        });
        $controller->setContainer($container);

        $request = Request::create('/api/analytics/track', 'POST', [], [], [], [], json_encode([
            'post_id' => 1,
            'engagement_type' => 'view',
        ]));

        $response = $controller->trackEngagement($request);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    #[Test]
    public function trackEngagementReturnsServerErrorWhenServiceThrows(): void
    {
        $post = $this->createPost(1);

        $this->liveTextPostRepo->method('find')->willReturn($post);
        $this->analyticsService->method('trackEngagement')->willThrowException(
            new \Exception('DB connection failed')
        );
        $this->setCurrentUser(null);

        $request = Request::create('/api/analytics/track', 'POST', [], [], [], [], json_encode([
            'post_id' => 1,
            'engagement_type' => 'view',
        ]));

        $response = $this->controller->trackEngagement($request);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('DB connection failed', $data['error']);
    }

    // =============================================
    // GET /api/analytics/live-text/{id}/heatmap
    // =============================================

    #[Test]
    public function getHeatmapReturnsNotFoundWhenLiveTextDoesNotExist(): void
    {
        $this->liveTextRepo->method('find')->willReturn(null);

        $response = $this->controller->getHeatmap(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('LiveText not found', $data['error']);
    }

    #[Test]
    public function getHeatmapReturnsDataWhenLiveTextExists(): void
    {
        $liveText = $this->createLiveText(1);
        $heatmapData = [
            'data' => [['post_id' => 1, 'intensity' => 80]],
            'max_engagements' => 100,
            'total_posts' => 1,
        ];

        $this->liveTextRepo->method('find')->willReturn($liveText);
        $this->analyticsService->method('getHeatmapData')->willReturn($heatmapData);

        $response = $this->controller->getHeatmap(1);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(100, $data['max_engagements']);
        $this->assertSame(1, $data['total_posts']);
        $this->assertCount(1, $data['data']);
    }

    // =============================================
    // GET /api/analytics/live-text/{id}/funnel
    // =============================================

    #[Test]
    public function getFunnelReturnsNotFoundWhenLiveTextDoesNotExist(): void
    {
        $this->liveTextRepo->method('find')->willReturn(null);

        $response = $this->controller->getFunnel(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('LiveText not found', $data['error']);
    }

    #[Test]
    public function getFunnelReturnsDataWhenLiveTextExists(): void
    {
        $liveText = $this->createLiveText(1);
        $funnelData = [
            'funnel' => ['view' => ['unique_users' => 100, 'total_events' => 200]],
            'total_views' => 100,
            'drop_off_rate' => ['view_to_read' => 50.0],
        ];

        $this->liveTextRepo->method('find')->willReturn($liveText);
        $this->analyticsService->method('getEngagementFunnel')->willReturn($funnelData);

        $response = $this->controller->getFunnel(1);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(100, $data['total_views']);
        $this->assertArrayHasKey('funnel', $data);
    }

    // =============================================
    // GET /api/analytics/live-text/{id}/top-posts
    // =============================================

    #[Test]
    public function getTopPostsReturnsNotFoundWhenLiveTextDoesNotExist(): void
    {
        $this->liveTextRepo->method('find')->willReturn(null);

        $request = Request::create('/api/analytics/live-text/999/top-posts');
        $response = $this->controller->getTopPosts(999, $request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function getTopPostsReturnsDataWithDefaultLimit(): void
    {
        $liveText = $this->createLiveText(1);
        $topPosts = [
            ['post_id' => 1, 'engagement_count' => 50],
            ['post_id' => 2, 'engagement_count' => 30],
        ];

        $this->liveTextRepo->method('find')->willReturn($liveText);
        $this->analyticsService->method('getTopEngagedPosts')->willReturn($topPosts);

        $request = Request::create('/api/analytics/live-text/1/top-posts');
        $response = $this->controller->getTopPosts(1, $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['top_posts']);
        $this->assertSame(10, $data['limit']);
    }

    #[Test]
    public function getTopPostsRespectsCustomLimit(): void
    {
        $liveText = $this->createLiveText(1);

        $this->liveTextRepo->method('find')->willReturn($liveText);
        $this->analyticsService->method('getTopEngagedPosts')->willReturn([]);

        $request = Request::create('/api/analytics/live-text/1/top-posts', 'GET', ['limit' => '5']);
        $response = $this->controller->getTopPosts(1, $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(5, $data['limit']);
    }

    // =============================================
    // GET /api/analytics/live-text/{id}/summary
    // =============================================

    #[Test]
    public function getSummaryReturnsNotFoundWhenLiveTextDoesNotExist(): void
    {
        $this->liveTextRepo->method('find')->willReturn(null);

        $response = $this->controller->getSummary(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('LiveText not found', $data['error']);
    }

    #[Test]
    public function getSummaryReturnsDataWhenLiveTextExists(): void
    {
        $liveText = $this->createLiveText(1);
        $summaryData = [
            'summary' => [
                'total_engagements' => 500,
                'total_unique_users' => 200,
                'avg_time_spent' => 45.5,
                'avg_scroll_depth' => 72.0,
                'engagement_rate' => 2.5,
            ],
            'funnel' => [],
            'top_posts' => [],
            'heatmap_summary' => ['max_engagements' => 50, 'total_posts' => 10],
        ];

        $this->liveTextRepo->method('find')->willReturn($liveText);
        $this->analyticsService->method('getEngagementSummary')->willReturn($summaryData);

        $response = $this->controller->getSummary(1);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(500, $data['summary']['total_engagements']);
        $this->assertArrayHasKey('heatmap_summary', $data);
    }

    // =============================================
    // POST /api/analytics/view
    // =============================================

    #[Test]
    public function trackViewReturnsBadRequestWhenMissingPostId(): void
    {
        $request = Request::create('/api/analytics/view', 'POST', [], [], [], [], json_encode([]));

        $response = $this->controller->trackView($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('post_id', $data['error']);
    }

    #[Test]
    public function trackViewReturnsNotFoundWhenPostDoesNotExist(): void
    {
        $this->liveTextPostRepo->method('find')->willReturn(null);
        $this->setCurrentUser(null);

        $request = Request::create('/api/analytics/view', 'POST', [], [], [], [], json_encode([
            'post_id' => 999,
        ]));

        $response = $this->controller->trackView($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function trackViewReturnsSuccessWhenPostExists(): void
    {
        $post = $this->createPost(1);
        $this->liveTextPostRepo->method('find')->willReturn($post);
        $this->setCurrentUser(null);

        $request = Request::create('/api/analytics/view', 'POST', [], [], [], [], json_encode([
            'post_id' => 1,
        ]));

        $response = $this->controller->trackView($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    // =============================================
    // POST /api/analytics/read
    // =============================================

    #[Test]
    public function trackReadReturnsBadRequestWhenMissingPostId(): void
    {
        $request = Request::create('/api/analytics/read', 'POST', [], [], [], [], json_encode([
            'time_spent' => 30,
            'scroll_depth' => 80,
        ]));

        $response = $this->controller->trackRead($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function trackReadReturnsBadRequestWhenMissingTimeSpent(): void
    {
        $request = Request::create('/api/analytics/read', 'POST', [], [], [], [], json_encode([
            'post_id' => 1,
            'scroll_depth' => 80,
        ]));

        $response = $this->controller->trackRead($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function trackReadReturnsBadRequestWhenMissingScrollDepth(): void
    {
        $request = Request::create('/api/analytics/read', 'POST', [], [], [], [], json_encode([
            'post_id' => 1,
            'time_spent' => 30,
        ]));

        $response = $this->controller->trackRead($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function trackReadReturnsNotFoundWhenPostDoesNotExist(): void
    {
        $this->liveTextPostRepo->method('find')->willReturn(null);
        $this->setCurrentUser(null);

        $request = Request::create('/api/analytics/read', 'POST', [], [], [], [], json_encode([
            'post_id' => 999,
            'time_spent' => 30,
            'scroll_depth' => 80,
        ]));

        $response = $this->controller->trackRead($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function trackReadReturnsSuccessWhenValid(): void
    {
        $post = $this->createPost(1);
        $this->liveTextPostRepo->method('find')->willReturn($post);
        $this->setCurrentUser(null);

        $request = Request::create('/api/analytics/read', 'POST', [], [], [], [], json_encode([
            'post_id' => 1,
            'time_spent' => 30,
            'scroll_depth' => 80,
        ]));

        $response = $this->controller->trackRead($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    // =============================================
    // POST /api/analytics/click
    // =============================================

    #[Test]
    public function trackClickReturnsBadRequestWhenMissingPostId(): void
    {
        $request = Request::create('/api/analytics/click', 'POST', [], [], [], [], json_encode([
            'clicked_element' => 'link',
        ]));

        $response = $this->controller->trackClick($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function trackClickReturnsBadRequestWhenMissingClickedElement(): void
    {
        $request = Request::create('/api/analytics/click', 'POST', [], [], [], [], json_encode([
            'post_id' => 1,
        ]));

        $response = $this->controller->trackClick($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function trackClickReturnsNotFoundWhenPostDoesNotExist(): void
    {
        $this->liveTextPostRepo->method('find')->willReturn(null);
        $this->setCurrentUser(null);

        $request = Request::create('/api/analytics/click', 'POST', [], [], [], [], json_encode([
            'post_id' => 999,
            'clicked_element' => 'link',
        ]));

        $response = $this->controller->trackClick($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function trackClickReturnsSuccessWhenValid(): void
    {
        $post = $this->createPost(1);
        $this->liveTextPostRepo->method('find')->willReturn($post);
        $this->setCurrentUser(null);

        $request = Request::create('/api/analytics/click', 'POST', [], [], [], [], json_encode([
            'post_id' => 1,
            'clicked_element' => 'button.read-more',
        ]));

        $response = $this->controller->trackClick($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    // =============================================
    // POST /api/analytics/share
    // =============================================

    #[Test]
    public function trackShareReturnsBadRequestWhenMissingPostId(): void
    {
        $request = Request::create('/api/analytics/share', 'POST', [], [], [], [], json_encode([
            'platform' => 'twitter',
        ]));

        $response = $this->controller->trackShare($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function trackShareReturnsBadRequestWhenMissingPlatform(): void
    {
        $request = Request::create('/api/analytics/share', 'POST', [], [], [], [], json_encode([
            'post_id' => 1,
        ]));

        $response = $this->controller->trackShare($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function trackShareReturnsNotFoundWhenPostDoesNotExist(): void
    {
        $this->liveTextPostRepo->method('find')->willReturn(null);
        $this->setCurrentUser(null);

        $request = Request::create('/api/analytics/share', 'POST', [], [], [], [], json_encode([
            'post_id' => 999,
            'platform' => 'facebook',
        ]));

        $response = $this->controller->trackShare($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function trackShareReturnsSuccessWhenValid(): void
    {
        $post = $this->createPost(1);
        $this->liveTextPostRepo->method('find')->willReturn($post);
        $this->setCurrentUser(null);

        $request = Request::create('/api/analytics/share', 'POST', [], [], [], [], json_encode([
            'post_id' => 1,
            'platform' => 'facebook',
        ]));

        $response = $this->controller->trackShare($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    // =============================================
    // DELETE /api/analytics/clear-old
    // =============================================

    #[Test]
    public function clearOldEngagementsReturnsSuccessWithDefaultDays(): void
    {
        $this->analyticsService->method('clearOldEngagements')->willReturn(42);

        $request = Request::create('/api/analytics/clear-old', 'DELETE');
        $response = $this->controller->clearOldEngagements($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(42, $data['deleted_count']);
        $this->assertArrayHasKey('before_date', $data);
    }

    #[Test]
    public function clearOldEngagementsRespectsCustomDaysParameter(): void
    {
        $this->analyticsService->method('clearOldEngagements')->willReturn(10);

        $request = Request::create('/api/analytics/clear-old', 'DELETE', ['days' => '30']);
        $response = $this->controller->clearOldEngagements($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(10, $data['deleted_count']);
    }

    #[Test]
    public function clearOldEngagementsReturnsZeroDeletedCount(): void
    {
        $this->analyticsService->method('clearOldEngagements')->willReturn(0);

        $request = Request::create('/api/analytics/clear-old', 'DELETE');
        $response = $this->controller->clearOldEngagements($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(0, $data['deleted_count']);
    }

    #[Test]
    public function clearOldEngagementsContainsProperDateFormat(): void
    {
        $this->analyticsService->method('clearOldEngagements')->willReturn(5);

        $request = Request::create('/api/analytics/clear-old', 'DELETE', ['days' => '90']);
        $response = $this->controller->clearOldEngagements($request);

        $data = json_decode($response->getContent(), true);
        // Validate the date is parseable and in the expected format
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $data['before_date']);
        $this->assertNotFalse($date, 'before_date should be a valid Y-m-d H:i:s format');
    }
}
