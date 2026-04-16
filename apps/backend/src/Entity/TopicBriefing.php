<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\BriefingCadence;
use App\Enum\BriefingStatus;
use App\Repository\TopicBriefingRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TopicBriefingRepository::class)]
#[ORM\Table(name: 'topic_briefings')]
#[ORM\Index(name: 'idx_tb_topic_cadence', columns: ['topic_id', 'cadence'])]
#[ORM\Index(name: 'idx_tb_cadence_status', columns: ['cadence', 'status'])]
#[ORM\Index(name: 'idx_tb_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_tb_period', columns: ['period_from', 'period_to'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/briefings/{id}',
            normalizationContext: ['groups' => ['briefing:read']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new GetCollection(
            uriTemplate: '/admin/briefings',
            normalizationContext: ['groups' => ['briefing:read']],
            security: "is_granted('ROLE_ADMIN')",
            paginationItemsPerPage: 20,
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true,
        ),
    ],
    order: ['createdAt' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'cadence' => 'exact',
    'status' => 'exact',
    'topic.id' => 'exact',
    'topic.title' => 'partial',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'prCount'])]
class TopicBriefing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['briefing:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Topic::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['briefing:read'])]
    private Topic $topic;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: BriefingCadence::class)]
    #[Groups(['briefing:read'])]
    private BriefingCadence $cadence;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: BriefingStatus::class, options: ['default' => 'pending'])]
    #[Groups(['briefing:read'])]
    private BriefingStatus $status = BriefingStatus::PENDING;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['briefing:read'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['briefing:read'])]
    private ?string $summaryShort = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['briefing:read'])]
    private ?string $summaryLong = null;

    /**
     * @var list<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['briefing:read'])]
    private ?array $keyFacts = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['briefing:read'])]
    private ?string $whyItMatters = null;

    /** Raw Gemini JSON response — only exposed in debug serialization group */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['briefing:debug'])]
    private ?string $geminiDraftRaw = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['briefing:read'])]
    private bool $claudePolished = false;

    /** Number of PressReleases included in this briefing */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['briefing:read'])]
    private int $prCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['briefing:read'])]
    private DateTimeImmutable $periodFrom;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['briefing:read'])]
    private DateTimeImmutable $periodTo;

    /** When AI generation completed (null if still pending/generating) */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['briefing:read'])]
    private ?DateTimeImmutable $generatedAt = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['briefing:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['briefing:read'])]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct(
        Topic $topic,
        BriefingCadence $cadence,
        DateTimeImmutable $periodFrom,
        DateTimeImmutable $periodTo,
    ) {
        $this->topic = $topic;
        $this->cadence = $cadence;
        $this->periodFrom = $periodFrom;
        $this->periodTo = $periodTo;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTopic(): Topic
    {
        return $this->topic;
    }

    public function getCadence(): BriefingCadence
    {
        return $this->cadence;
    }

    public function getStatus(): BriefingStatus
    {
        return $this->status;
    }

    public function setStatus(BriefingStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getSummaryShort(): ?string
    {
        return $this->summaryShort;
    }

    public function setSummaryShort(?string $summaryShort): self
    {
        $this->summaryShort = $summaryShort;

        return $this;
    }

    public function getSummaryLong(): ?string
    {
        return $this->summaryLong;
    }

    public function setSummaryLong(?string $summaryLong): self
    {
        $this->summaryLong = $summaryLong;

        return $this;
    }

    /**
     * @return list<string>|null
     */
    public function getKeyFacts(): ?array
    {
        return $this->keyFacts;
    }

    /**
     * @param list<string>|null $keyFacts
     */
    public function setKeyFacts(?array $keyFacts): self
    {
        $this->keyFacts = $keyFacts;

        return $this;
    }

    public function getWhyItMatters(): ?string
    {
        return $this->whyItMatters;
    }

    public function setWhyItMatters(?string $whyItMatters): self
    {
        $this->whyItMatters = $whyItMatters;

        return $this;
    }

    public function getGeminiDraftRaw(): ?string
    {
        return $this->geminiDraftRaw;
    }

    public function setGeminiDraftRaw(?string $geminiDraftRaw): self
    {
        $this->geminiDraftRaw = $geminiDraftRaw;

        return $this;
    }

    public function isClaudePolished(): bool
    {
        return $this->claudePolished;
    }

    public function setClaudePolished(bool $claudePolished): self
    {
        $this->claudePolished = $claudePolished;

        return $this;
    }

    public function getPrCount(): int
    {
        return $this->prCount;
    }

    public function setPrCount(int $prCount): self
    {
        $this->prCount = $prCount;

        return $this;
    }

    public function getPeriodFrom(): DateTimeImmutable
    {
        return $this->periodFrom;
    }

    public function getPeriodTo(): DateTimeImmutable
    {
        return $this->periodTo;
    }

    public function getGeneratedAt(): ?DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(?DateTimeImmutable $generatedAt): self
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
