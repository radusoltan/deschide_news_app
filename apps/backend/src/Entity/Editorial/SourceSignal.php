<?php

declare(strict_types=1);

namespace App\Entity\Editorial;

use App\Repository\Editorial\SourceSignalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Raw signal ingested from a {@see VerifiedSource} (ADR-020 D4 amendment 2026-04-18).
 *
 * One signal per FeedItem observed on a monitored source. `raw_content_hash`
 * (SHA-256 over title + canonical URL + summary) is unique per VerifiedSource
 * so re-ingesting an unchanged feed is a no-op; mutated content produces a
 * new signal.
 *
 * Sprint 53 persists only the raw payload. Downstream enrichment
 * (`sourceAttribution`, `sourceLinksOut`) is populated by Sprint 54's
 * `SourceAttributionExtractor` once the LLM layer lands.
 */
#[ORM\Entity(repositoryClass: SourceSignalRepository::class)]
#[ORM\Table(name: 'source_signals')]
#[ORM\UniqueConstraint(name: 'uniq_source_signal_source_hash', columns: ['verified_source_id', 'raw_content_hash'])]
#[ORM\Index(name: 'idx_source_signals_source_captured', columns: ['verified_source_id', 'captured_at'])]
#[ORM\Index(name: 'idx_source_signals_captured_at', columns: ['captured_at'])]
#[ORM\Index(name: 'idx_source_signals_published_at', columns: ['published_at'])]
class SourceSignal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VerifiedSource::class)]
    #[ORM\JoinColumn(name: 'verified_source_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private VerifiedSource $verifiedSource;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $capturedAt;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $sourceUrl;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $canonicalUrl = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rawSummary = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /** Populated by Sprint 54 SourceAttributionExtractor; nullable in Sprint 53. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $sourceAttribution = null;

    /**
     * Outbound links mentioned inside the signal's body, extracted by Sprint 54.
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $sourceLinksOut = null;

    /** SHA-256 hex digest, 64 chars. */
    #[ORM\Column(length: 64)]
    #[Assert\Length(exactly: 64)]
    private string $rawContentHash;

    /**
     * Full FeedItem serialization (or equivalent) for audit / replay.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rawPayload = null;

    public function __construct(
        VerifiedSource $verifiedSource,
        string $sourceUrl,
        string $title,
        string $rawContentHash,
        ?\DateTimeImmutable $capturedAt = null,
    ) {
        $this->verifiedSource = $verifiedSource;
        $this->sourceUrl = $sourceUrl;
        $this->title = $title;
        $this->rawContentHash = $rawContentHash;
        $this->capturedAt = $capturedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVerifiedSource(): VerifiedSource
    {
        return $this->verifiedSource;
    }

    public function getCapturedAt(): \DateTimeImmutable
    {
        return $this->capturedAt;
    }

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function setCanonicalUrl(?string $canonicalUrl): self
    {
        $this->canonicalUrl = $canonicalUrl;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getRawSummary(): ?string
    {
        return $this->rawSummary;
    }

    public function setRawSummary(?string $rawSummary): self
    {
        $this->rawSummary = $rawSummary;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getSourceAttribution(): ?string
    {
        return $this->sourceAttribution;
    }

    public function setSourceAttribution(?string $sourceAttribution): self
    {
        $this->sourceAttribution = $sourceAttribution;

        return $this;
    }

    /** @return list<string>|null */
    public function getSourceLinksOut(): ?array
    {
        return $this->sourceLinksOut;
    }

    /** @param list<string>|null $sourceLinksOut */
    public function setSourceLinksOut(?array $sourceLinksOut): self
    {
        $this->sourceLinksOut = $sourceLinksOut;

        return $this;
    }

    public function getRawContentHash(): string
    {
        return $this->rawContentHash;
    }

    /** @return array<string, mixed>|null */
    public function getRawPayload(): ?array
    {
        return $this->rawPayload;
    }

    /** @param array<string, mixed>|null $rawPayload */
    public function setRawPayload(?array $rawPayload): self
    {
        $this->rawPayload = $rawPayload;

        return $this;
    }
}
