<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\LiveText;
use App\Entity\LiveTextMatchEvent;
use App\Entity\LiveTextSportMatch;
use App\Repository\LiveTextMatchEventRepository;
use App\Repository\LiveTextSportMatchRepository;
use App\Service\LiveTextNotificationService;
use App\Service\LiveTextSportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LiveTextSportServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private LiveTextSportMatchRepository $sportMatchRepository;
    private LiveTextMatchEventRepository $matchEventRepository;
    private LiveTextNotificationService $notificationService;
    private LoggerInterface $logger;
    private LiveTextSportService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->sportMatchRepository = $this->createStub(LiveTextSportMatchRepository::class);
        $this->matchEventRepository = $this->createStub(LiveTextMatchEventRepository::class);
        $this->notificationService = $this->createStub(LiveTextNotificationService::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->service = new LiveTextSportService(
            $this->entityManager,
            $this->sportMatchRepository,
            $this->matchEventRepository,
            $this->notificationService,
            $this->logger
        );
    }

    private function createSportMatch(
        int $homeScore = 0,
        int $awayScore = 0,
        string $status = 'not_started',
    ): LiveTextSportMatch {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);

        $match = $this->createMock(LiveTextSportMatch::class);
        $match->method('getId')->willReturn(1);
        $match->method('getLiveText')->willReturn($liveText);
        $match->method('getHomeTeam')->willReturn('Team A');
        $match->method('getAwayTeam')->willReturn('Team B');
        $match->method('getHomeScore')->willReturn($homeScore);
        $match->method('getAwayScore')->willReturn($awayScore);
        $match->method('getStatus')->willReturn($status);
        $match->method('getCurrentMinute')->willReturn(0);
        $match->method('getStatistics')->willReturn([]);

        return $match;
    }

    // --- updateScore ---

    public function testUpdateScoreSetsScoreAndFlushes(): void
    {
        $match = $this->createSportMatch();
        $match->expects($this->once())->method('setHomeScore')->with(2);
        $match->expects($this->once())->method('setAwayScore')->with(1);

        $this->service->updateScore($match, 2, 1);
    }

    // --- updateMatchStatus ---

    public function testUpdateMatchStatusSetsStatusAndFlushes(): void
    {
        $match = $this->createSportMatch(0, 0, 'not_started');
        $match->expects($this->once())->method('setStatus')->with('live');

        $this->service->updateMatchStatus($match, 'live');
    }

    public function testUpdateMatchStatusSetsStartTimeWhenGoingLive(): void
    {
        $match = $this->createSportMatch(0, 0, 'not_started');
        $match->expects($this->once())->method('setActualStartTime');

        $this->service->updateMatchStatus($match, 'live');
    }

    public function testUpdateMatchStatusSetsEndTimeWhenFinished(): void
    {
        $match = $this->createSportMatch(2, 1, 'live');
        $match->expects($this->once())->method('setEndTime');

        $this->service->updateMatchStatus($match, 'finished');
    }

    // --- addMatchEvent ---

    public function testAddMatchEventCreatesEventEntity(): void
    {
        $match = $this->createSportMatch(0, 0, 'live');

        $eventData = [
            'eventType' => 'yellow_card',
            'team' => 'home',
            'eventMinute' => 30,
            'playerName' => 'John Doe',
            'description' => 'Yellow card for foul',
        ];

        $result = $this->service->addMatchEvent($match, $eventData);

        $this->assertInstanceOf(LiveTextMatchEvent::class, $result);
    }

    public function testAddMatchEventAutoUpdatesScoreForGoal(): void
    {
        $match = $this->createSportMatch(1, 0, 'live');
        $match->expects($this->atLeastOnce())->method('setHomeScore');

        $eventData = [
            'eventType' => 'goal',
            'team' => 'home',
            'eventMinute' => 45,
            'playerName' => 'Striker',
        ];

        $this->service->addMatchEvent($match, $eventData);
    }

    public function testAddMatchEventAutoUpdatesScoreForAwayGoal(): void
    {
        $match = $this->createSportMatch(0, 0, 'live');
        $match->expects($this->atLeastOnce())->method('setAwayScore');

        $eventData = [
            'eventType' => 'goal',
            'team' => 'away',
            'eventMinute' => 55,
            'playerName' => 'Away Striker',
        ];

        $this->service->addMatchEvent($match, $eventData);
    }

    public function testAddMatchEventHandlesOwnGoal(): void
    {
        $match = $this->createSportMatch(0, 0, 'live');
        // Own goal by home team increases away score
        $match->expects($this->atLeastOnce())->method('setAwayScore');

        $eventData = [
            'eventType' => 'own_goal',
            'team' => 'home',
            'eventMinute' => 60,
            'playerName' => 'Unlucky Defender',
        ];

        $this->service->addMatchEvent($match, $eventData);
    }

    public function testAddMatchEventHandlesAwayOwnGoal(): void
    {
        $match = $this->createSportMatch(0, 0, 'live');
        // Own goal by away team increases home score
        $match->expects($this->atLeastOnce())->method('setHomeScore');

        $eventData = [
            'eventType' => 'own_goal',
            'team' => 'away',
            'eventMinute' => 70,
            'playerName' => 'Away Defender',
        ];

        $this->service->addMatchEvent($match, $eventData);
    }

    public function testAddMatchEventHandlesPenaltyGoal(): void
    {
        $match = $this->createSportMatch(0, 0, 'live');
        $match->expects($this->atLeastOnce())->method('setHomeScore');

        $eventData = [
            'eventType' => 'penalty_goal',
            'team' => 'home',
            'eventMinute' => 80,
            'playerName' => 'Penalty Taker',
        ];

        $this->service->addMatchEvent($match, $eventData);
    }

    public function testAddMatchEventWithOptionalFields(): void
    {
        $match = $this->createSportMatch(0, 0, 'live');

        $eventData = [
            'eventType' => 'substitution',
            'team' => 'home',
            'eventMinute' => 65,
            'extraTimeMinute' => 2,
            'playerName' => 'Player Out',
            'secondPlayerName' => 'Player In',
            'description' => 'Tactical substitution',
            'metadata' => ['reason' => 'tactical'],
        ];

        $result = $this->service->addMatchEvent($match, $eventData);

        $this->assertInstanceOf(LiveTextMatchEvent::class, $result);
    }

    // --- updateCurrentMinute ---

    public function testUpdateCurrentMinuteSetsMinute(): void
    {
        $match = $this->createSportMatch(0, 0, 'live');
        $match->expects($this->once())->method('setCurrentMinute')->with(45);

        $this->service->updateCurrentMinute($match, 45);
    }

    // --- updateStatistics ---

    public function testUpdateStatisticsSetsStats(): void
    {
        $match = $this->createSportMatch(0, 0, 'live');
        $stats = ['possession' => [55, 45], 'shots' => [10, 5]];
        $match->expects($this->once())->method('setStatistics')->with($stats);

        $this->service->updateStatistics($match, $stats);
    }

    // --- getMatchTimeline ---

    public function testGetMatchTimelineReturnsEvents(): void
    {
        $match = $this->createSportMatch();
        $events = [$this->createStub(LiveTextMatchEvent::class)];
        $this->matchEventRepository->method('findByMatch')->willReturn($events);

        $result = $this->service->getMatchTimeline($match);

        $this->assertCount(1, $result);
    }

    // --- getMatchSummary ---

    public function testGetMatchSummaryReturnsStructuredData(): void
    {
        $match = $this->createSportMatch(2, 1, 'live');
        $this->matchEventRepository->method('getGoalCountByTeam')->willReturn(0);
        $this->matchEventRepository->method('getCardCountByTeam')->willReturn(0);
        $this->matchEventRepository->method('findLatestEvents')->willReturn([]);

        $result = $this->service->getMatchSummary($match);

        $this->assertArrayHasKey('match_id', $result);
        $this->assertArrayHasKey('home_team', $result);
        $this->assertArrayHasKey('away_team', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('statistics', $result);
        $this->assertArrayHasKey('latest_events', $result);
    }

    // --- getLiveMatches ---

    public function testGetLiveMatchesReturnsList(): void
    {
        $this->sportMatchRepository->method('findLiveMatches')->willReturn([]);

        $result = $this->service->getLiveMatches();

        $this->assertIsArray($result);
    }

    // --- getUpcomingMatches ---

    public function testGetUpcomingMatchesReturnsList(): void
    {
        $this->sportMatchRepository->method('findUpcomingMatches')->willReturn([]);

        $result = $this->service->getUpcomingMatches(5);

        $this->assertIsArray($result);
    }
}
