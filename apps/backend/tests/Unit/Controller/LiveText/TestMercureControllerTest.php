<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\LiveText;

use App\Controller\LiveText\TestMercureController;
use App\Service\LiveTextNotificationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class TestMercureControllerTest extends TestCase
{
    private LiveTextNotificationService $notificationService;
    private TestMercureController $controller;

    protected function setUp(): void
    {
        $this->notificationService = $this->createStub(LiveTextNotificationService::class);
        $this->controller = new TestMercureController($this->notificationService);
    }

    // ========================
    // testMercure Tests
    // ========================

    #[Test]
    public function testMercurePublishesEventAndReturnsSuccess(): void
    {
        $this->notificationService->method('getTopicUrl')
            ->with(42)
            ->willReturn('deschide_news/live_text/42');

        $request = new Request(query: ['count' => '200']);

        $response = $this->controller->testMercure(42, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Test event published to Mercure', $data['message']);
        $this->assertSame(42, $data['liveTextId']);
        $this->assertSame('deschide_news/live_text/42', $data['topic']);
        $this->assertArrayHasKey('event', $data);
        $this->assertSame('viewers.count', $data['event']['type']);
        $this->assertSame(42, $data['event']['liveTextId']);
        $this->assertSame(200, $data['event']['count']);
    }

    #[Test]
    public function testMercureUsesDefaultCountOf100(): void
    {
        $this->notificationService->method('getTopicUrl')
            ->willReturn('deschide_news/live_text/1');

        // No count parameter in query
        $request = new Request();

        $response = $this->controller->testMercure(1, $request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(100, $data['event']['count']);
    }

    #[Test]
    public function testMercureCallsPublishEvent(): void
    {
        $notificationService = $this->createMock(LiveTextNotificationService::class);
        $notificationService->expects($this->once())->method('publishEvent');
        $notificationService->method('getTopicUrl')->willReturn('topic');

        $controller = new TestMercureController($notificationService);
        $request = new Request();

        $controller->testMercure(1, $request);
    }

    // ========================
    // mercureInfo Tests
    // ========================

    #[Test]
    public function mercureInfoReturnsHubUrlAndTopic(): void
    {
        $this->notificationService->method('getMercureHubUrl')
            ->willReturn('http://localhost:3000/.well-known/mercure');
        $this->notificationService->method('getTopicUrl')
            ->with(1)
            ->willReturn('deschide_news/live_text/1');

        $response = $this->controller->mercureInfo();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('http://localhost:3000/.well-known/mercure', $data['mercureHubUrl']);
        $this->assertSame('deschide_news/live_text/1', $data['exampleTopic']);
        $this->assertArrayHasKey('instructions', $data);
        $this->assertIsArray($data['instructions']);
        $this->assertCount(3, $data['instructions']);
    }

    #[Test]
    public function mercureInfoInstructionsContainSubscriptionExample(): void
    {
        $this->notificationService->method('getMercureHubUrl')
            ->willReturn('http://localhost:3000/.well-known/mercure');
        $this->notificationService->method('getTopicUrl')
            ->with(1)
            ->willReturn('deschide_news/live_text/1');

        $response = $this->controller->mercureInfo();
        $data = json_decode($response->getContent(), true);

        $instructionsStr = implode(' ', $data['instructions']);
        $this->assertStringContainsString('EventSource', $instructionsStr);
        $this->assertStringContainsString('onmessage', $instructionsStr);
    }
}
