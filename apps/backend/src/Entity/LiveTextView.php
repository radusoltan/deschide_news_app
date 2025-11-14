<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LiveTextViewRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * LiveTextView entity for tracking views and analytics.
 *
 * Tracks individual view sessions including time spent, IP address, and user agent.
 * Used for calculating total views, unique viewers, and engagement metrics.
 */
#[ORM\Entity(repositoryClass: LiveTextViewRepository::class)]
#[ORM\Table(name: 'live_text_views')]
#[ORM\Index(name: 'idx_live_text_view_live_text', columns: ['live_text_id'])]
#[ORM\Index(name: 'idx_live_text_view_session', columns: ['session_id'])]
#[ORM\Index(name: 'idx_live_text_view_ip', columns: ['ip_address'])]
#[ORM\Index(name: 'idx_live_text_view_viewed_at', columns: ['viewed_at'])]
class LiveTextView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['livetext_view:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LiveText::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['livetext_view:read'])]
    private ?LiveText $liveText = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['livetext_view:read'])]
    private ?User $user = null;

    /**
     * Unique session identifier for tracking individual viewing sessions.
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['livetext_view:read', 'livetext_view:write'])]
    private ?string $sessionId = null;

    /**
     * Total time spent viewing in seconds.
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['livetext_view:read', 'livetext_view:write'])]
    private int $timeSpent = 0;

    /**
     * IP address for unique viewer tracking.
     */
    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    #[Groups(['livetext_view:read'])]
    private ?string $ipAddress = null;

    /**
     * User agent for analytics.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['livetext_view:read'])]
    private ?string $userAgent = null;

    /**
     * Initial view timestamp.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['livetext_view:read'])]
    private ?DateTimeInterface $viewedAt = null;

    /**
     * Last activity timestamp for tracking active sessions.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['livetext_view:read'])]
    private ?DateTimeInterface $lastActivityAt = null;

    public function __construct()
    {
        $this->viewedAt = new DateTime();
        $this->lastActivityAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLiveText(): ?LiveText
    {
        return $this->liveText;
    }

    public function setLiveText(?LiveText $liveText): static
    {
        $this->liveText = $liveText;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): static
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function getTimeSpent(): int
    {
        return $this->timeSpent;
    }

    public function setTimeSpent(int $timeSpent): static
    {
        $this->timeSpent = $timeSpent;

        return $this;
    }

    public function addTimeSpent(int $seconds): static
    {
        $this->timeSpent += $seconds;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getViewedAt(): ?DateTimeInterface
    {
        return $this->viewedAt;
    }

    public function setViewedAt(DateTimeInterface $viewedAt): static
    {
        $this->viewedAt = $viewedAt;

        return $this;
    }

    public function getLastActivityAt(): ?DateTimeInterface
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(DateTimeInterface $lastActivityAt): static
    {
        $this->lastActivityAt = $lastActivityAt;

        return $this;
    }

    /**
     * Update last activity timestamp to now.
     */
    public function updateActivity(): static
    {
        $this->lastActivityAt = new DateTime();

        return $this;
    }
}
