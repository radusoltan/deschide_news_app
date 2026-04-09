<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LiveTextAbTestRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A/B Test for LiveText.
 *
 * Tracks experiments for testing different LiveText configurations
 */
#[ORM\Entity(repositoryClass: LiveTextAbTestRepository::class)]
#[ORM\Table(name: 'live_text_ab_tests')]
#[ORM\Index(name: 'idx_ab_test_status', columns: ['status'])]
#[ORM\Index(name: 'idx_ab_test_start', columns: ['start_date'])]
class LiveTextAbTest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Test name.
     */
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * Test description.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Test hypothesis.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $hypothesis = null;

    /**
     * Test status (draft, running, paused, completed).
     */
    #[ORM\Column(length: 20)]
    private string $status = 'draft';

    /**
     * Variant type (template, layout, theme, etc.).
     */
    #[ORM\Column(length: 50)]
    private ?string $variantType = null;

    /**
     * Control variant (baseline).
     */
    #[ORM\Column(type: Types::JSON)]
    private array $controlVariant = [];

    /**
     * Test variants.
     */
    #[ORM\Column(type: Types::JSON)]
    private array $testVariants = [];

    /**
     * Traffic allocation percentage (0-100).
     */
    #[ORM\Column]
    private int $trafficAllocation = 100;

    /**
     * Target metric (views, time_spent, engagement_rate, etc.).
     */
    #[ORM\Column(length: 100)]
    private ?string $targetMetric = null;

    /**
     * Minimum sample size.
     */
    #[ORM\Column(nullable: true)]
    private ?int $minSampleSize = null;

    /**
     * Statistical significance level (e.g., 0.05 for 95% confidence).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    private ?string $significanceLevel = null;

    /**
     * Start date.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $startDate = null;

    /**
     * End date.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $endDate = null;

    /**
     * Results (JSON).
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $results = null;

    /**
     * Winner variant key.
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $winnerVariant = null;

    /**
     * Confidence level of winner (percentage).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $confidenceLevel = null;

    /**
     * Associated LiveTexts.
     */
    #[ORM\ManyToMany(targetEntity: LiveText::class)]
    #[ORM\JoinTable(name: 'live_text_ab_test_live_texts')]
    private Collection $liveTexts;

    /**
     * Created by.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /**
     * Timestamps.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->liveTexts = new ArrayCollection();
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getHypothesis(): ?string
    {
        return $this->hypothesis;
    }

    public function setHypothesis(?string $hypothesis): static
    {
        $this->hypothesis = $hypothesis;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new DateTime();

        return $this;
    }

    public function getVariantType(): ?string
    {
        return $this->variantType;
    }

    public function setVariantType(string $variantType): static
    {
        $this->variantType = $variantType;

        return $this;
    }

    public function getControlVariant(): array
    {
        return $this->controlVariant;
    }

    public function setControlVariant(array $controlVariant): static
    {
        $this->controlVariant = $controlVariant;

        return $this;
    }

    public function getTestVariants(): array
    {
        return $this->testVariants;
    }

    public function setTestVariants(array $testVariants): static
    {
        $this->testVariants = $testVariants;

        return $this;
    }

    public function getTrafficAllocation(): int
    {
        return $this->trafficAllocation;
    }

    public function setTrafficAllocation(int $trafficAllocation): static
    {
        $this->trafficAllocation = $trafficAllocation;

        return $this;
    }

    public function getTargetMetric(): ?string
    {
        return $this->targetMetric;
    }

    public function setTargetMetric(string $targetMetric): static
    {
        $this->targetMetric = $targetMetric;

        return $this;
    }

    public function getMinSampleSize(): ?int
    {
        return $this->minSampleSize;
    }

    public function setMinSampleSize(?int $minSampleSize): static
    {
        $this->minSampleSize = $minSampleSize;

        return $this;
    }

    public function getSignificanceLevel(): ?string
    {
        return $this->significanceLevel;
    }

    public function setSignificanceLevel(?string $significanceLevel): static
    {
        $this->significanceLevel = $significanceLevel;

        return $this;
    }

    public function getStartDate(): ?DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getResults(): ?array
    {
        return $this->results;
    }

    public function setResults(?array $results): static
    {
        $this->results = $results;
        $this->updatedAt = new DateTime();

        return $this;
    }

    public function getWinnerVariant(): ?string
    {
        return $this->winnerVariant;
    }

    public function setWinnerVariant(?string $winnerVariant): static
    {
        $this->winnerVariant = $winnerVariant;

        return $this;
    }

    public function getConfidenceLevel(): ?string
    {
        return $this->confidenceLevel;
    }

    public function setConfidenceLevel(?string $confidenceLevel): static
    {
        $this->confidenceLevel = $confidenceLevel;

        return $this;
    }

    /**
     * @return Collection<int, LiveText>
     */
    public function getLiveTexts(): Collection
    {
        return $this->liveTexts;
    }

    public function addLiveText(LiveText $liveText): static
    {
        if (!$this->liveTexts->contains($liveText)) {
            $this->liveTexts->add($liveText);
        }

        return $this;
    }

    public function removeLiveText(LiveText $liveText): static
    {
        $this->liveTexts->removeElement($liveText);

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

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

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
