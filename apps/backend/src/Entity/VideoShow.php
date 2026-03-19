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
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\VideoShowRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * VideoShow represents a YouTube video series/emission (e.g., "Deschide LIVE", "Interviuri")
 */
#[ORM\Entity(repositoryClass: VideoShowRepository::class)]
#[ORM\Table(name: 'video_shows')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_video_show_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_video_show_position', columns: ['position'])]
#[UniqueEntity('slug', message: 'This slug is already in use.')]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/video_shows/{id}',
            normalizationContext: ['groups' => ['video_show:read', 'video_show:detail']]
        ),
        new GetCollection(
            uriTemplate: '/video_shows',
            normalizationContext: ['groups' => ['video_show:read', 'video_show:list']],
            paginationItemsPerPage: 20
        ),
        new Post(
            uriTemplate: '/video_shows',
            security: "is_granted('ROLE_ADMIN')",
            denormalizationContext: ['groups' => ['video_show:write']]
        ),
        new Put(
            uriTemplate: '/video_shows/{id}',
            security: "is_granted('ROLE_ADMIN')",
            denormalizationContext: ['groups' => ['video_show:write']]
        ),
        new Delete(
            uriTemplate: '/video_shows/{id}',
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    order: ['position' => 'ASC', 'name' => 'ASC'],
    paginationEnabled: true
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'slug' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive'])]
#[ApiFilter(OrderFilter::class, properties: ['position', 'name', 'createdAt'])]
class VideoShow implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['video_show:read', 'youtube_video:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Translatable]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['video_show:read', 'video_show:write', 'youtube_video:read'])]
    private string $name = '';

    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    #[Groups(['video_show:read', 'youtube_video:read'])]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Translatable]
    #[Groups(['video_show:read', 'video_show:write', 'video_show:detail'])]
    private ?string $description = null;

    /**
     * YouTube playlist ID (e.g., PLxxxxxxx)
     */
    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['video_show:read', 'video_show:write'])]
    private ?string $youtubePlaylistId = null;

    /**
     * YouTube channel ID (e.g., UCxxxxxxx)
     */
    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['video_show:read', 'video_show:write'])]
    private ?string $youtubeChannelId = null;

    /**
     * Show thumbnail/cover image URL
     */
    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['video_show:read', 'video_show:write', 'youtube_video:read'])]
    private ?string $thumbnailUrl = null;

    /**
     * Brand color for this show (hex format, e.g., #FF0000)
     */
    #[ORM\Column(length: 7, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Color must be in hex format (#RRGGBB)')]
    #[Groups(['video_show:read', 'video_show:write', 'youtube_video:read'])]
    private ?string $color = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['video_show:read', 'video_show:write'])]
    private bool $isActive = true;

    /**
     * Display order (lower = first)
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['video_show:read', 'video_show:write'])]
    private int $position = 0;

    /**
     * @var Collection<int, YouTubeVideo>
     */
    #[ORM\OneToMany(targetEntity: YouTubeVideo::class, mappedBy: 'videoShow', cascade: ['persist'], orphanRemoval: false)]
    #[ORM\OrderBy(['publishedAt' => 'DESC'])]
    #[Groups(['video_show:detail'])]
    #[MaxDepth(1)]
    private Collection $videos;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['video_show:read'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['video_show:read'])]
    private DateTimeImmutable $updatedAt;

    /**
     * Locale used for translations
     */
    #[Gedmo\Locale]
    private ?string $locale = null;

    public function __construct()
    {
        $this->videos = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
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

    public function getYoutubePlaylistId(): ?string
    {
        return $this->youtubePlaylistId;
    }

    public function setYoutubePlaylistId(?string $youtubePlaylistId): self
    {
        $this->youtubePlaylistId = $youtubePlaylistId;
        return $this;
    }

    public function getYoutubeChannelId(): ?string
    {
        return $this->youtubeChannelId;
    }

    public function setYoutubeChannelId(?string $youtubeChannelId): self
    {
        $this->youtubeChannelId = $youtubeChannelId;
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

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    /**
     * @return Collection<int, YouTubeVideo>
     */
    public function getVideos(): Collection
    {
        return $this->videos;
    }

    public function addVideo(YouTubeVideo $video): self
    {
        if (!$this->videos->contains($video)) {
            $this->videos->add($video);
            $video->setVideoShow($this);
        }
        return $this;
    }

    public function removeVideo(YouTubeVideo $video): self
    {
        if ($this->videos->removeElement($video)) {
            if ($video->getVideoShow() === $this) {
                $video->setVideoShow(null);
            }
        }
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
     * Get count of videos in this show
     */
    #[Groups(['video_show:read', 'video_show:list'])]
    public function getVideosCount(): int
    {
        return $this->videos->count();
    }
}
