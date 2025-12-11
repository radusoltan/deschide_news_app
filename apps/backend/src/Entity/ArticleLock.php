<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\ArticleLockRepository;
use App\State\ArticleLockProvider;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ArticleLockRepository::class)]
#[ORM\Table(name: 'article_locks')]
#[ORM\Index(name: 'idx_article_lock_article', columns: ['article_id'])]
#[ORM\Index(name: 'idx_article_lock_expires', columns: ['expires_at'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/article_locks/{id}',
            normalizationContext: ['groups' => ['article_lock:read']],
            provider: ArticleLockProvider::class
        ),
        new GetCollection(
            uriTemplate: '/article_locks',
            normalizationContext: ['groups' => ['article_lock:read']],
            provider: ArticleLockProvider::class
        ),
    ]
)]
class ArticleLock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['article_lock:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['article_lock:read', 'article_lock:create'])]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['article_lock:read'])]
    private ?User $lockedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['article_lock:read'])]
    private ?DateTimeImmutable $lockedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['article_lock:read'])]
    private ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['article_lock:read'])]
    private ?string $sessionId = null;

    public function __construct()
    {
        $this->lockedAt = new DateTimeImmutable();
        $this->refreshExpiration();
    }

    public function refreshExpiration(): void
    {
        // Lock expires after 15 minutes of inactivity
        $this->expiresAt = new DateTimeImmutable('+15 minutes');
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): self
    {
        $this->article = $article;

        return $this;
    }

    public function getLockedBy(): ?User
    {
        return $this->lockedBy;
    }

    public function setLockedBy(?User $lockedBy): self
    {
        $this->lockedBy = $lockedBy;

        return $this;
    }

    public function getLockedAt(): ?DateTimeImmutable
    {
        return $this->lockedAt;
    }

    public function setLockedAt(DateTimeImmutable $lockedAt): self
    {
        $this->lockedAt = $lockedAt;

        return $this;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }
}
