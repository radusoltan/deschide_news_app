<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LiveTextMatchEvent;
use App\Entity\LiveTextSportMatch;
use App\Repository\LiveTextMatchEventRepository;
use App\Repository\LiveTextSportMatchRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing sport-specific features in LiveText.
 */
class LiveTextSportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LiveTextSportMatchRepository $sportMatchRepository,
        private readonly LiveTextMatchEventRepository $matchEventRepository,
        private readonly LiveTextNotificationService $notificationService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Update match score.
     */
    public function updateScore(LiveTextSportMatch $sportMatch, int $homeScore, int $awayScore): void
    {
        $sportMatch->setHomeScore($homeScore);
        $sportMatch->setAwayScore($awayScore);

        $this->entityManager->flush();

        // Publish score update via Mercure
        $this->notificationService->publishSportScoreUpdate(
            $sportMatch->getLiveText(),
            $sportMatch
        );

        $this->logger->info('Sport match score updated', [
            'match_id' => $sportMatch->getId(),
            'score' => "$homeScore-$awayScore",
        ]);
    }

    /**
     * Update match status.
     */
    public function updateMatchStatus(LiveTextSportMatch $sportMatch, string $status): void
    {
        $oldStatus = $sportMatch->getStatus();
        $sportMatch->setStatus($status);

        // Set timestamps based on status
        switch ($status) {
            case 'live':
                if ($oldStatus === 'not_started') {
                    $sportMatch->setActualStartTime(new DateTime());
                }
                break;
            case 'finished':
                $sportMatch->setEndTime(new DateTime());
                break;
        }

        $this->entityManager->flush();

        // Publish status change via Mercure
        $this->notificationService->publishSportMatchStatusChange(
            $sportMatch->getLiveText(),
            $sportMatch,
            $oldStatus,
            $status
        );

        $this->logger->info('Sport match status changed', [
            'match_id' => $sportMatch->getId(),
            'old_status' => $oldStatus,
            'new_status' => $status,
        ]);
    }

    /**
     * Add match event (goal, card, substitution, etc.).
     */
    public function addMatchEvent(LiveTextSportMatch $sportMatch, array $eventData): LiveTextMatchEvent
    {
        $event = new LiveTextMatchEvent();
        $event->setSportMatch($sportMatch);
        $event->setEventType($eventData['eventType']);
        $event->setTeam($eventData['team']);
        $event->setEventMinute($eventData['eventMinute']);

        if (isset($eventData['extraTimeMinute'])) {
            $event->setExtraTimeMinute($eventData['extraTimeMinute']);
        }

        if (isset($eventData['playerName'])) {
            $event->setPlayerName($eventData['playerName']);
        }

        if (isset($eventData['secondPlayerName'])) {
            $event->setSecondPlayerName($eventData['secondPlayerName']);
        }

        if (isset($eventData['description'])) {
            $event->setDescription($eventData['description']);
        }

        if (isset($eventData['metadata'])) {
            $event->setMetadata($eventData['metadata']);
        }

        // Auto-update score for goal events
        if (\in_array($event->getEventType(), ['goal', 'penalty_goal'], true)) {
            if ($event->getTeam() === 'home') {
                $sportMatch->setHomeScore($sportMatch->getHomeScore() + 1);
            } else {
                $sportMatch->setAwayScore($sportMatch->getAwayScore() + 1);
            }

            $event->setScoreAfterEvent(
                $sportMatch->getHomeScore() . '-' . $sportMatch->getAwayScore()
            );
        }

        // Handle own goal (increases opponent score)
        if ($event->getEventType() === 'own_goal') {
            if ($event->getTeam() === 'home') {
                $sportMatch->setAwayScore($sportMatch->getAwayScore() + 1);
            } else {
                $sportMatch->setHomeScore($sportMatch->getHomeScore() + 1);
            }

            $event->setScoreAfterEvent(
                $sportMatch->getHomeScore() . '-' . $sportMatch->getAwayScore()
            );
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        // Publish event via Mercure
        $this->notificationService->publishSportMatchEvent(
            $sportMatch->getLiveText(),
            $sportMatch,
            $event
        );

        $this->logger->info('Sport match event added', [
            'match_id' => $sportMatch->getId(),
            'event_type' => $event->getEventType(),
            'minute' => $event->getFormattedMinute(),
        ]);

        return $event;
    }

    /**
     * Update match minute (for live tracking).
     */
    public function updateCurrentMinute(LiveTextSportMatch $sportMatch, int $minute): void
    {
        $sportMatch->setCurrentMinute($minute);
        $this->entityManager->flush();

        // Publish minute update via Mercure (optional, can be rate-limited)
        $this->notificationService->publishSportMinuteUpdate(
            $sportMatch->getLiveText(),
            $sportMatch
        );
    }

    /**
     * Update match statistics.
     */
    public function updateStatistics(LiveTextSportMatch $sportMatch, array $statistics): void
    {
        $sportMatch->setStatistics($statistics);
        $this->entityManager->flush();

        // Publish statistics update via Mercure
        $this->notificationService->publishSportStatisticsUpdate(
            $sportMatch->getLiveText(),
            $sportMatch
        );

        $this->logger->info('Sport match statistics updated', [
            'match_id' => $sportMatch->getId(),
            'statistics' => $statistics,
        ]);
    }

    /**
     * Get match timeline (all events ordered by minute).
     */
    public function getMatchTimeline(LiveTextSportMatch $sportMatch): array
    {
        return $this->matchEventRepository->findByMatch($sportMatch);
    }

    /**
     * Get match summary (goals, cards, substitutions count).
     */
    public function getMatchSummary(LiveTextSportMatch $sportMatch): array
    {
        return [
            'match_id' => $sportMatch->getId(),
            'home_team' => $sportMatch->getHomeTeam(),
            'away_team' => $sportMatch->getAwayTeam(),
            'score' => [
                'home' => $sportMatch->getHomeScore(),
                'away' => $sportMatch->getAwayScore(),
            ],
            'status' => $sportMatch->getStatus(),
            'current_minute' => $sportMatch->getCurrentMinute(),
            'statistics' => [
                'home' => [
                    'goals' => $this->matchEventRepository->getGoalCountByTeam($sportMatch, 'home'),
                    'yellow_cards' => $this->matchEventRepository->getCardCountByTeam($sportMatch, 'home', 'yellow_card'),
                    'red_cards' => $this->matchEventRepository->getCardCountByTeam($sportMatch, 'home', 'red_card'),
                ],
                'away' => [
                    'goals' => $this->matchEventRepository->getGoalCountByTeam($sportMatch, 'away'),
                    'yellow_cards' => $this->matchEventRepository->getCardCountByTeam($sportMatch, 'away', 'yellow_card'),
                    'red_cards' => $this->matchEventRepository->getCardCountByTeam($sportMatch, 'away', 'red_card'),
                ],
            ],
            'latest_events' => array_map(
                fn ($event) => [
                    'id' => $event->getId(),
                    'type' => $event->getEventType(),
                    'team' => $event->getTeam(),
                    'player' => $event->getPlayerName(),
                    'minute' => $event->getFormattedMinute(),
                    'icon' => $event->getEventIcon(),
                ],
                $this->matchEventRepository->findLatestEvents($sportMatch, 5)
            ),
        ];
    }

    /**
     * Get live matches.
     */
    public function getLiveMatches(): array
    {
        return $this->sportMatchRepository->findLiveMatches();
    }

    /**
     * Get upcoming matches.
     */
    public function getUpcomingMatches(int $limit = 10): array
    {
        return $this->sportMatchRepository->findUpcomingMatches($limit);
    }
}
