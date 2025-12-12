<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use App\Repository\YouTubeVideoRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * YouTubeVideo represents a single video from YouTube channel
 */
#[ORM\Entity(repositoryClass: YouTubeVideoRepository::class)]
#[ORM\Table(name: 'youtube_videos')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_youtube_video_youtube_id', columns: ['youtube_id'])]
#[ORM\Index(name: 'idx_youtube_video_published', columns: ['published_at'])]
#[ORM\Index(name: 'idx_youtube_video_featured', columns: ['is_featured'])]
#[ORM\Index(name: 'idx_youtube_video_hidden', columns: ['is_hidden'])]
#[UniqueEntity('youtubeId', message: 'This YouTube video ID already exists.')]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/youtube_videos/{id}',
            normalizationContext: ['groups' => ['youtube_video:read', 'youtube_video:detail']]
        ),
        new GetCollection(
            uriTemplate: '/youtube_videos',
            normalizationContext: ['groups' => ['youtube_video:read', 'youtube_video:list']],
            paginationItemsPerPage: 12
        ),
        new Put(
            uriTemplate: '/youtube_videos/{id}',
            security: "is_granted('ROLE_ADMIN')",
            denormalizationContext: ['groups' => ['youtube_video:write']]
        ),
        new Delete(
            uriTemplate: '/youtube_videos/{id}',
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    order: ['publishedAt' => 'DESC'],
    paginationEnabled: true
)]
#[ApiFilter(SearchFilter::class, properties: [
    'title' => 'partial',
    'youtubeId' => 'exact',
    'videoShow.slug' => 'exact',
    'videoShow.id' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['isFeatured', 'isHidden'])]
#[ApiFilter(OrderFilter::class, properties: ['publishedAt', 'viewCount', 'position'])]
class YouTubeVideo implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['youtube_video:read'])]
    private ?int $id = null;

    /**
     * YouTube video ID (e.g., dQw4w9WgXcQ)
     */
    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    #[Groups(['youtube_video:read'])]
    private string $youtubeId = '';

    #[ORM\ManyToOne(targetEntity: VideoShow::class, inversedBy: 'videos')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['youtube_video:read', 'youtube_video:write'])]
    #[MaxDepth(1)]
    private ?VideoShow $videoShow = null;

    #[ORM\Column(length: 500)]
    #[Gedmo\Translatable]
    #[Assert\NotBlank]
    #[Groups(['youtube_video:read', 'youtube_video:write'])]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Translatable]
    #[Groups(['youtube_video:read', 'youtube_video:write', 'youtube_video:detail'])]
    private ?string $description = null;

    /**
     * High-resolution thumbnail URL (maxresdefault or hqdefault)
     */
    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['youtube_video:read'])]
    private ?string $thumbnailUrl = null;

    /**
     * Medium resolution thumbnail URL (mqdefault)
     */
    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['youtube_video:read'])]
    private ?string $thumbnailMedium = null;

    /**
     * Video duration in seconds
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['youtube_video:read'])]
    private ?int $durationSeconds = null;

    /**
     * When the video was published on YouTube
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['youtube_video:read'])]
    private ?DateTimeImmutable $publishedAt = null;

    /**
     * Number of views on YouTube
     */
    #[ORM\Column(type: Types::BIGINT, options: ['default' => 0])]
    #[Groups(['youtube_video:read'])]
    private int $viewCount = 0;

    /**
     * Number of likes on YouTube
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['youtube_video:read'])]
    private int $likeCount = 0;

    /**
     * Manually marked as featured (priority display)
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['youtube_video:read', 'youtube_video:write'])]
    private bool $isFeatured = false;

    /**
     * Hidden from public display (but kept in database)
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['youtube_video:read', 'youtube_video:write'])]
    private bool $isHidden = false;

    /**
     * Manual position override (null = use default sorting)
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['youtube_video:read', 'youtube_video:write'])]
    private ?int $position = null;

    /**
     * When this video was last synced from YouTube API
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['youtube_video:read'])]
    private ?DateTimeImmutable $syncedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['youtube_video:read'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['youtube_video:read'])]
    private DateTimeImmutable $updatedAt;

    /**
     * Locale used for translations
     */
    #[Gedmo\Locale]
    private ?string $locale = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getYoutubeId(): string
    {
        return $this->youtubeId;
    }

    public function setYoutubeId(string $youtubeId): self
    {
        $this->youtubeId = $youtubeId;
        return $this;
    }

    public function getVideoShow(): ?VideoShow
    {
        return $this->videoShow;
    }

    public function setVideoShow(?VideoShow $videoShow): self
    {
        $this->videoShow = $videoShow;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }

    public function setThumbnailUrl(?string $thumbnailUrl): self
    {
        $this->thumbnailUrl = $thumbnailUrl;
        return $this;
    }

    public function getThumbnailMedium(): ?string
    {
        return $this->thumbnailMedium;
    }

    public function setThumbnailMedium(?string $thumbnailMedium): self
    {
        $this->thumbnailMedium = $thumbnailMedium;
        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(?int $durationSeconds): self
    {
        $this->durationSeconds = $durationSeconds;
        return $this;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function setViewCount(int $viewCount): self
    {
        $this->viewCount = $viewCount;
        return $this;
    }

    public function getLikeCount(): int
    {
        return $this->likeCount;
    }

    public function setLikeCount(int $likeCount): self
    {
        $this->likeCount = $likeCount;
        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): self
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }

    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    public function setIsHidden(bool $isHidden): self
    {
        $this->isHidden = $isHidden;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getSyncedAt(): ?DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(?DateTimeImmutable $syncedAt): self
    {
        $this->syncedAt = $syncedAt;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setTranslatableLocale(?string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Get formatted duration string (e.g., "1:30:45" or "45:30")
     */
    #[Groups(['youtube_video:read'])]
    public function getDurationFormatted(): ?string
    {
        if ($this->durationSeconds === null) {
            return null;
        }

        $hours = floor($this->durationSeconds / 3600);
        $minutes = floor(($this->durationSeconds % 3600) / 60);
        $seconds = $this->durationSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Get YouTube watch URL
     */
    #[Groups(['youtube_video:read'])]
    public function getYoutubeUrl(): string
    {
        return 'https://www.youtube.com/watch?v=' . $this->youtubeId;
    }

    /**
     * Get YouTube embed URL (privacy-enhanced)
     */
    #[Groups(['youtube_video:read'])]
    public function getEmbedUrl(): string
    {
        return 'https://www.youtube-nocookie.com/embed/' . $this->youtubeId;
    }
}
