<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\LiveText\LiveTextEventDto;
use App\Entity\LiveText;
use App\Entity\LiveTextMatchEvent;
use App\Entity\LiveTextSportMatch;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for publishing LiveText events to Mercure hub.
 */
class LiveTextNotificationService
{
    private const TOPIC_PREFIX = 'deschide_news/live_text';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $mercureUrl,
        private readonly string $mercurePublisherJwt
    ) {
    }

    /**
     * Publish an event to Mercure.
     */
    public function publishEvent(LiveTextEventDto $event): void
    {
        try {
            $topic = $this->buildTopic($event->liveTextId);
            $data = json_encode($event->toArray());

            $this->logger->info('Publishing Mercure event', [
                'type' => $event->type,
                'liveTextId' => $event->liveTextId,
                'topic' => $topic,
            ]);

            $response = $this->httpClient->request('POST', $this->mercureUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->mercurePublisherJwt,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'topic' => $topic,
                    'data' => $data,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Failed to publish Mercure event', [
                    'statusCode' => $response->getStatusCode(),
                    'response' => $response->getContent(false),
                ]);
            } else {
                $this->logger->debug('Mercure event published successfully', [
                    'topic' => $topic,
                    'type' => $event->type,
                ]);
            }
        } catch (Exception $e) {
            // Log error but don't fail the request if Mercure is unavailable
            $this->logger->error('Exception while publishing Mercure event', [
                'error' => $e->getMessage(),
                'type' => $event->type,
                'liveTextId' => $event->liveTextId,
            ]);
        }
    }

    /**
     * Get topic for subscribing (for frontend).
     */
    public function getTopicUrl(int $liveTextId): string
    {
        return $this->buildTopic($liveTextId);
    }

    /**
     * Get Mercure hub URL for subscribing.
     */
    public function getMercureHubUrl(): string
    {
        return $this->mercureUrl;
    }

    /**
     * Publish sport score update.
     */
    public function publishSportScoreUpdate(LiveText $liveText, LiveTextSportMatch $sportMatch): void
    {
        $event = new LiveTextEventDto(
            type: 'sport.score.updated',
            liveTextId: $liveText->getId(),
            data: [
                'match_id' => $sportMatch->getId(),
                'home_team' => $sportMatch->getHomeTeam(),
                'away_team' => $sportMatch->getAwayTeam(),
                'home_score' => $sportMatch->getHomeScore(),
                'away_score' => $sportMatch->getAwayScore(),
                'status' => $sportMatch->getStatus(),
                'current_minute' => $sportMatch->getCurrentMinute(),
            ],
            timestamp: new DateTime()
        );

        $this->publishEvent($event);
    }

    /**
     * Publish sport match status change.
     */
    public function publishSportMatchStatusChange(
        LiveText $liveText,
        LiveTextSportMatch $sportMatch,
        string $oldStatus,
        string $newStatus
    ): void {
        $event = new LiveTextEventDto(
            type: 'sport.match.status_changed',
            liveTextId: $liveText->getId(),
            data: [
                'match_id' => $sportMatch->getId(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'home_team' => $sportMatch->getHomeTeam(),
                'away_team' => $sportMatch->getAwayTeam(),
                'score' => [
                    'home' => $sportMatch->getHomeScore(),
                    'away' => $sportMatch->getAwayScore(),
                ],
            ],
            timestamp: new DateTime()
        );

        $this->publishEvent($event);
    }

    /**
     * Publish sport match event (goal, card, substitution, etc.).
     */
    public function publishSportMatchEvent(
        LiveText $liveText,
        LiveTextSportMatch $sportMatch,
        LiveTextMatchEvent $matchEvent
    ): void {
        $event = new LiveTextEventDto(
            type: 'sport.match.event',
            liveTextId: $liveText->getId(),
            data: [
                'match_id' => $sportMatch->getId(),
                'event_id' => $matchEvent->getId(),
                'event_type' => $matchEvent->getEventType(),
                'event_icon' => $matchEvent->getEventIcon(),
                'team' => $matchEvent->getTeam(),
                'player_name' => $matchEvent->getPlayerName(),
                'second_player_name' => $matchEvent->getSecondPlayerName(),
                'minute' => $matchEvent->getFormattedMinute(),
                'score_after' => $matchEvent->getScoreAfterEvent(),
                'description' => $matchEvent->getDescription(),
                'current_score' => [
                    'home' => $sportMatch->getHomeScore(),
                    'away' => $sportMatch->getAwayScore(),
                ],
            ],
            timestamp: new DateTime()
        );

        $this->publishEvent($event);
    }

    /**
     * Publish sport minute update.
     */
    public function publishSportMinuteUpdate(LiveText $liveText, LiveTextSportMatch $sportMatch): void
    {
        $event = new LiveTextEventDto(
            type: 'sport.minute.updated',
            liveTextId: $liveText->getId(),
            data: [
                'match_id' => $sportMatch->getId(),
                'current_minute' => $sportMatch->getCurrentMinute(),
                'status' => $sportMatch->getStatus(),
            ],
            timestamp: new DateTime()
        );

        $this->publishEvent($event);
    }

    /**
     * Publish sport statistics update.
     */
    public function publishSportStatisticsUpdate(LiveText $liveText, LiveTextSportMatch $sportMatch): void
    {
        $event = new LiveTextEventDto(
            type: 'sport.statistics.updated',
            liveTextId: $liveText->getId(),
            data: [
                'match_id' => $sportMatch->getId(),
                'statistics' => $sportMatch->getStatistics(),
            ],
            timestamp: new DateTime()
        );

        $this->publishEvent($event);
    }

    /**
     * Build Mercure topic for a LiveText.
     */
    private function buildTopic(int $liveTextId): string
    {
        return \sprintf('%s/%d', self::TOPIC_PREFIX, $liveTextId);
    }
}
