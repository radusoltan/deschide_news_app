<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\LiveTextViewController;
use App\Entity\LiveText;
use App\Entity\LiveTextView;
use App\Entity\User;
use App\Service\LiveTextAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LiveTextViewControllerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private LiveTextAnalyticsService $analyticsService;
    private LiveTextViewController $controller;
    private TokenStorageInterface $tokenStorage;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->analyticsService = $this->createStub(LiveTextAnalyticsService::class);
        $this->tokenStorage = $this->createStub(TokenStorageInterface::class);

        $this->controller = $this->buildController(
            $this->entityManager,
            $this->analyticsService,
        );
    }

    private function buildController(
        EntityManagerInterface $em,
        LiveTextAnalyticsService $analyticsService,
    ): LiveTextViewController {
        $controller = new LiveTextViewController($em, $analyticsService);

        // AbstractController needs a container for json() and getUser()
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['serializer', false],
            ['security.token_storage', true],
            ['twig', false],
        ]);
        $container->method('get')->willReturnMap([
            ['security.token_storage', $this->tokenStorage],
        ]);
        $controller->setContainer($container);

        return $controller;
    }

    private function setLoggedInUser(?User $user): void
    {
        if ($user === null) {
            $this->tokenStorage->method('getToken')->willReturn(null);
        } else {
            $token = $this->createStub(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
            $this->tokenStorage->method('getToken')->willReturn($token);
        }
    }

    private function stubLiveTextRepo(?LiveText $liveText, int $id): void
    {
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with($id)->willReturn($liveText);
        $this->entityManager->method('getRepository')->with(LiveText::class)->willReturn($repo);
    }

    // ========================
    // trackView Tests
    // ========================

    #[Test]
    public function trackViewReturns404WhenLiveTextNotFound(): void
    {
        $this->stubLiveTextRepo(null, 999);
        $this->setLoggedInUser(null);

        $request = new Request(content: json_encode(['sessionId' => 'abc']));

        $response = $this->controller->trackView(999, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Live text not found', $data['error']);
    }

    #[Test]
    public function trackViewReturns400WhenSessionIdIsMissing(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $this->stubLiveTextRepo($liveText, 1);
        $this->setLoggedInUser(null);

        $request = new Request(content: json_encode([]));

        $response = $this->controller->trackView(1, $request);

        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Session ID is required', $data['error']);
    }

    #[Test]
    public function trackViewReturns400WhenSessionIdIsEmptyString(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $this->stubLiveTextRepo($liveText, 1);
        $this->setLoggedInUser(null);

        $request = new Request(content: json_encode(['sessionId' => '']));

        $response = $this->controller->trackView(1, $request);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function trackViewReturnsSuccessWithValidData(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);
        $this->stubLiveTextRepo($liveText, 1);

        $this->setLoggedInUser(null);

        $view = $this->createStub(LiveTextView::class);
        $view->method('getId')->willReturn(10);

        $analyticsService = $this->createStub(LiveTextAnalyticsService::class);
        $analyticsService->method('trackView')->willReturn($view);
        $analyticsService->method('getActiveViewerCount')->willReturn(5);

        $controller = $this->buildController($this->entityManager, $analyticsService);

        $request = new Request(content: json_encode(['sessionId' => 'abc123']));

        $response = $controller->trackView(1, $request);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(10, $data['viewId']);
        $this->assertSame(5, $data['currentViewers']);
    }

    #[Test]
    public function trackViewCallsUpdateTimeSpentWhenProvided(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);
        $this->stubLiveTextRepo($liveText, 1);
        $this->setLoggedInUser(null);

        $view = $this->createStub(LiveTextView::class);
        $view->method('getId')->willReturn(10);

        $analyticsService = $this->createMock(LiveTextAnalyticsService::class);
        $analyticsService->method('trackView')->willReturn($view);
        $analyticsService->method('getActiveViewerCount')->willReturn(1);
        $analyticsService->expects($this->once())->method('updateTimeSpent')
            ->with('abc123', $liveText, 30);
        $analyticsService->expects($this->once())->method('addActiveViewer')
            ->with(1, 'abc123');

        $controller = $this->buildController($this->entityManager, $analyticsService);

        $request = new Request(content: json_encode([
            'sessionId' => 'abc123',
            'timeSpent' => 30,
        ]));

        $response = $controller->trackView(1, $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function trackViewDoesNotCallUpdateTimeSpentWhenZero(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);
        $this->stubLiveTextRepo($liveText, 1);
        $this->setLoggedInUser(null);

        $view = $this->createStub(LiveTextView::class);
        $view->method('getId')->willReturn(10);

        $analyticsService = $this->createMock(LiveTextAnalyticsService::class);
        $analyticsService->method('trackView')->willReturn($view);
        $analyticsService->method('getActiveViewerCount')->willReturn(1);
        $analyticsService->expects($this->never())->method('updateTimeSpent');

        $controller = $this->buildController($this->entityManager, $analyticsService);

        $request = new Request(content: json_encode([
            'sessionId' => 'abc123',
            'timeSpent' => 0,
        ]));

        $controller->trackView(1, $request);
    }

    #[Test]
    public function trackViewDoesNotCallUpdateTimeSpentWhenStringValue(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);
        $this->stubLiveTextRepo($liveText, 1);
        $this->setLoggedInUser(null);

        $view = $this->createStub(LiveTextView::class);
        $view->method('getId')->willReturn(10);

        $analyticsService = $this->createMock(LiveTextAnalyticsService::class);
        $analyticsService->method('trackView')->willReturn($view);
        $analyticsService->method('getActiveViewerCount')->willReturn(1);
        $analyticsService->expects($this->never())->method('updateTimeSpent');

        $controller = $this->buildController($this->entityManager, $analyticsService);

        $request = new Request(content: json_encode([
            'sessionId' => 'abc123',
            'timeSpent' => 'not-an-int',
        ]));

        $controller->trackView(1, $request);
    }

    #[Test]
    public function trackViewDoesNotCallUpdateTimeSpentWhenNegative(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);
        $this->stubLiveTextRepo($liveText, 1);
        $this->setLoggedInUser(null);

        $view = $this->createStub(LiveTextView::class);
        $view->method('getId')->willReturn(10);

        $analyticsService = $this->createMock(LiveTextAnalyticsService::class);
        $analyticsService->method('trackView')->willReturn($view);
        $analyticsService->method('getActiveViewerCount')->willReturn(1);
        $analyticsService->expects($this->never())->method('updateTimeSpent');

        $controller = $this->buildController($this->entityManager, $analyticsService);

        $request = new Request(content: json_encode([
            'sessionId' => 'abc123',
            'timeSpent' => -5,
        ]));

        $controller->trackView(1, $request);
    }

    // ========================
    // getViewers Tests
    // ========================

    #[Test]
    public function getViewersReturns404WhenLiveTextNotFound(): void
    {
        $this->stubLiveTextRepo(null, 999);

        $response = $this->controller->getViewers(999);

        $this->assertSame(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Live text not found', $data['error']);
    }

    #[Test]
    public function getViewersReturnsCountSuccessfully(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);
        $this->stubLiveTextRepo($liveText, 1);

        $analyticsService = $this->createStub(LiveTextAnalyticsService::class);
        $analyticsService->method('getActiveViewerCount')->willReturn(42);

        $controller = $this->buildController($this->entityManager, $analyticsService);

        $response = $controller->getViewers(1);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['liveTextId']);
        $this->assertSame(42, $data['currentViewers']);
    }

    // ========================
    // generateSession Tests
    // ========================

    #[Test]
    public function generateSessionReturnsSessionId(): void
    {
        $analyticsService = $this->createStub(LiveTextAnalyticsService::class);
        $analyticsService->method('generateSessionId')->willReturn('session-abc-123');

        $controller = $this->buildController($this->entityManager, $analyticsService);

        $response = $controller->generateSession();

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('session-abc-123', $data['sessionId']);
    }
}
