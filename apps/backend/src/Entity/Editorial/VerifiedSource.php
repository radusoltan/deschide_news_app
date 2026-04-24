<?php

declare(strict_types=1);

namespace App\Entity\Editorial;

use App\Entity\Source;
use App\Enum\EditorialAlignment;
use App\Repository\Editorial\VerifiedSourceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Editorial-pipeline verified source (ADR-020 D4 amendment 2026-04-18).
 *
 * A `VerifiedSource` is a source usable by the editorial verification layer
 * (tier, editorial alignment, trust baseline, rolling trust). It links to
 * {@see Source} via nullable FK; when linked, delegated accessors
 * ({@see self::getRssUrl()}, {@see self::getCredibility()},
 * {@see self::getCountry()}, {@see self::getName()},
 * {@see self::getFetchFrequencyMinutes()}) pull through the `sources` row so
 * we never duplicate canonical registry data.
 *
 * Fields Source does not yet carry (language, url) are stored locally as
 * nullable own-values so VerifiedSource remains usable even when the linked
 * Source row lacks them (or when the FK is detached, e.g. editorial-only
 * sources pre-Sprint 54 TASS/RIA addition).
 */
#[ORM\Entity(repositoryClass: VerifiedSourceRepository::class)]
#[ORM\Table(name: 'verified_sources')]
#[ORM\Index(name: 'idx_verified_sources_alignment', columns: ['editorial_alignment'])]
#[ORM\Index(name: 'idx_verified_sources_tier', columns: ['tier'])]
#[ORM\Index(name: 'idx_verified_sources_enabled', columns: ['enabled'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('slug', message: 'A VerifiedSource with this slug already exists.')]
class VerifiedSource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9_-]*$/', message: 'Slug must be lowercase alphanumeric with _ or - only.')]
    private string $slug;

    #[ORM\ManyToOne(targetEntity: Source::class)]
    #[ORM\JoinColumn(name: 'source_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Source $source = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 4)]
    private int $tier;

    #[ORM\Column(length: 40, enumType: EditorialAlignment::class)]
    private EditorialAlignment $editorialAlignment;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2)]
    #[Assert\Range(min: 0.0, max: 1.0)]
    private string $trustScoreBaseline;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    #[Assert\Range(min: 0.0, max: 1.0)]
    private ?string $trustScoreRolling = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $editorialNotes = null;

    /** ISO 639-1 language code (Source entity has no language column today). */
    #[ORM\Column(length: 2, nullable: true)]
    #[Assert\Length(exactly: 2)]
    private ?string $language = null;

    /** Canonical website URL (Source entity has no url column today). */
    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Url]
    private ?string $url = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $slug,
        int $tier,
        EditorialAlignment $editorialAlignment,
        string $trustScoreBaseline,
        ?Source $source = null,
    ) {
        $this->slug = $slug;
        $this->tier = $tier;
        $this->editorialAlignment = $editorialAlignment;
        $this->trustScoreBaseline = $trustScoreBaseline;
        $this->source = $source;

        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getSource(): ?Source
    {
        return $this->source;
    }

    public function setSource(?Source $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getTier(): int
    {
        return $this->tier;
    }

    public function setTier(int $tier): self
    {
        $this->tier = $tier;

        return $this;
    }

    public function getEditorialAlignment(): EditorialAlignment
    {
        return $this->editorialAlignment;
    }

    public function setEditorialAlignment(EditorialAlignment $editorialAlignment): self
    {
        $this->editorialAlignment = $editorialAlignment;

        return $this;
    }

    public function getTrustScoreBaseline(): string
    {
        return $this->trustScoreBaseline;
    }

    public function setTrustScoreBaseline(string $trustScoreBaseline): self
    {
        $this->trustScoreBaseline = $trustScoreBaseline;

        return $this;
    }

    public function getTrustScoreRolling(): ?string
    {
        return $this->trustScoreRolling;
    }

    public function setTrustScoreRolling(?string $trustScoreRolling): self
    {
        $this->trustScoreRolling = $trustScoreRolling;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getEditorialNotes(): ?string
    {
        return $this->editorialNotes;
    }

    public function setEditorialNotes(?string $editorialNotes): self
    {
        $this->editorialNotes = $editorialNotes;

        return $this;
    }

    public function setLanguage(?string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // --- Delegated accessors (FK-first per ADR-020 D4) ----------------------
    // When a Source FK is attached, canonical registry fields come from there.
    // Own-stored language/url are used as primary because Source lacks them.

    public function getRssUrl(): ?string
    {
        return $this->source?->getRssUrl();
    }

    public function getCredibility(): ?float
    {
        return $this->source?->getCredibilityWeight();
    }

    public function getCountry(): ?string
    {
        return $this->source?->getCountry();
    }

    /**
     * Display name. Never returns null — falls back to slug when Source is
     * detached so UI/log rendering always has a safe identifier.
     */
    public function getName(): string
    {
        return $this->source?->getName() ?? $this->slug;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getFetchFrequencyMinutes(): ?int
    {
        return $this->source?->getFetchFrequencyMinutes();
    }
}
