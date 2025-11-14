<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LiveTextSportMatch;
use App\Service\LiveTextSportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route('/api/sport_matches', name: 'api_sport_matches_')]
class LiveTextSportController extends AbstractController
{
    public function __construct(
        private readonly LiveTextSportService $sportService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Update match score.
     */
    #[Route('/{id}/score', name: 'update_score', methods: ['PUT'])]
    #[IsGranted('ROLE_EDITOR')]
    public function updateScore(LiveTextSportMatch $sportMatch, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['home_score']) || !isset($data['away_score'])) {
            return $this->json([
                'error' => 'Missing required fields: home_score, away_score',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->sportService->updateScore(
            $sportMatch,
            (int) $data['home_score'],
            (int) $data['away_score']
        );

        return $this->json([
            'message' => 'Score updated successfully',
            'match_id' => $sportMatch->getId(),
            'score' => [
                'home' => $sportMatch->getHomeScore(),
                'away' => $sportMatch->getAwayScore(),
            ],
        ]);
    }

    /**
     * Update match status.
     */
    #[Route('/{id}/status', name: 'update_status', methods: ['PUT'])]
    #[IsGranted('ROLE_EDITOR')]
    public function updateStatus(LiveTextSportMatch $sportMatch, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['status'])) {
            return $this->json([
                'error' => 'Missing required field: status',
            ], Response::HTTP_BAD_REQUEST);
        }

        $allowedStatuses = ['not_started', 'live', 'half_time', 'finished', 'postponed', 'cancelled'];
        if (!\in_array($data['status'], $allowedStatuses, true)) {
            return $this->json([
                'error' => 'Invalid status. Allowed: ' . implode(', ', $allowedStatuses),
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->sportService->updateMatchStatus($sportMatch, $data['status']);

        return $this->json([
            'message' => 'Status updated successfully',
            'match_id' => $sportMatch->getId(),
            'status' => $sportMatch->getStatus(),
        ]);
    }

    /**
     * Add match event (goal, card, substitution, etc.).
     */
    #[Route('/{id}/events', name: 'add_event', methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    public function addEvent(LiveTextSportMatch $sportMatch, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $requiredFields = ['eventType', 'team', 'eventMinute'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return $this->json([
                    'error' => "Missing required field: $field",
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $allowedEventTypes = [
            'goal', 'penalty_goal', 'own_goal', 'missed_penalty',
            'yellow_card', 'red_card', 'second_yellow_card',
            'substitution',
            'penalty_saved', 'var_check', 'var_goal_cancelled', 'var_penalty',
            'injury', 'injury_time',
            'kick_off', 'half_time', 'full_time',
            'corner', 'free_kick', 'offside',
            'other',
        ];

        if (!\in_array($data['eventType'], $allowedEventTypes, true)) {
            return $this->json([
                'error' => 'Invalid event type',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!\in_array($data['team'], ['home', 'away'], true)) {
            return $this->json([
                'error' => 'Invalid team. Must be: home or away',
            ], Response::HTTP_BAD_REQUEST);
        }

        $event = $this->sportService->addMatchEvent($sportMatch, $data);

        return $this->json([
            'message' => 'Event added successfully',
            'event' => [
                'id' => $event->getId(),
                'type' => $event->getEventType(),
                'icon' => $event->getEventIcon(),
                'team' => $event->getTeam(),
                'player' => $event->getPlayerName(),
                'minute' => $event->getFormattedMinute(),
                'score_after' => $event->getScoreAfterEvent(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Update current minute.
     */
    #[Route('/{id}/minute', name: 'update_minute', methods: ['PUT'])]
    #[IsGranted('ROLE_EDITOR')]
    public function updateMinute(LiveTextSportMatch $sportMatch, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['minute'])) {
            return $this->json([
                'error' => 'Missing required field: minute',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->sportService->updateCurrentMinute($sportMatch, (int) $data['minute']);

        return $this->json([
            'message' => 'Minute updated successfully',
            'match_id' => $sportMatch->getId(),
            'current_minute' => $sportMatch->getCurrentMinute(),
        ]);
    }

    /**
     * Update match statistics.
     */
    #[Route('/{id}/statistics', name: 'update_statistics', methods: ['PUT'])]
    #[IsGranted('ROLE_EDITOR')]
    public function updateStatistics(LiveTextSportMatch $sportMatch, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['statistics']) || !\is_array($data['statistics'])) {
            return $this->json([
                'error' => 'Missing or invalid field: statistics (must be an object)',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->sportService->updateStatistics($sportMatch, $data['statistics']);

        return $this->json([
            'message' => 'Statistics updated successfully',
            'match_id' => $sportMatch->getId(),
            'statistics' => $sportMatch->getStatistics(),
        ]);
    }

    /**
     * Get match timeline (all events).
     */
    #[Route('/{id}/timeline', name: 'get_timeline', methods: ['GET'])]
    public function getTimeline(LiveTextSportMatch $sportMatch): JsonResponse
    {
        $events = $this->sportService->getMatchTimeline($sportMatch);

        return $this->json([
            'match_id' => $sportMatch->getId(),
            'home_team' => $sportMatch->getHomeTeam(),
            'away_team' => $sportMatch->getAwayTeam(),
            'events' => array_map(fn ($event) => [
                'id' => $event->getId(),
                'type' => $event->getEventType(),
                'icon' => $event->getEventIcon(),
                'team' => $event->getTeam(),
                'player' => $event->getPlayerName(),
                'second_player' => $event->getSecondPlayerName(),
                'minute' => $event->getFormattedMinute(),
                'score_after' => $event->getScoreAfterEvent(),
                'description' => $event->getDescription(),
            ], $events),
        ]);
    }

    /**
     * Get match summary.
     */
    #[Route('/{id}/summary', name: 'get_summary', methods: ['GET'])]
    public function getSummary(LiveTextSportMatch $sportMatch): JsonResponse
    {
        $summary = $this->sportService->getMatchSummary($sportMatch);

        return $this->json($summary);
    }

    /**
     * Get live matches.
     */
    #[Route('/live', name: 'get_live_matches', methods: ['GET'])]
    public function getLiveMatches(): JsonResponse
    {
        $matches = $this->sportService->getLiveMatches();

        return $this->json([
            'count' => \count($matches),
            'matches' => array_map(fn ($match) => [
                'id' => $match->getId(),
                'live_text_id' => $match->getLiveText()->getId(),
                'sport_type' => $match->getSportType(),
                'home_team' => $match->getHomeTeam(),
                'away_team' => $match->getAwayTeam(),
                'score' => [
                    'home' => $match->getHomeScore(),
                    'away' => $match->getAwayScore(),
                ],
                'status' => $match->getStatus(),
                'current_minute' => $match->getCurrentMinute(),
                'competition' => $match->getCompetition(),
            ], $matches),
        ]);
    }

    /**
     * Get upcoming matches.
     */
    #[Route('/upcoming', name: 'get_upcoming_matches', methods: ['GET'])]
    public function getUpcomingMatches(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 10);
        $matches = $this->sportService->getUpcomingMatches($limit);

        return $this->json([
            'count' => \count($matches),
            'matches' => array_map(fn ($match) => [
                'id' => $match->getId(),
                'live_text_id' => $match->getLiveText()->getId(),
                'sport_type' => $match->getSportType(),
                'home_team' => $match->getHomeTeam(),
                'away_team' => $match->getAwayTeam(),
                'competition' => $match->getCompetition(),
                'scheduled_start_time' => $match->getScheduledStartTime()?->format('c'),
                'venue' => $match->getVenue(),
            ], $matches),
        ]);
    }
}
