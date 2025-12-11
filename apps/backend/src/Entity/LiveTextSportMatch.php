<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\LiveTextSportMatchRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Sport Match entity for LiveText.
 *
 * Tracks sport-specific data for live text events:
 * - Match details (teams, sport type, venue)
 * - Live score tracking
 * - Match status and timing
 * - Statistics
 */
#[ORM\Entity(repositoryClass: LiveTextSportMatchRepository::class)]
#[ORM\Table(name: 'live_text_sport_matches')]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_EDITOR')"),
        new Put(security: "is_granted('ROLE_EDITOR')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['sport_match:read']],
    denormalizationContext: ['groups' => ['sport_match:write']]
)]
class LiveTextSportMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['sport_match:read', 'livetext:read', 'livetext:detail'])]
    private ?int $id = null;

    /**
     * Associated LiveText.
     */
    #[ORM\OneToOne(inversedBy: 'sportMatch', targetEntity: LiveText::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['sport_match:read', 'sport_match:write'])]
    private ?LiveText $liveText = null;

    /**
     * Sport type (football, basketball, tennis, etc.).
     */
    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['football', 'basketball', 'tennis', 'handball', 'volleyball', 'rugby', 'hockey', 'other'])]
    #[Groups(['sport_match:read', 'sport_match:write', 'livetext:read'])]
    private ?string $sportType = null;

    /**
     * Home team name.
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['sport_match:read', 'sport_match:write', 'livetext:read'])]
    private ?string $homeTeam = null;

    /**
     * Away team name.
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['sport_match:read', 'sport_match:write', 'livetext:read'])]
    private ?string $awayTeam = null;

    /**
     * Home team logo URL.
     */
    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['sport_match:read', 'sport_match:write', 'livetext:read'])]
    private ?string $homeTeamLogo = null;

    /**
     * Away team logo URL.
     */
    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['sport_match:read', 'sport_match:write', 'livetext:read'])]
    private ?string $awayTeamLogo = null;

    /**
     * Home team score.
     */
    #[ORM\Column]
    #[Groups(['sport_match:read', 'sport_match:write', 'livetext:read'])]
    private int $homeScore = 0;

    /**
     * Away team score.
     */
    #[ORM\Column]
    #[Groups(['sport_match:read', 'sport_match:write', 'livetext:read'])]
    private int $awayScore = 0;

    /**
     * Match status (not_started, live, half_time, finished, postponed, cancelled).
     */
    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: ['not_started', 'live', 'half_time', 'finished', 'postponed', 'cancelled'])]
    #[Groups(['sport_match:read', 'sport_match:write', 'livetext:read'])]
    private string $status = 'not_started';

    /**
     * Current minute (for football, handball, etc.).
     */
    #[ORM\Column(nullable: true)]
    #[Groups(['sport_match:read', 'sport_match:write', 'livetext:read'])]
    private ?int $currentMinute = null;

    /**
     * Current period/quarter/set (for basketball, tennis, etc.).
     */
    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['sport_match:read', 'sport_match:write', 'livetext:read'])]
    private ?string $currentPeriod = null;

    /**
     * Match venue.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['sport_match:read', 'sport_match:write'])]
    private ?string $venue = null;

    /**
     * Competition/League name.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['sport_match:read', 'sport_match:write', 'livetext:read'])]
    private ?string $competition = null;

    /**
     * Match scheduled start time.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['sport_match:read', 'sport_match:write'])]
    private ?DateTimeInterface $scheduledStartTime = null;

    /**
     * Match actual start time.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['sport_match:read', 'sport_match:write'])]
    private ?DateTimeInterface $actualStartTime = null;

    /**
     * Match end time.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['sport_match:read', 'sport_match:write'])]
    private ?DateTimeInterface $endTime = null;

    /**
     * Additional statistics (JSON: possession, shots, corners, etc.).
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['sport_match:read', 'sport_match:write'])]
    private ?array $statistics = null;

    /**
     * Match events (goals, cards, substitutions, etc.).
     */
    #[ORM\OneToMany(mappedBy: 'sportMatch', targetEntity: LiveTextMatchEvent::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['eventMinute' => 'ASC'])]
    #[Groups(['sport_match:read'])]
    private Collection $events;

    /**
     * Timestamps.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['sport_match:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['sport_match:read'])]
    private ?DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLiveText(): ?LiveText
    {
        return $this->liveText;
    }

    public function setLiveText(LiveText $liveText): static
    {
        $this->liveText = $liveText;

        return $this;
    }

    public function getSportType(): ?string
    {
        return $this->sportType;
    }

    public function setSportType(string $sportType): static
    {
        $this->sportType = $sportType;

        return $this;
    }

    public function getHomeTeam(): ?string
    {
        return $this->homeTeam;
    }

    public function setHomeTeam(string $homeTeam): static
    {
        $this->homeTeam = $homeTeam;

        return $this;
    }

    public function getAwayTeam(): ?string
    {
        return $this->awayTeam;
    }

    public function setAwayTeam(string $awayTeam): static
    {
        $this->awayTeam = $awayTeam;

        return $this;
    }

    public function getHomeTeamLogo(): ?string
    {
        return $this->homeTeamLogo;
    }

    public function setHomeTeamLogo(?string $homeTeamLogo): static
    {
        $this->homeTeamLogo = $homeTeamLogo;

        return $this;
    }

    public function getAwayTeamLogo(): ?string
    {
        return $this->awayTeamLogo;
    }

    public function setAwayTeamLogo(?string $awayTeamLogo): static
    {
        $this->awayTeamLogo = $awayTeamLogo;

        return $this;
    }

    public function getHomeScore(): int
    {
        return $this->homeScore;
    }

    public function setHomeScore(int $homeScore): static
    {
        $this->homeScore = $homeScore;
        $this->updatedAt = new DateTime();

        return $this;
    }

    public function getAwayScore(): int
    {
        return $this->awayScore;
    }

    public function setAwayScore(int $awayScore): static
    {
        $this->awayScore = $awayScore;
        $this->updatedAt = new DateTime();

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new DateTime();

        return $this;
    }

    public function getCurrentMinute(): ?int
    {
        return $this->currentMinute;
    }

    public function setCurrentMinute(?int $currentMinute): static
    {
        $this->currentMinute = $currentMinute;
        $this->updatedAt = new DateTime();

        return $this;
    }

    public function getCurrentPeriod(): ?string
    {
        return $this->currentPeriod;
    }

    public function setCurrentPeriod(?string $currentPeriod): static
    {
        $this->currentPeriod = $currentPeriod;

        return $this;
    }

    public function getVenue(): ?string
    {
        return $this->venue;
    }

    public function setVenue(?string $venue): static
    {
        $this->venue = $venue;

        return $this;
    }

    public function getCompetition(): ?string
    {
        return $this->competition;
    }

    public function setCompetition(?string $competition): static
    {
        $this->competition = $competition;

        return $this;
    }

    public function getScheduledStartTime(): ?DateTimeInterface
    {
        return $this->scheduledStartTime;
    }

    public function setScheduledStartTime(?DateTimeInterface $scheduledStartTime): static
    {
        $this->scheduledStartTime = $scheduledStartTime;

        return $this;
    }

    public function getActualStartTime(): ?DateTimeInterface
    {
        return $this->actualStartTime;
    }

    public function setActualStartTime(?DateTimeInterface $actualStartTime): static
    {
        $this->actualStartTime = $actualStartTime;

        return $this;
    }

    public function getEndTime(): ?DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(?DateTimeInterface $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getStatistics(): ?array
    {
        return $this->statistics;
    }

    public function setStatistics(?array $statistics): static
    {
        $this->statistics = $statistics;

        return $this;
    }

    /**
     * @return Collection<int, LiveTextMatchEvent>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(LiveTextMatchEvent $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setSportMatch($this);
        }

        return $this;
    }

    public function removeEvent(LiveTextMatchEvent $event): static
    {
        if ($this->events->removeElement($event)) {
            if ($event->getSportMatch() === $this) {
                $event->setSportMatch(null);
            }
        }

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

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
