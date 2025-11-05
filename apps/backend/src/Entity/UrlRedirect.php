<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UrlRedirectRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * URL Redirect Entity.
 *
 * Tracks URL redirects created when article categories or slugs change.
 * This preserves SEO value by automatically redirecting old URLs to new ones.
 *
 * Example:
 *   Old URL: /politica/reforma-economica
 *   New URL: /economie/reforma-economica
 *   Result: 301 Permanent Redirect from old to new
 *
 * @see docs/url-structure-APPROVED.md - Section: URL Migration & Redirect Strategy
 */
#[ORM\Entity(repositoryClass: UrlRedirectRepository::class)]
#[ORM\Table(name: 'url_redirects')]
#[ORM\Index(columns: ['old_url'], name: 'idx_old_url')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
#[ORM\Index(columns: ['type', 'entity_id'], name: 'idx_entity_type')]
class UrlRedirect
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * The old URL path that should redirect
     * Example: /politica/vechiul-articol.
     */
    #[ORM\Column(type: Types::STRING, length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private string $oldUrl;

    /**
     * The new URL path to redirect to
     * Example: /economie/vechiul-articol.
     */
    #[ORM\Column(type: Types::STRING, length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private string $newUrl;

    /**
     * The locale for this redirect (ro, en, ru).
     */
    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['ro', 'en', 'ru'])]
    private string $locale;

    /**
     * HTTP status code for redirect
     * 301 = Permanent Redirect (default, best for SEO)
     * 302 = Temporary Redirect
     * 307 = Temporary Redirect (preserves method)
     * 308 = Permanent Redirect (preserves method).
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Choice(choices: [301, 302, 307, 308])]
    private int $httpStatusCode = 301;

    /**
     * Type of redirect: article, category, author, or manual.
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['article', 'category', 'author', 'manual'])]
    private string $type;

    /**
     * ID of the entity that caused this redirect
     * For article redirects: article ID
     * For category redirects: category ID.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $entityId = null;

    /**
     * Number of times this redirect has been accessed.
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\GreaterThanOrEqual(0)]
    private int $hitCount = 0;

    /**
     * When this redirect was created.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /**
     * When this redirect was last accessed.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastAccessedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOldUrl(): string
    {
        return $this->oldUrl;
    }

    public function setOldUrl(string $oldUrl): self
    {
        $this->oldUrl = $oldUrl;

        return $this;
    }

    public function getNewUrl(): string
    {
        return $this->newUrl;
    }

    public function setNewUrl(string $newUrl): self
    {
        $this->newUrl = $newUrl;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function setHttpStatusCode(int $httpStatusCode): self
    {
        $this->httpStatusCode = $httpStatusCode;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getHitCount(): int
    {
        return $this->hitCount;
    }

    public function setHitCount(int $hitCount): self
    {
        $this->hitCount = $hitCount;

        return $this;
    }

    /**
     * Increment hit count and update last accessed timestamp.
     */
    public function incrementHitCount(): self
    {
        ++$this->hitCount;
        $this->lastAccessedAt = new DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastAccessedAt(): ?DateTimeImmutable
    {
        return $this->lastAccessedAt;
    }

    public function setLastAccessedAt(?DateTimeImmutable $lastAccessedAt): self
    {
        $this->lastAccessedAt = $lastAccessedAt;

        return $this;
    }
}
