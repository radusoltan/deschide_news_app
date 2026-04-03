<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Repository\PressReleaseRepository;
use App\State\PressReleaseApproveProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PressReleaseRepository::class)]
#[ORM\Table(name: 'press_releases')]
#[ORM\Index(name: 'idx_press_release_status', columns: ['status'])]
#[ORM\Index(name: 'idx_press_release_received', columns: ['received_at'])]
#[ORM\Index(name: 'idx_press_release_content_hash', columns: ['content_hash'])]
#[ORM\Index(name: 'idx_press_release_source_type', columns: ['source_type'])]
#[ORM\UniqueConstraint(name: 'uniq_content_hash_source_type', columns: ['content_hash', 'source_type'])]
#[UniqueEntity('sourceEmailId', message: 'This email has already been imported.')]
#[ORM\HasLifecycleCallbacks]
#[ApiFilter(SearchFilter::class, properties: ['status' => 'exact', 'categorySlug' => 'exact', 'senderAddress' => 'partial', 'sourceType' => 'exact'])]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['press:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['press:read', 'press:detail']],
        ),
        new Patch(
            denormalizationContext: ['groups' => ['press:write']],
            normalizationContext: ['groups' => ['press:read']],
        ),
        new Post(
            uriTemplate: '/press_releases/{id}/approve',
            normalizationContext: ['groups' => ['press:read']],
            processor: PressReleaseApproveProcessor::class,
            description: 'Approve a press release and create an article from it',
        ),
        new Delete(),
    ],
    order: ['receivedAt' => 'DESC'],
    paginationItemsPerPage: 20,
)]
class PressRelease
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['press:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['press:read', 'press:write'])]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['press:read', 'press:write'])]
    private ?string $lead = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['press:read', 'press:detail'])]
    private string $content;

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    #[Groups(['press:read'])]
    private ?string $sourceEmailId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['press:read'])]
    private ?string $senderAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['press:read'])]
    private ?string $senderName = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['press:read'])]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 50)]
    #[Groups(['press:read', 'press:write'])]
    private string $categorySlug;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['press:read'])]
    private ?string $emailSubject = null;

    #[ORM\Column(enumType: PressReleaseStatus::class)]
    #[Groups(['press:read', 'press:write'])]
    private PressReleaseStatus $status = PressReleaseStatus::PENDING;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['press:read'])]
    private ?string $contentHash = null;

    #[ORM\Column(enumType: SourceType::class, options: ['default' => 'email'])]
    #[Groups(['press:read'])]
    private SourceType $sourceType = SourceType::EMAIL;

    #[ORM\Column(length: 5, nullable: true, options: ['default' => 'ro'])]
    #[Groups(['press:read'])]
    private ?string $originalLanguage = 'ro';

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['press:read'])]
    private ?string $sourceName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['press:read', 'press:write'])]
    private ?string $rejectionReason = null;

    #[ORM\Column]
    #[Groups(['press:read'])]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column]
    #[Groups(['press:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['press:read'])]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['press:read'])]
    private ?User $processedBy = null;

    #[ORM\OneToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['press:read'])]
    private ?Article $article = null;

    #[Groups(['press:read'])]
    public function getArticleId(): ?int
    {
        return $this->article?->getId();
    }

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['press:read'])]
    private int $contentLength = 0;

    /** Original attachment filename from email (e.g. "masa-bucuriei.jpg") */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['press:read'])]
    private ?string $attachmentFilename = null;

    /** Path to downloaded attachment on disk (relative to project dir) */
    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['press:read'])]
    private ?string $attachmentPath = null;

    /** MIME type of attachment */
    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['press:read'])]
    private ?string $attachmentMimeType = null;

    /** Attachment size in bytes */
    #[ORM\Column(nullable: true)]
    #[Groups(['press:read'])]
    private ?int $attachmentSize = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getLead(): ?string { return $this->lead; }
    public function setLead(?string $lead): static { $this->lead = $lead; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static
    {
        $this->content = $content;
        $this->contentLength = mb_strlen(strip_tags($content));
        return $this;
    }

    public function getSourceEmailId(): ?string { return $this->sourceEmailId; }
    public function setSourceEmailId(?string $sourceEmailId): static { $this->sourceEmailId = $sourceEmailId; return $this; }

    public function getSenderAddress(): ?string { return $this->senderAddress; }
    public function setSenderAddress(?string $senderAddress): static { $this->senderAddress = $senderAddress; return $this; }

    public function getSenderName(): ?string { return $this->senderName; }
    public function setSenderName(?string $senderName): static { $this->senderName = $senderName; return $this; }

    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $sourceUrl): static { $this->sourceUrl = $sourceUrl; return $this; }

    public function getCategorySlug(): string { return $this->categorySlug; }
    public function setCategorySlug(string $categorySlug): static { $this->categorySlug = $categorySlug; return $this; }

    public function getEmailSubject(): ?string { return $this->emailSubject; }
    public function setEmailSubject(?string $emailSubject): static { $this->emailSubject = $emailSubject; return $this; }

    public function getStatus(): PressReleaseStatus { return $this->status; }
    public function setStatus(PressReleaseStatus $status): static { $this->status = $status; return $this; }

    public function getReceivedAt(): \DateTimeImmutable { return $this->receivedAt; }
    public function setReceivedAt(\DateTimeImmutable $receivedAt): static { $this->receivedAt = $receivedAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getProcessedAt(): ?\DateTimeImmutable { return $this->processedAt; }
    public function setProcessedAt(?\DateTimeImmutable $processedAt): static { $this->processedAt = $processedAt; return $this; }

    public function getProcessedBy(): ?User { return $this->processedBy; }
    public function setProcessedBy(?User $processedBy): static { $this->processedBy = $processedBy; return $this; }

    public function getArticle(): ?Article { return $this->article; }
    public function setArticle(?Article $article): static { $this->article = $article; return $this; }

    public function getContentLength(): int { return $this->contentLength; }

    public function getContentHash(): ?string { return $this->contentHash; }
    public function setContentHash(?string $contentHash): static { $this->contentHash = $contentHash; return $this; }

    public function getSourceType(): SourceType { return $this->sourceType; }
    public function setSourceType(SourceType $sourceType): static { $this->sourceType = $sourceType; return $this; }

    public function getOriginalLanguage(): ?string { return $this->originalLanguage; }
    public function setOriginalLanguage(?string $originalLanguage): static { $this->originalLanguage = $originalLanguage; return $this; }

    public function getSourceName(): ?string { return $this->sourceName; }
    public function setSourceName(?string $sourceName): static { $this->sourceName = $sourceName; return $this; }

    public function getRejectionReason(): ?string { return $this->rejectionReason; }
    public function setRejectionReason(?string $rejectionReason): static { $this->rejectionReason = $rejectionReason; return $this; }

    public function getAttachmentFilename(): ?string { return $this->attachmentFilename; }
    public function setAttachmentFilename(?string $attachmentFilename): static { $this->attachmentFilename = $attachmentFilename; return $this; }

    public function getAttachmentPath(): ?string { return $this->attachmentPath; }
    public function setAttachmentPath(?string $attachmentPath): static { $this->attachmentPath = $attachmentPath; return $this; }

    public function getAttachmentMimeType(): ?string { return $this->attachmentMimeType; }
    public function setAttachmentMimeType(?string $attachmentMimeType): static { $this->attachmentMimeType = $attachmentMimeType; return $this; }

    public function getAttachmentSize(): ?int { return $this->attachmentSize; }
    public function setAttachmentSize(?int $attachmentSize): static { $this->attachmentSize = $attachmentSize; return $this; }

    public function hasAttachment(): bool { return $this->attachmentPath !== null; }
}
