<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LiveTextPostEngagementRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Post Engagement Tracking.
 *
 * Tracks detailed user engagement with individual posts for heatmap analysis
 */
#[ORM\Entity(repositoryClass: LiveTextPostEngagementRepository::class)]
#[ORM\Table(name: 'live_text_post_engagements')]
#[ORM\Index(name: 'idx_post_engagement_post', columns: ['post_id'])]
#[ORM\Index(name: 'idx_post_engagement_session', columns: ['session_id'])]
#[ORM\Index(name: 'idx_post_engagement_created', columns: ['created_at'])]
class LiveTextPostEngagement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Associated post.
     */
    #[ORM\ManyToOne(targetEntity: LiveTextPost::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?LiveTextPost $post = null;

    /**
     * Session ID for anonymous tracking.
     */
    #[ORM\Column(length: 255)]
    private ?string $sessionId = null;

    /**
     * User (nullable for anonymous).
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    /**
     * Engagement type (view, read, click, share, reaction).
     */
    #[ORM\Column(length: 50)]
    private ?string $engagementType = null;

    /**
     * Time spent on post (seconds).
     */
    #[ORM\Column(nullable: true)]
    private ?int $timeSpent = null;

    /**
     * Scroll depth percentage (0-100).
     */
    #[ORM\Column(nullable: true)]
    private ?int $scrollDepth = null;

    /**
     * Clicked element (link, image, etc.).
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clickedElement = null;

    /**
     * Metadata (JSON).
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    /**
     * IP address.
     */
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    /**
     * User agent.
     */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userAgent = null;

    /**
     * Created timestamp.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
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

    public function getPost(): ?LiveTextPost
    {
        return $this->post;
    }

    public function setPost(?LiveTextPost $post): static
    {
        $this->post = $post;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getEngagementType(): ?string
    {
        return $this->engagementType;
    }

    public function setEngagementType(string $engagementType): static
    {
        $this->engagementType = $engagementType;

        return $this;
    }

    public function getTimeSpent(): ?int
    {
        return $this->timeSpent;
    }

    public function setTimeSpent(?int $timeSpent): static
    {
        $this->timeSpent = $timeSpent;

        return $this;
    }

    public function getScrollDepth(): ?int
    {
        return $this->scrollDepth;
    }

    public function setScrollDepth(?int $scrollDepth): static
    {
        $this->scrollDepth = $scrollDepth;

        return $this;
    }

    public function getClickedElement(): ?string
    {
        return $this->clickedElement;
    }

    public function setClickedElement(?string $clickedElement): static
    {
        $this->clickedElement = $clickedElement;

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

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
