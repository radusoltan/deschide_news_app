<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\LiveTextSportController;
use App\Entity\LiveText;
use App\Entity\LiveTextMatchEvent;
use App\Entity\LiveTextSportMatch;
use App\Service\LiveTextSportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for LiveTextSportController.
 *
 * Covers: updateScore, updateStatus, addEvent, updateMinute,
 *         updateStatistics, getTimeline, getSummary, getLiveMatches, getUpcomingMatches.
 */
class LiveTextSportControllerTest extends TestCase
{
    private LiveTextSportService $sportService;
    private EntityManagerInterface $entityManager;
    private LiveTextSportController $controller;

    protected function setUp(): void
    {
        $this->sportService = $this->createStub(LiveTextSportService::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);

        $this->controller = new LiveTextSportController(
            $this->sportService,
            $this->entityManager
        );

        // Set up container for AbstractController
        $paramBag = $this->createStub(ContainerBagInterface::class);
        $paramBag->method('get')->willReturnMap([
            ['kernel.debug', false],
        ]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function (string $id): bool {
            return match ($id) {
                'parameter_bag' => true,
                default => false,
            };
        });
        $container->method('get')->willReturnCallback(function (string $id) use ($paramBag) {
            return match ($id) {
                'parameter_bag' => $paramBag,
                default => null,
            };
        });

        $this->controller->setContainer($container);
    }

    private function createSportMatch(
        int $id = 1,
        int $homeScore = 0,
        int $awayScore = 0,
        string $status = 'not_started',
        ?int $currentMinute = null,
        ?array $statistics = null,
        string $homeTeam = 'Team A',
        string $awayTeam = 'Team B',
        string $sportType = 'football',
        ?string $competition = 'League',
        ?string $venue = 'Stadium',
    ): LiveTextSportMatch {
        $match = new LiveTextSportMatch();
        $reflection = new \ReflectionClass($match);
        $idProp = $reflection->getProperty('id');
        $idProp->setValue($match, $id);

        $liveText = new LiveText();
        $ltReflection = new \ReflectionClass($liveText);
        $ltIdProp = $ltReflection->getProperty('id');
        $ltIdProp->setValue($liveText, $id);

        $match->setLiveText($liveText);
        $match->setHomeTeam($homeTeam);
        $match->setAwayTeam($awayTeam);
        $match->setHomeScore($homeScore);
        $match->setAwayScore($awayScore);
        $match->setStatus($status);
        $match->setSportType($sportType);
        $match->setCompetition($competition);
        $match->setVenue($venue);
        if ($currentMinute !== null) {
            $match->setCurrentMinute($currentMinute);
        }
        if ($statistics !== null) {
            $match->setStatistics($statistics);
        }

        return $match;
    }

    private function createMatchEvent(
        int $id,
        string $eventType = 'goal',
        string $team = 'home',
        int $eventMinute = 45,
        ?int $extraTimeMinute = null,
        ?string $playerName = null,
        ?string $secondPlayerName = null,
        ?string $description = null,
        ?string $scoreAfterEvent = null,
    ): LiveTextMatchEvent {
        $event = new LiveTextMatchEvent();
        $reflection = new \ReflectionClass($event);
        $idProp = $reflection->getProperty('id');
        $idProp->setValue($event, $id);

        $event->setEventType($eventType);
        $event->setTeam($team);
        $event->setEventMinute($eventMinute);
        if ($extraTimeMinute !== null) {
            $event->setExtraTimeMinute($extraTimeMinute);
        }
        if ($playerName !== null) {
            $event->setPlayerName($playerName);
        }
        if ($secondPlayerName !== null) {
            $event->setSecondPlayerName($secondPlayerName);
        }
        if ($description !== null) {
            $event->setDescription($description);
        }
        if ($scoreAfterEvent !== null) {
            $event->setScoreAfterEvent($scoreAfterEvent);
        }

        return $event;
    }

    // =============================================
    // PUT /{id}/score
    // =============================================

    #[Test]
    public function updateScoreReturnsBadRequestWhenMissingHomeScore(): void
    {
        $match = $this->createSportMatch();
        $request = Request::create('/api/sport_matches/1/score', 'PUT', [], [], [], [], json_encode([
            'away_score' => 2,
        ]));

        $response = $this->controller->updateScore($match, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('home_score', $data['error']);
    }

    #[Test]
    public function updateScoreReturnsBadRequestWhenMissingAwayScore(): void
    {
        $match = $this->createSportMatch();
        $request = Request::create('/api/sport_matches/1/score', 'PUT', [], [], [], [], json_encode([
            'home_score' => 1,
        ]));

        $response = $this->controller->updateScore($match, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('away_score', $data['error']);
    }

    #[Test]
    public function updateScoreReturnsBadRequestWhenBothScoresMissing(): void
    {
        $match = $this->createSportMatch();
        $request = Request::create('/api/sport_matches/1/score', 'PUT', [], [], [], [], json_encode([]));

        $response = $this->controller->updateScore($match, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function updateScoreReturnsSuccessWithUpdatedScore(): void
    {
        $match = $this->createSportMatch(id: 5, homeScore: 2, awayScore: 1);
        $request = Request::create('/api/sport_matches/5/score', 'PUT', [], [], [], [], json_encode([
            'home_score' => 2,
            'away_score' => 1,
        ]));

        $response = $this->controller->updateScore($match, $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Score updated successfully', $data['message']);
        $this->assertSame(5, $data['match_id']);
        $this->assertSame(2, $data['score']['home']);
        $this->assertSame(1, $data['score']['away']);
    }

    // =============================================
    // PUT /{id}/status
    // =============================================

    #[Test]
    public function updateStatusReturnsBadRequestWhenMissingStatus(): void
    {
        $match = $this->createSportMatch();
        $request = Request::create('/api/sport_matches/1/status', 'PUT', [], [], [], [], json_encode([]));

        $response = $this->controller->updateStatus($match, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('status', $data['error']);
    }

    #[Test]
    public function updateStatusReturnsBadRequestForInvalidStatus(): void
    {
        $match = $this->createSportMatch();
        $request = Request::create('/api/sport_matches/1/status', 'PUT', [], [], [], [], json_encode([
            'status' => 'invalid_status',
        ]));

        $response = $this->controller->updateStatus($match, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Invalid status', $data['error']);
        $this->assertStringContainsString('not_started', $data['error']);
    }

    #[Test]
    public function updateStatusReturnsSuccessForValidStatus(): void
    {
        $match = $this->createSportMatch(id: 3, status: 'live');
        $request = Request::create('/api/sport_matches/3/status', 'PUT', [], [], [], [], json_encode([
            'status' => 'live',
        ]));

        $response = $this->controller->updateStatus($match, $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Status updated successfully', $data['message']);
        $this->assertSame(3, $data['match_id']);
        $this->assertSame('live', $data['status']);
    }

    #[Test]
    public function updateStatusAcceptsAllValidStatuses(): void
    {
        $validStatuses = ['not_started', 'live', 'half_time', 'finished', 'postponed', 'cancelled'];

        foreach ($validStatuses as $status) {
            $match = $this->createSportMatch(status: $status);
            $request = Request::create('/api/sport_matches/1/status', 'PUT', [], [], [], [], json_encode([
                'status' => $status,
            ]));

            $response = $this->controller->updateStatus($match, $request);

            $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), "Status '$status' should be accepted");
        }
    }

    // =============================================
    // POST /{id}/events
    // =============================================

    #[Test]
    public function addEventReturnsBadRequestWhenMissingEventType(): void
    {
        $match = $this->createSportMatch();
        $request = Request::create('/api/sport_matches/1/events', 'POST', [], [], [], [], json_encode([
            'team' => 'home',
            'eventMinute' => 45,
        ]));

        $response = $this->controller->addEvent($match, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('eventType', $data['error']);
    }

    #[Test]
    public function addEventReturnsBadRequestWhenMissingTeam(): void
    {
        $match = $this->createSportMatch();
        $request = Request::create('/api/sport_matches/1/events', 'POST', [], [], [], [], json_encode([
            'eventType' => 'goal',
            'eventMinute' => 45,
        ]));

        $response = $this->controller->addEvent($match, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('team', $data['error']);
    }

    #[Test]
    public function addEventReturnsBadRequestWhenMissingEventMinute(): void
    {
        $match = $this->createSportMatch();
        $request = Request::create('/api/sport_matches/1/events', 'POST', [], [], [], [], json_encode([
            'eventType' => 'goal',
            'team' => 'home',
        ]));

        $response = $this->controller->addEvent($match, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('eventMinute', $data['error']);
    }

    #[Test]
    public function addEventReturnsBadRequestForInvalidEventType(): void
    {
        $match = $this->createSportMatch();
        $request = Request::create('/api/sport_matches/1/events', 'POST', [], [], [], [], json_encode([
            'eventType' => 'triple_play',
            'team' => 'home',
            'eventMinute' => 10,
        ]));

        $response = $this->controller->addEvent($match, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid event type', $data['error']);
    }

    #[Test]
    public function addEventReturnsBadRequestForInvalidTeam(): void
    {
        $match = $this->createSportMatch();
        $request = Request::create('/api/sport_matches/1/events', 'POST', [], [], [], [], json_encode([
            'eventType' => 'goal',
            'team' => 'neutral',
            'eventMinute' => 10,
        ]));

        $response = $this->controller->addEvent($match, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('home or away', $data['error']);
    }

    #[Test]
    public function addEventReturnsCreatedOnSuccess(): void
    {
        $match = $this->createSportMatch(id: 2);
        $event = $this->createMatchEvent(
            id: 10,
            eventType: 'goal',
            team: 'home',
            eventMinute: 45,
            playerName: 'John Doe',
            scoreAfterEvent: '1-0'
        );

        $this->sportService->method('addMatchEvent')->willReturn($event);

        $request = Request::create('/api/sport_matches/2/events', 'POST', [], [], [], [], json_encode([
            'eventType' => 'goal',
            'team' => 'home',
            'eventMinute' => 45,
            'playerName' => 'John Doe',
        ]));

        $response = $this->controller->addEvent($match, $request);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Event added successfully', $data['message']);
        $this->assertSame(10, $data['event']['id']);
        $this->assertSame('goal', $data['event']['type']);
        $this->assertSame('goal', $data['event']['icon']);
        $this->assertSame('home', $data['event']['team']);
        $this->assertSame('John Doe', $data['event']['player']);
        $this->assertSame('45', $data['event']['minute']);
        $this->assertSame('1-0', $data['event']['score_after']);
    }

    #[Test]
    public function addEventAcceptsAllValidEventTypes(): void
    {
        $validEventTypes = [
            'goal', 'penalty_goal', 'own_goal', 'missed_penalty',
            'yellow_card', 'red_card', 'second_yellow_card',
            'substitution',
            'penalty_saved', 'var_check', 'var_goal_cancelled', 'var_penalty',
            'injury', 'injury_time',
            'kick_off', 'half_time', 'full_time',
            'corner', 'free_kick', 'offside',
            'other',
        ];

        $event = $this->createMatchEvent(id: 1);
        $this->sportService->method('addMatchEvent')->willReturn($event);

        foreach ($validEventTypes as $eventType) {
            $match = $this->createSportMatch();
            $request = Request::create('/api/sport_matches/1/events', 'POST', [], [], [], [], json_encode([
                'eventType' => $eventType,
                'team' => 'home',
                'eventMinute' => 10,
            ]));

            $response = $this->controller->addEvent($match, $request);

            $this->assertSame(
                Response::HTTP_CREATED,
                $response->getStatusCode(),
                "Event type '$eventType' should be accepted"
            );
        }
    }

    #[Test]
    public function addEventAcceptsBothHomeAndAwayTeam(): void
    {
        $event = $this->createMatchEvent(id: 1);
        $this->sportService->method('addMatchEvent')->willReturn($event);

        foreach (['home', 'away'] as $team) {
            $match = $this->createSportMatch();
            $request = Request::create('/api/sport_matches/1/events', 'POST', [], [], [], [], json_encode([
                'eventType' => 'goal',
                'team' => $team,
                'eventMinute' => 30,
            ]));

            $response = $this->controller->addEvent($match, $request);
            $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        }
    }

    // =============================================
    // PUT /{id}/minute
    // =============================================

    #[Test]
    public function updateMinuteReturnsBadRequestWhenMissingMinute(): void
    {
        $match = $this->createSportMatch();
        $request = Request::create('/api/sport_matches/1/minute', 'PUT', [], [], [], [], json_encode([]));

        $response = $this->controller->updateMinute($match, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('minute', $data['error']);
    }

    #[Test]
    public function updateMinuteReturnsSuccessWithUpdatedMinute(): void
    {
        $match = $this->createSportMatch(id: 7, currentMinute: 65);
        $request = Request::create('/api/sport_matches/7/minute', 'PUT', [], [], [], [], json_encode([
            'minute' => 65,
        ]));

        $response = $this->controller->updateMinute($match, $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Minute updated successfully', $data['message']);
        $this->assertSame(7, $data['match_id']);
        $this->assertSame(65, $data['current_minute']);
    }

    // =============================================
    // PUT /{id}/statistics
    // =============================================

    #[Test]
    public function updateStatisticsReturnsBadRequestWhenMissingStatistics(): void
    {
        $match = $this->createSportMatch();
        $request = Request::create('/api/sport_matches/1/statistics', 'PUT', [], [], [], [], json_encode([]));

        $response = $this->controller->updateStatistics($match, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('statistics', $data['error']);
    }

    #[Test]
    public function updateStatisticsReturnsBadRequestWhenStatisticsIsNotArray(): void
    {
        $match = $this->createSportMatch();
        $request = Request::create('/api/sport_matches/1/statistics', 'PUT', [], [], [], [], json_encode([
            'statistics' => 'not_an_array',
        ]));

        $response = $this->controller->updateStatistics($match, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function updateStatisticsReturnsSuccessWithUpdatedStatistics(): void
    {
        $stats = ['possession' => ['home' => 60, 'away' => 40], 'shots' => ['home' => 12, 'away' => 8]];
        $match = $this->createSportMatch(id: 4, statistics: $stats);

        $request = Request::create('/api/sport_matches/4/statistics', 'PUT', [], [], [], [], json_encode([
            'statistics' => $stats,
        ]));

        $response = $this->controller->updateStatistics($match, $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Statistics updated successfully', $data['message']);
        $this->assertSame(4, $data['match_id']);
        $this->assertSame($stats, $data['statistics']);
    }

    // =============================================
    // GET /{id}/timeline
    // =============================================

    #[Test]
    public function getTimelineReturnsEmptyEventsWhenNoEvents(): void
    {
        $match = $this->createSportMatch(id: 1, homeTeam: 'FC Barcelona', awayTeam: 'Real Madrid');
        $this->sportService->method('getMatchTimeline')->willReturn([]);

        $response = $this->controller->getTimeline($match);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['match_id']);
        $this->assertSame('FC Barcelona', $data['home_team']);
        $this->assertSame('Real Madrid', $data['away_team']);
        $this->assertCount(0, $data['events']);
    }

    #[Test]
    public function getTimelineReturnsFormattedEvents(): void
    {
        $match = $this->createSportMatch(id: 2, homeTeam: 'Team A', awayTeam: 'Team B');
        $event1 = $this->createMatchEvent(
            id: 1,
            eventType: 'goal',
            team: 'home',
            eventMinute: 23,
            playerName: 'Player 1',
            scoreAfterEvent: '1-0',
            description: 'Great goal from outside the box'
        );
        $event2 = $this->createMatchEvent(
            id: 2,
            eventType: 'yellow_card',
            team: 'away',
            eventMinute: 45,
            extraTimeMinute: 2,
            playerName: 'Player 2',
            secondPlayerName: null,
            description: 'Hard foul'
        );

        $this->sportService->method('getMatchTimeline')->willReturn([$event1, $event2]);

        $response = $this->controller->getTimeline($match);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['events']);

        // First event: goal
        $this->assertSame(1, $data['events'][0]['id']);
        $this->assertSame('goal', $data['events'][0]['type']);
        $this->assertSame('goal', $data['events'][0]['icon']);
        $this->assertSame('home', $data['events'][0]['team']);
        $this->assertSame('Player 1', $data['events'][0]['player']);
        $this->assertSame('23', $data['events'][0]['minute']);
        $this->assertSame('1-0', $data['events'][0]['score_after']);
        $this->assertSame('Great goal from outside the box', $data['events'][0]['description']);

        // Second event: yellow card with extra time
        $this->assertSame(2, $data['events'][1]['id']);
        $this->assertSame('yellow_card', $data['events'][1]['type']);
        $this->assertSame('yellow-card', $data['events'][1]['icon']);
        $this->assertSame('away', $data['events'][1]['team']);
        $this->assertSame('45+2', $data['events'][1]['minute']);
    }

    #[Test]
    public function getTimelineIncludesSecondPlayerName(): void
    {
        $match = $this->createSportMatch(id: 1);
        $event = $this->createMatchEvent(
            id: 3,
            eventType: 'substitution',
            team: 'home',
            eventMinute: 60,
            playerName: 'Player In',
            secondPlayerName: 'Player Out'
        );

        $this->sportService->method('getMatchTimeline')->willReturn([$event]);

        $response = $this->controller->getTimeline($match);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Player In', $data['events'][0]['player']);
        $this->assertSame('Player Out', $data['events'][0]['second_player']);
    }

    // =============================================
    // GET /{id}/summary
    // =============================================

    #[Test]
    public function getSummaryReturnsSummaryData(): void
    {
        $match = $this->createSportMatch(id: 5);
        $summaryData = [
            'match_id' => 5,
            'home_team' => 'Team A',
            'away_team' => 'Team B',
            'score' => ['home' => 2, 'away' => 1],
            'status' => 'live',
            'current_minute' => 75,
            'statistics' => [
                'home' => ['goals' => 2, 'yellow_cards' => 1, 'red_cards' => 0],
                'away' => ['goals' => 1, 'yellow_cards' => 2, 'red_cards' => 0],
            ],
            'latest_events' => [],
        ];

        $this->sportService->method('getMatchSummary')->willReturn($summaryData);

        $response = $this->controller->getSummary($match);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(5, $data['match_id']);
        $this->assertSame(2, $data['score']['home']);
        $this->assertSame(1, $data['score']['away']);
        $this->assertSame('live', $data['status']);
    }

    // =============================================
    // GET /live
    // =============================================

    #[Test]
    public function getLiveMatchesReturnsEmptyWhenNoLiveMatches(): void
    {
        $this->sportService->method('getLiveMatches')->willReturn([]);

        $response = $this->controller->getLiveMatches();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(0, $data['count']);
        $this->assertCount(0, $data['matches']);
    }

    #[Test]
    public function getLiveMatchesReturnsFormattedMatches(): void
    {
        $match1 = $this->createSportMatch(
            id: 1,
            homeScore: 2,
            awayScore: 0,
            status: 'live',
            currentMinute: 55,
            homeTeam: 'FC Barcelona',
            awayTeam: 'Real Madrid',
            sportType: 'football',
            competition: 'La Liga'
        );

        $this->sportService->method('getLiveMatches')->willReturn([$match1]);

        $response = $this->controller->getLiveMatches();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['count']);
        $this->assertCount(1, $data['matches']);

        $m = $data['matches'][0];
        $this->assertSame(1, $m['id']);
        $this->assertSame(1, $m['live_text_id']);
        $this->assertSame('football', $m['sport_type']);
        $this->assertSame('FC Barcelona', $m['home_team']);
        $this->assertSame('Real Madrid', $m['away_team']);
        $this->assertSame(2, $m['score']['home']);
        $this->assertSame(0, $m['score']['away']);
        $this->assertSame('live', $m['status']);
        $this->assertSame(55, $m['current_minute']);
        $this->assertSame('La Liga', $m['competition']);
    }

    #[Test]
    public function getLiveMatchesReturnsMultipleMatches(): void
    {
        $match1 = $this->createSportMatch(id: 1, status: 'live');
        $match2 = $this->createSportMatch(id: 2, status: 'live');

        $this->sportService->method('getLiveMatches')->willReturn([$match1, $match2]);

        $response = $this->controller->getLiveMatches();

        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['count']);
        $this->assertCount(2, $data['matches']);
    }

    // =============================================
    // GET /upcoming
    // =============================================

    #[Test]
    public function getUpcomingMatchesReturnsEmptyWhenNone(): void
    {
        $this->sportService->method('getUpcomingMatches')->willReturn([]);

        $request = Request::create('/api/sport_matches/upcoming');
        $response = $this->controller->getUpcomingMatches($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(0, $data['count']);
        $this->assertCount(0, $data['matches']);
    }

    #[Test]
    public function getUpcomingMatchesReturnsFormattedMatches(): void
    {
        $match = $this->createSportMatch(
            id: 10,
            homeTeam: 'Team X',
            awayTeam: 'Team Y',
            sportType: 'basketball',
            competition: 'NBA',
            venue: 'Madison Square Garden'
        );
        $scheduledTime = new \DateTime('2026-04-01 18:00:00');
        $match->setScheduledStartTime($scheduledTime);

        $this->sportService->method('getUpcomingMatches')->willReturn([$match]);

        $request = Request::create('/api/sport_matches/upcoming');
        $response = $this->controller->getUpcomingMatches($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['count']);

        $m = $data['matches'][0];
        $this->assertSame(10, $m['id']);
        $this->assertSame(10, $m['live_text_id']);
        $this->assertSame('basketball', $m['sport_type']);
        $this->assertSame('Team X', $m['home_team']);
        $this->assertSame('Team Y', $m['away_team']);
        $this->assertSame('NBA', $m['competition']);
        $this->assertSame('Madison Square Garden', $m['venue']);
        $this->assertNotNull($m['scheduled_start_time']);
    }

    #[Test]
    public function getUpcomingMatchesUsesDefaultLimitOfTen(): void
    {
        $sportService = $this->createMock(LiveTextSportService::class);
        $sportService->expects($this->once())
            ->method('getUpcomingMatches')
            ->with(10)
            ->willReturn([]);

        $controller = new LiveTextSportController($sportService, $this->entityManager);

        // Set up container
        $paramBag = $this->createStub(ContainerBagInterface::class);
        $paramBag->method('get')->willReturnMap([['kernel.debug', false]]);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => match ($id) {
            'parameter_bag' => true,
            default => false,
        });
        $container->method('get')->willReturnCallback(fn (string $id) => match ($id) {
            'parameter_bag' => $paramBag,
            default => null,
        });
        $controller->setContainer($container);

        $request = Request::create('/api/sport_matches/upcoming');
        $controller->getUpcomingMatches($request);
    }

    #[Test]
    public function getUpcomingMatchesRespectsCustomLimit(): void
    {
        $sportService = $this->createMock(LiveTextSportService::class);
        $sportService->expects($this->once())
            ->method('getUpcomingMatches')
            ->with(5)
            ->willReturn([]);

        $controller = new LiveTextSportController($sportService, $this->entityManager);

        // Set up container
        $paramBag = $this->createStub(ContainerBagInterface::class);
        $paramBag->method('get')->willReturnMap([['kernel.debug', false]]);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => match ($id) {
            'parameter_bag' => true,
            default => false,
        });
        $container->method('get')->willReturnCallback(fn (string $id) => match ($id) {
            'parameter_bag' => $paramBag,
            default => null,
        });
        $controller->setContainer($container);

        $request = Request::create('/api/sport_matches/upcoming', 'GET', ['limit' => '5']);
        $controller->getUpcomingMatches($request);
    }

    #[Test]
    public function getUpcomingMatchesHandlesNullScheduledStartTime(): void
    {
        $match = $this->createSportMatch(id: 1);
        // scheduledStartTime is null by default

        $this->sportService->method('getUpcomingMatches')->willReturn([$match]);

        $request = Request::create('/api/sport_matches/upcoming');
        $response = $this->controller->getUpcomingMatches($request);

        $data = json_decode($response->getContent(), true);
        $this->assertNull($data['matches'][0]['scheduled_start_time']);
    }
}
