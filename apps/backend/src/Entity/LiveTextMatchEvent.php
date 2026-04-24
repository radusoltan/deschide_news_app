<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\LiveTextMatchEventRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Match Event entity.
 *
 * Tracks specific events during a sport match:
 * - Goals (regular, penalty, own goal)
 * - Cards (yellow, red)
 * - Substitutions
 * - Other events (penalty saved, VAR decision, etc.)
 */
#[ORM\Entity(repositoryClass: LiveTextMatchEventRepository::class)]
#[ORM\Table(name: 'live_text_match_events')]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_EDITOR')"),
        new Put(security: "is_granted('ROLE_EDITOR')"),
        new Delete(security: "is_granted('ROLE_EDITOR')"),
    ],
    normalizationContext: ['groups' => ['match_event:read']],
    denormalizationContext: ['groups' => ['match_event:write']]
)]
class LiveTextMatchEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['match_event:read', 'sport_match:read'])]
    private ?int $id = null;

    /**
     * Associated Sport Match.
     */
    #[ORM\ManyToOne(inversedBy: 'events', targetEntity: LiveTextSportMatch::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['match_event:read', 'match_event:write'])]
    private ?LiveTextSportMatch $sportMatch = null;

    /**
     * Event type (goal, yellow_card, red_card, substitution, penalty_saved, var_check, injury, etc.).
     */
    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        'goal', 'penalty_goal', 'own_goal', 'missed_penalty',
        'yellow_card', 'red_card', 'second_yellow_card',
        'substitution',
        'penalty_saved', 'var_check', 'var_goal_cancelled', 'var_penalty',
        'injury', 'injury_time',
        'kick_off', 'half_time', 'full_time',
        'corner', 'free_kick', 'offside',
        'other',
    ])]
    #[Groups(['match_event:read', 'match_event:write', 'sport_match:read'])]
    private ?string $eventType = null;

    /**
     * Team (home or away).
     */
    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: ['home', 'away'])]
    #[Groups(['match_event:read', 'match_event:write', 'sport_match:read'])]
    private ?string $team = null;

    /**
     * Player name (for goals, cards, substitutions).
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['match_event:read', 'match_event:write', 'sport_match:read'])]
    private ?string $playerName = null;

    /**
     * Second player name (for substitutions: player out).
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['match_event:read', 'match_event:write', 'sport_match:read'])]
    private ?string $secondPlayerName = null;

    /**
     * Event minute.
     */
    #[ORM\Column]
    #[Assert\NotBlank]
    #[Groups(['match_event:read', 'match_event:write', 'sport_match:read'])]
    private ?int $eventMinute = null;

    /**
     * Extra time minute (e.g., 45+2).
     */
    #[ORM\Column(nullable: true)]
    #[Groups(['match_event:read', 'match_event:write', 'sport_match:read'])]
    private ?int $extraTimeMinute = null;

    /**
     * Score after event (format: "2-1").
     */
    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['match_event:read', 'match_event:write', 'sport_match:read'])]
    private ?string $scoreAfterEvent = null;

    /**
     * Event description.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['match_event:read', 'match_event:write', 'sport_match:read'])]
    private ?string $description = null;

    /**
     * Additional metadata (JSON: assist, video URL, etc.).
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['match_event:read', 'match_event:write'])]
    private ?array $metadata = null;

    /**
     * Timestamps.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['match_event:read'])]
    private ?DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSportMatch(): ?LiveTextSportMatch
    {
        return $this->sportMatch;
    }

    public function setSportMatch(?LiveTextSportMatch $sportMatch): static
    {
        $this->sportMatch = $sportMatch;

        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getTeam(): ?string
    {
        return $this->team;
    }

    public function setTeam(string $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getPlayerName(): ?string
    {
        return $this->playerName;
    }

    public function setPlayerName(?string $playerName): static
    {
        $this->playerName = $playerName;

        return $this;
    }

    public function getSecondPlayerName(): ?string
    {
        return $this->secondPlayerName;
    }

    public function setSecondPlayerName(?string $secondPlayerName): static
    {
        $this->secondPlayerName = $secondPlayerName;

        return $this;
    }

    public function getEventMinute(): ?int
    {
        return $this->eventMinute;
    }

    public function setEventMinute(int $eventMinute): static
    {
        $this->eventMinute = $eventMinute;

        return $this;
    }

    public function getExtraTimeMinute(): ?int
    {
        return $this->extraTimeMinute;
    }

    public function setExtraTimeMinute(?int $extraTimeMinute): static
    {
        $this->extraTimeMinute = $extraTimeMinute;

        return $this;
    }

    public function getScoreAfterEvent(): ?string
    {
        return $this->scoreAfterEvent;
    }

    public function setScoreAfterEvent(?string $scoreAfterEvent): static
    {
        $this->scoreAfterEvent = $scoreAfterEvent;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get formatted minute (e.g., "45+2" for extra time).
     */
    #[Groups(['match_event:read', 'sport_match:read'])]
    public function getFormattedMinute(): string
    {
        if ($this->extraTimeMinute !== null && $this->extraTimeMinute > 0) {
            return $this->eventMinute . '+' . $this->extraTimeMinute;
        }

        return (string) $this->eventMinute;
    }

    /**
     * Get event icon identifier for frontend.
     */
    #[Groups(['match_event:read', 'sport_match:read'])]
    public function getEventIcon(): string
    {
        return match ($this->eventType) {
            'goal', 'penalty_goal' => 'goal',
            'own_goal' => 'own-goal',
            'missed_penalty' => 'missed-penalty',
            'yellow_card' => 'yellow-card',
            'red_card', 'second_yellow_card' => 'red-card',
            'substitution' => 'substitution',
            'var_check', 'var_goal_cancelled', 'var_penalty' => 'var',
            'injury' => 'injury',
            default => 'event'
        };
    }
}
