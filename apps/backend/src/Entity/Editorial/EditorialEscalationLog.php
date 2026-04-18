<?php

declare(strict_types=1);

namespace App\Entity\Editorial;

use App\Entity\User;
use App\Enum\EscalationDecision;
use App\Repository\Editorial\EditorialEscalationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Schema-only entity (Sprint 53 scope, ADR-020 D4).
 *
 * Immutable log of editorial escalations triggered when the verification
 * layer cannot resolve a claim autonomously. Records the article state at
 * escalation time, its origin-graph (Sprint 55 pipeline snapshot), and the
 * human editor's decision + timestamp.
 *
 * `category_code` is the sensitive-topic classification string from the
 * editorial taxonomy (`categ_1`..`categ_7` for primary axes, `family_a`..
 * `family_d` for cross-cutting families). Enum is not imposed at this layer
 * because the taxonomy is governed by a separate table and may grow.
 *
 * **Business logic arrives in Sprint 55** — Sprint 53 only provisions the
 * table + constructors + accessors.
 */
#[ORM\Entity(repositoryClass: EditorialEscalationLogRepository::class)]
#[ORM\Table(name: 'editorial_escalation_log')]
#[ORM\Index(name: 'idx_editorial_escalation_log_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_editorial_escalation_log_decision', columns: ['decision'])]
#[ORM\Index(name: 'idx_editorial_escalation_log_category', columns: ['category_code'])]
#[ORM\Index(name: 'idx_editorial_escalation_log_decided_by', columns: ['decided_by_user_id'])]
class EditorialEscalationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $articleSnapshot;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    private string $categoryCode;

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
     * @param array<string, mixed> $articleSnapshot
     * @param array<string, mixed> $originGraphSnapshot
     */
    public function __construct(
        array $articleSnapshot,
        string $categoryCode,
        array $originGraphSnapshot,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->articleSnapshot = $articleSnapshot;
        $this->categoryCode = $categoryCode;
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

    public function getCategoryCode(): string
    {
        return $this->categoryCode;
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
}
