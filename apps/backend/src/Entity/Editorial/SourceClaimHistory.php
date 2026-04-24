<?php

declare(strict_types=1);

namespace App\Entity\Editorial;

use App\Enum\ClaimOutcome;
use App\Repository\Editorial\SourceClaimHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Schema-only entity (Sprint 53 scope, ADR-020 D4).
 *
 * Tracks individual claims observed on a {@see VerifiedSource} and whether
 * verification subsequently confirmed or infirmed them. Drives the rolling
 * trust-score adjustment applied to VerifiedSource.trustScoreRolling.
 *
 * **Business logic arrives in Sprint 54** — Sprint 53 only provisions the
 * table + constructors + accessors so downstream services can type-check
 * against stable shape.
 */
#[ORM\Entity(repositoryClass: SourceClaimHistoryRepository::class)]
#[ORM\Table(name: 'source_claim_history')]
#[ORM\Index(name: 'idx_source_claim_history_source', columns: ['verified_source_id'])]
#[ORM\Index(name: 'idx_source_claim_history_observed_at', columns: ['observed_at'])]
#[ORM\Index(name: 'idx_source_claim_history_outcome', columns: ['outcome'])]
class SourceClaimHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VerifiedSource::class)]
    #[ORM\JoinColumn(name: 'verified_source_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private VerifiedSource $verifiedSource;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $claimText;

    #[ORM\Column(length: 20, enumType: ClaimOutcome::class)]
    private ClaimOutcome $outcome;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $observedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct(
        VerifiedSource $verifiedSource,
        string $claimText,
        ClaimOutcome $outcome,
        ?\DateTimeImmutable $observedAt = null,
    ) {
        $this->verifiedSource = $verifiedSource;
        $this->claimText = $claimText;
        $this->outcome = $outcome;
        $this->observedAt = $observedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVerifiedSource(): VerifiedSource
    {
        return $this->verifiedSource;
    }

    public function getClaimText(): string
    {
        return $this->claimText;
    }

    public function getOutcome(): ClaimOutcome
    {
        return $this->outcome;
    }

    public function setOutcome(ClaimOutcome $outcome): self
    {
        $this->outcome = $outcome;

        return $this;
    }

    public function getObservedAt(): \DateTimeImmutable
    {
        return $this->observedAt;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): self
    {
        $this->confirmedAt = $confirmedAt;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }
}
