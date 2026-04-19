<?php

declare(strict_types=1);

namespace App\Entity\Editorial;

use App\Entity\User;
use App\Enum\Editorial\EscalationCategory;
use App\Enum\EscalationDecision;
use App\Repository\Editorial\EditorialEscalationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Immutable log of editorial escalations triggered when the verification
 * layer cannot resolve a claim autonomously or when {@see \App\Service\Editorial\Guard\LegalGuard}
 * flags Category 6 content (ADR-020 D7/D8).
 *
 * Sprint 53 provisioned the table + scalar accessors. Sprint 55 T55.8 adds:
 *   - {@see EscalationCategory} enum typing on the category column (tightened
 *     VARCHAR from 40 → 20 chars; values stay in the `categ_*` / `family_*`
 *     short code space).
 *   - `expires_at` DATETIME_IMMUTABLE column + partial index, driving the
 *     T55.11 SLA auto-expire scheduler.
 *
 * The getter+setter names preserve the S53 API shape (`getCategoryCode()`
 * remains available as a string shim) so legacy callers do not break.
 */
#[ORM\Entity(repositoryClass: EditorialEscalationLogRepository::class)]
#[ORM\Table(name: 'editorial_escalation_log')]
#[ORM\Index(name: 'idx_editorial_escalation_log_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_editorial_escalation_log_decision', columns: ['decision'])]
#[ORM\Index(name: 'idx_editorial_escalation_log_category', columns: ['category_code'])]
#[ORM\Index(name: 'idx_editorial_escalation_log_decided_by', columns: ['decided_by_user_id'])]
// Sprint 55 T55.8 — partial index for SLA expiry scanner (T55.11).
#[ORM\Index(name: 'idx_esc_log_expires_pending', columns: ['expires_at'], options: ['where' => '(decision IS NULL)'])]
class EditorialEscalationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $articleSnapshot;

    #[ORM\Column(name: 'category_code', length: 20, enumType: EscalationCategory::class)]
    private EscalationCategory $category;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $originGraphSnapshot;

    #[ORM\Column(length: 20, nullable: true, enumType: EscalationDecision::class)]
    private ?EscalationDecision $decision = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'decided_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $decidedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    /**
     * SLA expiry timestamp (Sprint 55 T55.8, audit D9). NULL when decided.
     * The partial index `idx_esc_log_expires_pending` covers only rows where
     * decision IS NULL so the scheduler scan is cheap.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    /**
     * @param array<string, mixed> $articleSnapshot
     * @param array<string, mixed> $originGraphSnapshot
     */
    public function __construct(
        array $articleSnapshot,
        EscalationCategory $category,
        array $originGraphSnapshot,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->articleSnapshot = $articleSnapshot;
        $this->category = $category;
        $this->originGraphSnapshot = $originGraphSnapshot;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /** @return array<string, mixed> */
    public function getArticleSnapshot(): array
    {
        return $this->articleSnapshot;
    }

    public function getCategory(): EscalationCategory
    {
        return $this->category;
    }

    /**
     * Short-code accessor kept for S53 backward compatibility.
     * Returns the enum's value (e.g. 'categ_3', 'family_b').
     */
    public function getCategoryCode(): string
    {
        return $this->category->value;
    }

    /** @return array<string, mixed> */
    public function getOriginGraphSnapshot(): array
    {
        return $this->originGraphSnapshot;
    }

    public function getDecision(): ?EscalationDecision
    {
        return $this->decision;
    }

    public function setDecision(?EscalationDecision $decision): self
    {
        $this->decision = $decision;

        return $this;
    }

    public function getDecidedBy(): ?User
    {
        return $this->decidedBy;
    }

    public function setDecidedBy(?User $decidedBy): self
    {
        $this->decidedBy = $decidedBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function setDecidedAt(?\DateTimeImmutable $decidedAt): self
    {
        $this->decidedAt = $decidedAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }
}
