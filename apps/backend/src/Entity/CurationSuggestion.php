<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\CurationSuggestionStatus;
use App\Enum\CurationSuggestionType;
use App\Repository\CurationSuggestionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CurationSuggestionRepository::class)]
#[ORM\Table(name: 'curation_suggestions')]
#[ORM\Index(name: 'idx_curation_status', columns: ['status'])]
#[ORM\Index(name: 'idx_curation_type', columns: ['type'])]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['curation:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['curation:read', 'curation:detail']],
        ),
    ],
    order: ['suggestedAt' => 'DESC'],
    paginationItemsPerPage: 30,
)]
#[ApiFilter(SearchFilter::class, properties: ['status' => 'exact', 'type' => 'exact'])]
class CurationSuggestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['curation:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: CurationSuggestionType::class)]
    #[Groups(['curation:read'])]
    private CurationSuggestionType $type;

    #[ORM\Column(length: 20, enumType: CurationSuggestionStatus::class, options: ['default' => 'pending'])]
    #[Groups(['curation:read'])]
    private CurationSuggestionStatus $status = CurationSuggestionStatus::PENDING;

    /** @var int[] Array of cluster IDs involved */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['curation:read'])]
    private array $clusterIds = [];

    #[ORM\Column(nullable: true)]
    #[Groups(['curation:read'])]
    private ?int $targetClusterId = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['curation:read'])]
    private string $reason;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['curation:read'])]
    private ?string $suggestedTopic = null;

    #[ORM\Column(type: Types::FLOAT)]
    #[Groups(['curation:read'])]
    private float $confidence = 0.0;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['curation:read'])]
    private \DateTimeImmutable $suggestedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['curation:read'])]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['curation:read'])]
    private ?string $resolvedBy = null;

    /** @var list<string>|null Cached cluster headlines for UI display (avoids extra queries) */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['curation:read'])]
    private ?array $clusterHeadlines = null;

    public function __construct()
    {
        $this->suggestedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getType(): CurationSuggestionType { return $this->type; }
    public function setType(CurationSuggestionType $type): static { $this->type = $type; return $this; }

    public function getStatus(): CurationSuggestionStatus { return $this->status; }
    public function setStatus(CurationSuggestionStatus $status): static { $this->status = $status; return $this; }

    /** @return int[] */
    public function getClusterIds(): array { return $this->clusterIds; }
    /** @param int[] $clusterIds */
    public function setClusterIds(array $clusterIds): static { $this->clusterIds = $clusterIds; return $this; }

    public function getTargetClusterId(): ?int { return $this->targetClusterId; }
    public function setTargetClusterId(?int $targetClusterId): static { $this->targetClusterId = $targetClusterId; return $this; }

    public function getReason(): string { return $this->reason; }
    public function setReason(string $reason): static { $this->reason = $reason; return $this; }

    public function getSuggestedTopic(): ?string { return $this->suggestedTopic; }
    public function setSuggestedTopic(?string $suggestedTopic): static { $this->suggestedTopic = $suggestedTopic; return $this; }

    public function getConfidence(): float { return $this->confidence; }
    public function setConfidence(float $confidence): static { $this->confidence = $confidence; return $this; }

    public function getSuggestedAt(): \DateTimeImmutable { return $this->suggestedAt; }

    public function getResolvedAt(): ?\DateTimeImmutable { return $this->resolvedAt; }
    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): static { $this->resolvedAt = $resolvedAt; return $this; }

    public function getResolvedBy(): ?string { return $this->resolvedBy; }
    public function setResolvedBy(?string $resolvedBy): static { $this->resolvedBy = $resolvedBy; return $this; }

    /** @return array<string>|null */
    public function getClusterHeadlines(): ?array { return $this->clusterHeadlines; }
    /** @param array<string>|null $clusterHeadlines */
    public function setClusterHeadlines(?array $clusterHeadlines): static { $this->clusterHeadlines = $clusterHeadlines; return $this; }
}
