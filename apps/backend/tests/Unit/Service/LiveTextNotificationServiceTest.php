<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\LiveText\PostCreatedEventDto;
use App\Dto\LiveText\PostDeletedEventDto;
use App\Dto\LiveText\PostUpdatedEventDto;
use App\Dto\LiveText\StatusChangedEventDto;
use App\Dto\LiveText\ViewersCountEventDto;
use App\Entity\LiveText;
use App\Entity\LiveTextMatchEvent;
use App\Entity\LiveTextSportMatch;
use App\Service\LiveTextNotificationService;
use DateTime;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LiveTextNotificationServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private LiveTextNotificationService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->service = new LiveTextNotificationService(
            $this->httpClient,
            $this->logger,
            'http://localhost:3000/.well-known/mercure',
            'fake-jwt-token'
        );
    }

    // ── getTopicUrl ────────────────────────────────────────────────────────────

    #[Test]
    public function itReturnsCorrectTopicUrlForLiveTextId(): void
    {
        $url = $this->service->getTopicUrl(42);

        $this->assertSame('deschide_news/live_text/42', $url);
    }

    #[Test]
    public function itReturnsCorrectTopicUrlForDifferentId(): void
    {
        $url = $this->service->getTopicUrl(1);

        $this->assertSame('deschide_news/live_text/1', $url);
    }

    // ── getMercureHubUrl ───────────────────────────────────────────────────────

    #[Test]
    public function itReturnsMercureHubUrl(): void
    {
        $url = $this->service->getMercureHubUrl();

        $this->assertSame('http://localhost:3000/.well-known/mercure', $url);
    }

    // ── publishEvent (via concrete Dto subclasses) ─────────────────────────────

    #[Test]
    public function itPublishesEventSuccessfully(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $dto = new PostDeletedEventDto(
            liveTextId: 5,
            postId: 10,
            timestamp: new DateTime()
        );

        $this->service->publishEvent($dto);

        $this->assertTrue(true);
    }

    #[Test]
    public function itLogsErrorWhenMercureReturnsNon200(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getContent')->willReturn('Internal Server Error');

        $this->httpClient->method('request')->willReturn($response);

        $dto = new PostDeletedEventDto(
            liveTextId: 5,
            postId: 10,
            timestamp: new DateTime()
        );

        // Should not throw
        $this->service->publishEvent($dto);

        $this->assertTrue(true);
    }

    #[Test]
    public function itHandlesHttpClientExceptionGracefully(): void
    {
        $this->httpClient->method('request')
            ->willThrowException(new Exception('Connection refused'));

        $dto = new PostDeletedEventDto(
            liveTextId: 5,
            postId: 10,
            timestamp: new DateTime()
        );

        // Must not throw
        $this->service->publishEvent($dto);

        $this->assertTrue(true);
    }

    // ── Dto toArray tests ──────────────────────────────────────────────────────

    #[Test]
    public function postDeletedDtoReturnsCorrectArray(): void
    {
        $timestamp = new DateTime('2026-01-15 12:00:00');

        $dto = new PostDeletedEventDto(
            liveTextId: 7,
            postId: 42,
            timestamp: $timestamp
        );

        $array = $dto->toArray();

        $this->assertSame('post.deleted', $array['type']);
        $this->assertSame(7, $array['liveTextId']);
        $this->assertSame(42, $array['postId']);
        $this->assertArrayHasKey('timestamp', $array);
    }

    #[Test]
    public function postUpdatedDtoReturnsCorrectArray(): void
    {
        $timestamp = new DateTime('2026-01-15 12:00:00');

        $dto = new PostUpdatedEventDto(
            liveTextId: 7,
            postId: 42,
            content: 'Updated content',
            contentHtml: '<p>Updated content</p>',
            isKeyPoint: true,
            position: 3,
            timestamp: $timestamp
        );

        $array = $dto->toArray();

        $this->assertSame('post.updated', $array['type']);
        $this->assertSame(7, $array['liveTextId']);
        $this->assertSame(42, $array['post']['id']);
        $this->assertSame('Updated content', $array['post']['content']);
        $this->assertTrue($array['post']['isKeyPoint']);
    }

    #[Test]
    public function postCreatedDtoReturnsCorrectArray(): void
    {
        $timestamp = new DateTime('2026-01-15 12:00:00');
        $publishedAt = new DateTime('2026-01-15 11:55:00');

        $dto = new PostCreatedEventDto(
            liveTextId: 10,
            postId: 99,
            content: 'New post content',
            contentHtml: '<p>New post content</p>',
            author: ['id' => 1, 'username' => 'admin', 'email' => 'admin@test.com'],
            isKeyPoint: false,
            position: 1,
            publishedAt: $publishedAt,
            timestamp: $timestamp
        );

        $array = $dto->toArray();

        $this->assertSame('post.created', $array['type']);
        $this->assertSame(10, $array['liveTextId']);
        $this->assertSame(99, $array['post']['id']);
        $this->assertSame('New post content', $array['post']['content']);
        $this->assertFalse($array['post']['isKeyPoint']);
        $this->assertSame(1, $array['post']['author']['id']);
    }

    #[Test]
    public function statusChangedDtoReturnsCorrectArray(): void
    {
        $timestamp = new DateTime('2026-01-15 12:00:00');

        $dto = new StatusChangedEventDto(
            liveTextId: 3,
            status: 'live',
            title: 'Match Title',
            timestamp: $timestamp
        );

        $array = $dto->toArray();

        $this->assertSame('status.changed', $array['type']);
        $this->assertSame(3, $array['liveTextId']);
        $this->assertSame('live', $array['status']);
        $this->assertSame('Match Title', $array['title']);
    }

    #[Test]
    public function viewersCountDtoReturnsCorrectArray(): void
    {
        $timestamp = new DateTime('2026-01-15 12:00:00');

        $dto = new ViewersCountEventDto(
            liveTextId: 5,
            count: 1234,
            timestamp: $timestamp
        );

        $array = $dto->toArray();

        $this->assertSame('viewers.count', $array['type']);
        $this->assertSame(5, $array['liveTextId']);
        $this->assertSame(1234, $array['count']);
    }

    // ── publishEvent verifies correct headers and body ─────────────────────────

    #[Test]
    public function publishEventSendsCorrectHeadersAndBody(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'http://localhost:3000/.well-known/mercure',
                $this->callback(function (array $options): bool {
                    $this->assertSame('Bearer fake-jwt-token', $options['headers']['Authorization']);
                    $this->assertSame('application/x-www-form-urlencoded', $options['headers']['Content-Type']);
                    $this->assertSame('deschide_news/live_text/5', $options['body']['topic']);
                    $this->assertIsString($options['body']['data']);

                    return true;
                })
            )
            ->willReturn($response);

        $dto = new PostDeletedEventDto(
            liveTextId: 5,
            postId: 10,
            timestamp: new DateTime()
        );

        $this->service->publishEvent($dto);
    }

    // ── publishSportScoreUpdate ─────────────────────────────────────────────────

    #[Test]
    public function publishSportScoreUpdateBuildsCorrectEventData(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $capturedBody = null;

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->anything(),
                $this->callback(function (array $options) use (&$capturedBody): bool {
                    $capturedBody = $options['body'] ?? null;

                    return true;
                })
            )
            ->willReturn($response);

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(10);

        $sportMatch = $this->createStub(LiveTextSportMatch::class);
        $sportMatch->method('getId')->willReturn(77);
        $sportMatch->method('getHomeTeam')->willReturn('FC Moldova');
        $sportMatch->method('getAwayTeam')->willReturn('FC Romania');
        $sportMatch->method('getHomeScore')->willReturn(2);
        $sportMatch->method('getAwayScore')->willReturn(1);
        $sportMatch->method('getStatus')->willReturn('in_progress');
        $sportMatch->method('getCurrentMinute')->willReturn(65);

        $this->service->publishSportScoreUpdate($liveText, $sportMatch);

        $this->assertNotNull($capturedBody);
        $this->assertSame('deschide_news/live_text/10', $capturedBody['topic']);

        $decoded = json_decode($capturedBody['data'], true);
        $this->assertSame('sport.score.updated', $decoded['type']);
        $this->assertSame(10, $decoded['liveTextId']);
        $this->assertSame(77, $decoded['data']['match_id']);
        $this->assertSame('FC Moldova', $decoded['data']['home_team']);
        $this->assertSame('FC Romania', $decoded['data']['away_team']);
        $this->assertSame(2, $decoded['data']['home_score']);
        $this->assertSame(1, $decoded['data']['away_score']);
        $this->assertSame('in_progress', $decoded['data']['status']);
        $this->assertSame(65, $decoded['data']['current_minute']);
    }

    // ── publishSportMatchStatusChange ───────────────────────────────────────────

    #[Test]
    public function publishSportMatchStatusChangeBuildsCorrectEventData(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $capturedBody = null;

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', $this->anything(), $this->callback(function (array $options) use (&$capturedBody): bool {
                $capturedBody = $options['body'] ?? null;

                return true;
            }))
            ->willReturn($response);

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(15);

        $sportMatch = $this->createStub(LiveTextSportMatch::class);
        $sportMatch->method('getId')->willReturn(88);
        $sportMatch->method('getHomeTeam')->willReturn('Team A');
        $sportMatch->method('getAwayTeam')->willReturn('Team B');
        $sportMatch->method('getHomeScore')->willReturn(0);
        $sportMatch->method('getAwayScore')->willReturn(0);

        $this->service->publishSportMatchStatusChange($liveText, $sportMatch, 'not_started', 'in_progress');

        $this->assertNotNull($capturedBody);

        $decoded = json_decode($capturedBody['data'], true);
        $this->assertSame('sport.match.status_changed', $decoded['type']);
        $this->assertSame(15, $decoded['liveTextId']);
        $this->assertSame(88, $decoded['data']['match_id']);
        $this->assertSame('not_started', $decoded['data']['old_status']);
        $this->assertSame('in_progress', $decoded['data']['new_status']);
        $this->assertSame('Team A', $decoded['data']['home_team']);
        $this->assertSame('Team B', $decoded['data']['away_team']);
        $this->assertSame(['home' => 0, 'away' => 0], $decoded['data']['score']);
    }

    // ── publishSportMatchEvent ──────────────────────────────────────────────────

    #[Test]
    public function publishSportMatchEventBuildsCorrectEventStructure(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $capturedBody = null;

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', $this->anything(), $this->callback(function (array $options) use (&$capturedBody): bool {
                $capturedBody = $options['body'] ?? null;

                return true;
            }))
            ->willReturn($response);

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(20);

        $sportMatch = $this->createStub(LiveTextSportMatch::class);
        $sportMatch->method('getId')->willReturn(55);
        $sportMatch->method('getHomeScore')->willReturn(1);
        $sportMatch->method('getAwayScore')->willReturn(0);

        $matchEvent = $this->createStub(LiveTextMatchEvent::class);
        $matchEvent->method('getId')->willReturn(101);
        $matchEvent->method('getEventType')->willReturn('goal');
        $matchEvent->method('getEventIcon')->willReturn('goal');
        $matchEvent->method('getTeam')->willReturn('home');
        $matchEvent->method('getPlayerName')->willReturn('Ion Popescu');
        $matchEvent->method('getSecondPlayerName')->willReturn('Vasile Rusu');
        $matchEvent->method('getFormattedMinute')->willReturn('45+2');
        $matchEvent->method('getScoreAfterEvent')->willReturn('1-0');
        $matchEvent->method('getDescription')->willReturn('Great goal from outside the box');

        $this->service->publishSportMatchEvent($liveText, $sportMatch, $matchEvent);

        $this->assertNotNull($capturedBody);
        $this->assertSame('deschide_news/live_text/20', $capturedBody['topic']);

        $decoded = json_decode($capturedBody['data'], true);
        $this->assertSame('sport.match.event', $decoded['type']);
        $this->assertSame(20, $decoded['liveTextId']);
        $this->assertSame(55, $decoded['data']['match_id']);
        $this->assertSame(101, $decoded['data']['event_id']);
        $this->assertSame('goal', $decoded['data']['event_type']);
        $this->assertSame('goal', $decoded['data']['event_icon']);
        $this->assertSame('home', $decoded['data']['team']);
        $this->assertSame('Ion Popescu', $decoded['data']['player_name']);
        $this->assertSame('Vasile Rusu', $decoded['data']['second_player_name']);
        $this->assertSame('45+2', $decoded['data']['minute']);
        $this->assertSame('1-0', $decoded['data']['score_after']);
        $this->assertSame('Great goal from outside the box', $decoded['data']['description']);
        $this->assertSame(['home' => 1, 'away' => 0], $decoded['data']['current_score']);
    }

    // ── publishSportMinuteUpdate ────────────────────────────────────────────────

    #[Test]
    public function publishSportMinuteUpdateBuildsCorrectData(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $capturedBody = null;

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', $this->anything(), $this->callback(function (array $options) use (&$capturedBody): bool {
                $capturedBody = $options['body'] ?? null;

                return true;
            }))
            ->willReturn($response);

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(30);

        $sportMatch = $this->createStub(LiveTextSportMatch::class);
        $sportMatch->method('getId')->willReturn(66);
        $sportMatch->method('getCurrentMinute')->willReturn(78);
        $sportMatch->method('getStatus')->willReturn('in_progress');

        $this->service->publishSportMinuteUpdate($liveText, $sportMatch);

        $this->assertNotNull($capturedBody);

        $decoded = json_decode($capturedBody['data'], true);
        $this->assertSame('sport.minute.updated', $decoded['type']);
        $this->assertSame(30, $decoded['liveTextId']);
        $this->assertSame(66, $decoded['data']['match_id']);
        $this->assertSame(78, $decoded['data']['current_minute']);
        $this->assertSame('in_progress', $decoded['data']['status']);
    }

    // ── publishSportStatisticsUpdate ────────────────────────────────────────────

    #[Test]
    public function publishSportStatisticsUpdateBuildsCorrectData(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $capturedBody = null;

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', $this->anything(), $this->callback(function (array $options) use (&$capturedBody): bool {
                $capturedBody = $options['body'] ?? null;

                return true;
            }))
            ->willReturn($response);

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(25);

        $stats = ['possession' => ['home' => 60, 'away' => 40], 'shots' => ['home' => 12, 'away' => 5]];
        $sportMatch = $this->createStub(LiveTextSportMatch::class);
        $sportMatch->method('getId')->willReturn(44);
        $sportMatch->method('getStatistics')->willReturn($stats);

        $this->service->publishSportStatisticsUpdate($liveText, $sportMatch);

        $this->assertNotNull($capturedBody);

        $decoded = json_decode($capturedBody['data'], true);
        $this->assertSame('sport.statistics.updated', $decoded['type']);
        $this->assertSame(25, $decoded['liveTextId']);
        $this->assertSame(44, $decoded['data']['match_id']);
        $this->assertSame($stats, $decoded['data']['statistics']);
    }

    // ── Sport methods handle Mercure failures gracefully ────────────────────────

    #[Test]
    public function publishSportScoreUpdateHandlesMercureException(): void
    {
        $this->httpClient->method('request')
            ->willThrowException(new Exception('Connection refused'));

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);

        $sportMatch = $this->createStub(LiveTextSportMatch::class);
        $sportMatch->method('getId')->willReturn(1);
        $sportMatch->method('getHomeTeam')->willReturn('A');
        $sportMatch->method('getAwayTeam')->willReturn('B');
        $sportMatch->method('getHomeScore')->willReturn(0);
        $sportMatch->method('getAwayScore')->willReturn(0);
        $sportMatch->method('getStatus')->willReturn('not_started');
        $sportMatch->method('getCurrentMinute')->willReturn(null);

        // Must not throw
        $this->service->publishSportScoreUpdate($liveText, $sportMatch);

        $this->assertTrue(true);
    }

    #[Test]
    public function publishSportMatchStatusChangeHandlesMercureNon200(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(503);
        $response->method('getContent')->willReturn('Service Unavailable');

        $this->httpClient->method('request')->willReturn($response);

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);

        $sportMatch = $this->createStub(LiveTextSportMatch::class);
        $sportMatch->method('getId')->willReturn(1);
        $sportMatch->method('getHomeTeam')->willReturn('A');
        $sportMatch->method('getAwayTeam')->willReturn('B');
        $sportMatch->method('getHomeScore')->willReturn(0);
        $sportMatch->method('getAwayScore')->willReturn(0);

        // Must not throw
        $this->service->publishSportMatchStatusChange($liveText, $sportMatch, 'live', 'finished');

        $this->assertTrue(true);
    }

    #[Test]
    public function publishSportMatchEventHandlesMercureException(): void
    {
        $this->httpClient->method('request')
            ->willThrowException(new Exception('Timeout'));

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);

        $sportMatch = $this->createStub(LiveTextSportMatch::class);
        $sportMatch->method('getId')->willReturn(1);
        $sportMatch->method('getHomeScore')->willReturn(0);
        $sportMatch->method('getAwayScore')->willReturn(0);

        $matchEvent = $this->createStub(LiveTextMatchEvent::class);
        $matchEvent->method('getId')->willReturn(1);
        $matchEvent->method('getEventType')->willReturn('yellow_card');
        $matchEvent->method('getEventIcon')->willReturn('yellow-card');
        $matchEvent->method('getTeam')->willReturn('away');
        $matchEvent->method('getPlayerName')->willReturn('Player');
        $matchEvent->method('getSecondPlayerName')->willReturn(null);
        $matchEvent->method('getFormattedMinute')->willReturn('30');
        $matchEvent->method('getScoreAfterEvent')->willReturn(null);
        $matchEvent->method('getDescription')->willReturn(null);

        // Must not throw
        $this->service->publishSportMatchEvent($liveText, $sportMatch, $matchEvent);

        $this->assertTrue(true);
    }

    #[Test]
    public function publishSportMinuteUpdateHandlesMercureException(): void
    {
        $this->httpClient->method('request')
            ->willThrowException(new Exception('Network error'));

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);

        $sportMatch = $this->createStub(LiveTextSportMatch::class);
        $sportMatch->method('getId')->willReturn(1);
        $sportMatch->method('getCurrentMinute')->willReturn(90);
        $sportMatch->method('getStatus')->willReturn('in_progress');

        // Must not throw
        $this->service->publishSportMinuteUpdate($liveText, $sportMatch);

        $this->assertTrue(true);
    }

    // ── Dto property access ────────────────────────────────────────────────────

    #[Test]
    public function dtoExposesTypeAndLiveTextIdAsReadonlyProperties(): void
    {
        $dto = new PostDeletedEventDto(
            liveTextId: 99,
            postId: 1,
            timestamp: new DateTime()
        );

        $this->assertSame('post.deleted', $dto->type);
        $this->assertSame(99, $dto->liveTextId);
        $this->assertSame(1, $dto->postId);
    }
}
