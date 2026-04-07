<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Enum\StoryClusterStatus;
use App\Repository\StoryClusterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;

#[ORM\Entity(repositoryClass: StoryClusterRepository::class)]
#[ORM\Table(name: 'story_clusters')]
#[ORM\Index(name: 'idx_cluster_importance', columns: ['importance_score'])]
#[ORM\Index(name: 'idx_cluster_status', columns: ['status'])]
#[ORM\Index(name: 'idx_cluster_first_seen', columns: ['first_seen_at'])]
#[ORM\Index(name: 'idx_cluster_promoted', columns: ['promoted_to_press_release'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['cluster:read'], 'enable_max_depth' => true],
        ),
        new Get(
            normalizationContext: ['groups' => ['cluster:read', 'cluster:detail'], 'enable_max_depth' => true],
        ),
        new Patch(
            denormalizationContext: ['groups' => ['cluster:write']],
            normalizationContext: ['groups' => ['cluster:read']],
            security: "is_granted('ROLE_EDITOR')",
        ),
    ],
    order: ['importanceScore' => 'DESC'],
    paginationItemsPerPage: 20,
)]
#[ApiFilter(SearchFilter::class, properties: ['status' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['importanceScore', 'firstSeenAt', 'sourceCount', 'articleCount'])]
class StoryCluster
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cluster:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 500)]
    #[Groups(['cluster:read', 'cluster:write'])]
    private string $primaryHeadline;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['cluster:read', 'cluster:write'])]
    private ?string $summaryShort = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['cluster:detail'])]
    private ?string $summaryMedium = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['cluster:detail'])]
    private ?string $whyItMatters = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['cluster:detail'])]
    private ?array $keyFacts = null;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.0])]
    #[Groups(['cluster:read'])]
    private float $importanceScore = 0.0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['cluster:read'])]
    private int $sourceCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['cluster:read'])]
    private int $articleCount = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['cluster:read'])]
    private ?array $regionTags = null;

    #[ORM\Column(length: 20, enumType: StoryClusterStatus::class, options: ['default' => 'auto'])]
    #[Groups(['cluster:read', 'cluster:write'])]
    private StoryClusterStatus $status = StoryClusterStatus::AUTO;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['cluster:read'])]
    private \DateTimeImmutable $firstSeenAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['cluster:read'])]
    private \DateTimeImmutable $lastUpdatedAt;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['cluster:read'])]
    private bool $promotedToPressRelease = false;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 1.0])]
    #[Groups(['cluster:read', 'cluster:write'])]
    private float $editorialBoost = 1.0;

    /** @var Collection<int, PressRelease> */
    #[ORM\ManyToMany(targetEntity: PressRelease::class, inversedBy: 'storyClusters')]
    #[ORM\JoinTable(name: 'story_cluster_press_release')]
    #[Groups(['cluster:detail'])]
    #[MaxDepth(1)]
    private Collection $pressReleases;

    /** @var Collection<int, Topic> */
    #[ORM\ManyToMany(targetEntity: Topic::class)]
    #[ORM\JoinTable(name: 'story_cluster_topic')]
    #[Groups(['cluster:read'])]
    private Collection $topics;

    public function __construct()
    {
        $this->pressReleases = new ArrayCollection();
        $this->topics = new ArrayCollection();
        $this->firstSeenAt = new \DateTimeImmutable();
        $this->lastUpdatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getPrimaryHeadline(): string { return $this->primaryHeadline; }
    public function setPrimaryHeadline(string $primaryHeadline): static
    {
        $this->primaryHeadline = $primaryHeadline;
        return $this;
    }

    public function getSummaryShort(): ?string { return $this->summaryShort; }
    public function setSummaryShort(?string $summaryShort): static { $this->summaryShort = $summaryShort; return $this; }

    public function getSummaryMedium(): ?string { return $this->summaryMedium; }
    public function setSummaryMedium(?string $summaryMedium): static { $this->summaryMedium = $summaryMedium; return $this; }

    public function getWhyItMatters(): ?string { return $this->whyItMatters; }
    public function setWhyItMatters(?string $whyItMatters): static { $this->whyItMatters = $whyItMatters; return $this; }

    public function getKeyFacts(): ?array { return $this->keyFacts; }
    public function setKeyFacts(?array $keyFacts): static { $this->keyFacts = $keyFacts; return $this; }

    public function getImportanceScore(): float { return $this->importanceScore; }
    public function setImportanceScore(float $importanceScore): static { $this->importanceScore = $importanceScore; return $this; }

    public function getSourceCount(): int { return $this->sourceCount; }
    public function setSourceCount(int $sourceCount): static { $this->sourceCount = $sourceCount; return $this; }

    public function getArticleCount(): int { return $this->articleCount; }
    public function setArticleCount(int $articleCount): static { $this->articleCount = $articleCount; return $this; }

    public function getRegionTags(): ?array { return $this->regionTags; }
    public function setRegionTags(?array $regionTags): static { $this->regionTags = $regionTags; return $this; }

    public function getStatus(): StoryClusterStatus { return $this->status; }
    public function setStatus(StoryClusterStatus $status): static { $this->status = $status; return $this; }

    public function getFirstSeenAt(): \DateTimeImmutable { return $this->firstSeenAt; }
    public function setFirstSeenAt(\DateTimeImmutable $firstSeenAt): static { $this->firstSeenAt = $firstSeenAt; return $this; }

    public function getLastUpdatedAt(): \DateTimeImmutable { return $this->lastUpdatedAt; }
    public function setLastUpdatedAt(\DateTimeImmutable $lastUpdatedAt): static { $this->lastUpdatedAt = $lastUpdatedAt; return $this; }

    public function isPromotedToPressRelease(): bool { return $this->promotedToPressRelease; }
    public function setPromotedToPressRelease(bool $promotedToPressRelease): static { $this->promotedToPressRelease = $promotedToPressRelease; return $this; }

    public function getEditorialBoost(): float { return $this->editorialBoost; }
    public function setEditorialBoost(float $editorialBoost): static { $this->editorialBoost = $editorialBoost; return $this; }

    /** @return Collection<int, PressRelease> */
    public function getPressReleases(): Collection { return $this->pressReleases; }

    public function addPressRelease(PressRelease $pressRelease): static
    {
        if (!$this->pressReleases->contains($pressRelease)) {
            $this->pressReleases->add($pressRelease);
        }
        return $this;
    }

    public function removePressRelease(PressRelease $pressRelease): static
    {
        $this->pressReleases->removeElement($pressRelease);
        return $this;
    }

    /** @return Collection<int, Topic> */
    public function getTopics(): Collection { return $this->topics; }

    public function addTopic(Topic $topic): static
    {
        if (!$this->topics->contains($topic)) {
            $this->topics->add($topic);
        }
        return $this;
    }

    public function removeTopic(Topic $topic): static
    {
        $this->topics->removeElement($topic);
        return $this;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->lastUpdatedAt = new \DateTimeImmutable();
    }

    /**
     * Recalculate sourceCount and articleCount from the pressReleases collection.
     */
    public function recalculateCounts(): void
    {
        $this->articleCount = $this->pressReleases->count();

        $uniqueSources = [];
        foreach ($this->pressReleases as $pr) {
            $hostname = $pr->getSourceHostname();
            if ($hostname !== null) {
                $uniqueSources[$hostname] = true;
            }
        }
        $this->sourceCount = \count($uniqueSources);
    }

    /**
     * Get distinct country codes from press releases' source hostnames.
     *
     * @return list<string>
     */
    #[Groups(['cluster:read'])]
    public function getDistinctCountries(): array
    {
        // Computed from regionTags if available
        return $this->regionTags ?? [];
    }
}
