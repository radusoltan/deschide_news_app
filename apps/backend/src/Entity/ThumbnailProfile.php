<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Enum\ThumbnailCategory;
use App\Enum\ThumbnailMode;
use App\Repository\ThumbnailProfileRepository;
use App\State\ThumbnailProfileProcessor;
use App\State\ThumbnailProfileProvider;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ThumbnailProfileRepository::class)]
#[ORM\Cache(usage: 'READ_ONLY', region: 'long_lived')]  // L2 cache: profiles rarely change
#[ORM\Table(name: 'thumbnail_profiles')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('name')]
#[ORM\Index(name: 'idx_profile_name', columns: ['name'])]
#[ORM\Index(name: 'idx_profile_is_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_profile_category', columns: ['category'])]
#[ORM\UniqueConstraint(name: 'idx_profile_dimensions', columns: ['width', 'height', 'mode'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/thumbnail_profiles/{id}',
            normalizationContext: ['groups' => ['thumbnail_profile:read', 'thumbnail_profile:detail']]
        ),
        new GetCollection(
            uriTemplate: '/thumbnail_profiles',
            normalizationContext: ['groups' => ['thumbnail_profile:read', 'thumbnail_profile:list']],
            paginationItemsPerPage: 50
        ),
        new Post(
            uriTemplate: '/thumbnail_profiles',
            denormalizationContext: ['groups' => ['thumbnail_profile:write', 'thumbnail_profile:create']]
        ),
        new Put(
            uriTemplate: '/thumbnail_profiles/{id}',
            denormalizationContext: ['groups' => ['thumbnail_profile:write']]
        ),
        new Delete(
            uriTemplate: '/thumbnail_profiles/{id}'
        ),
    ],
    provider: ThumbnailProfileProvider::class,
    processor: ThumbnailProfileProcessor::class
)]
class ThumbnailProfile implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['thumbnail_profile:read'])]
    private ?int $id = null;

    // Configuration - Non-translatable
    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Assert\Regex(pattern: '/^[a-z0-9_]+$/')]
    #[Groups(['thumbnail_profile:read', 'thumbnail_profile:create', 'article:read'])]
    private ?string $name = null;

    // Translatable fields
    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Groups(['thumbnail_profile:read', 'thumbnail_profile:write'])]
    private ?string $displayName = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['thumbnail_profile:read', 'thumbnail_profile:write'])]
    private ?string $description = null;

    // Dimensions - Non-translatable
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(min: 1, max: 8000)]
    #[Groups(['thumbnail_profile:read', 'thumbnail_profile:write'])]
    private ?int $width = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(min: 1, max: 8000)]
    #[Groups(['thumbnail_profile:read', 'thumbnail_profile:write'])]
    private ?int $height = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    #[Assert\Regex(pattern: '/^\d+:\d+$/')]
    #[Groups(['thumbnail_profile:read', 'thumbnail_profile:write'])]
    private ?string $aspectRatio = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ThumbnailMode::class)]
    #[Groups(['thumbnail_profile:read', 'thumbnail_profile:write'])]
    private ThumbnailMode $mode = ThumbnailMode::CROP;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(min: 1, max: 100)]
    #[Groups(['thumbnail_profile:read', 'thumbnail_profile:write'])]
    private int $quality = 85;

    // Metadata
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['thumbnail_profile:read', 'thumbnail_profile:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ThumbnailCategory::class)]
    #[Groups(['thumbnail_profile:read', 'thumbnail_profile:write'])]
    private ThumbnailCategory $category = ThumbnailCategory::GENERAL;

    // Relationships
    #[ORM\OneToMany(targetEntity: Thumbnail::class, mappedBy: 'profile')]
    private Collection $thumbnails;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['thumbnail_profile:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['thumbnail_profile:read'])]
    private ?DateTimeImmutable $updatedAt = null;

    // For translations
    #[Gedmo\Locale]
    #[Groups(['thumbnail_profile:read'])]
    private ?string $locale = null;

    public function __construct()
    {
        $this->thumbnails = new ArrayCollection();
    }

    // Getters and setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName;

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

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getAspectRatio(): ?string
    {
        return $this->aspectRatio;
    }

    public function setAspectRatio(?string $aspectRatio): self
    {
        $this->aspectRatio = $aspectRatio;

        return $this;
    }

    public function getMode(): ThumbnailMode
    {
        return $this->mode;
    }

    public function setMode(ThumbnailMode $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function getQuality(): int
    {
        return $this->quality;
    }

    public function setQuality(int $quality): self
    {
        $this->quality = $quality;

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

    public function getCategory(): ThumbnailCategory
    {
        return $this->category;
    }

    public function setCategory(ThumbnailCategory $category): self
    {
        $this->category = $category;

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

    public function setTranslatableLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * @return Collection<int, Thumbnail>
     */
    public function getThumbnails(): Collection
    {
        return $this->thumbnails;
    }

    /**
     * Computed property - calculated aspect ratio.
     */
    #[Groups(['thumbnail_profile:read'])]
    public function getCalculatedAspectRatio(): string
    {
        if ($this->aspectRatio) {
            return $this->aspectRatio;
        }

        if (!$this->width || !$this->height) {
            return '0:0';
        }

        $gcd = $this->gcd($this->width, $this->height);

        return ($this->width / $gcd) . ':' . ($this->height / $gcd);
    }

    /**
     * Computed property - dimensions label.
     */
    #[Groups(['thumbnail_profile:read'])]
    public function getDimensionsLabel(): string
    {
        return \sprintf(
            '%dx%d (%s)',
            $this->width ?? 0,
            $this->height ?? 0,
            $this->getCalculatedAspectRatio()
        );
    }

    /**
     * Greatest Common Divisor (for aspect ratio calculation).
     */
    private function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->gcd($b, $a % $b);
    }
}
