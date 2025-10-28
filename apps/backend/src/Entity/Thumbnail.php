<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\ThumbnailRepository;
use App\State\ThumbnailProcessor;
use App\State\ThumbnailProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ThumbnailRepository::class)]
#[ORM\Table(name: 'thumbnails')]
#[ORM\UniqueConstraint(name: 'idx_image_profile_unique', columns: ['image_id', 'profile_id'])]
#[ORM\Index(name: 'idx_thumbnail_image', columns: ['image_id'])]
#[ORM\Index(name: 'idx_thumbnail_profile', columns: ['profile_id'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/thumbnails/{id}',
            normalizationContext: ['groups' => ['thumbnail:read', 'thumbnail:detail']]
        ),
        new GetCollection(
            uriTemplate: '/thumbnails',
            normalizationContext: ['groups' => ['thumbnail:read', 'thumbnail:list']],
            paginationItemsPerPage: 50
        ),
        new Post(
            uriTemplate: '/thumbnails',
            denormalizationContext: ['groups' => ['thumbnail:write', 'thumbnail:create']],
            description: 'Create new thumbnail'
        ),
        new Post(
            uriTemplate: '/thumbnails/{id}/crop',
            denormalizationContext: ['groups' => ['thumbnail:crop']],
            normalizationContext: ['groups' => ['thumbnail:read']],
            description: 'Apply custom crop to thumbnail from react-cropper'
        ),
        new Put(
            uriTemplate: '/thumbnails/{id}',
            denormalizationContext: ['groups' => ['thumbnail:write']],
            description: 'Update thumbnail metadata'
        ),
        new Delete(
            uriTemplate: '/thumbnails/{id}',
            description: 'Delete thumbnail'
        )
    ],
    provider: ThumbnailProvider::class,
    processor: ThumbnailProcessor::class
)]
class Thumbnail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['thumbnail:read'])]
    private ?int $id = null;

    // File Information
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['thumbnail:read', 'thumbnail:create'])]
    private ?string $filename = null;

    #[ORM\Column(type: Types::STRING, length: 500)]
    #[Groups(['thumbnail:read', 'thumbnail:create'])]
    private ?string $path = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(min: 1)]
    #[Groups(['thumbnail:read', 'thumbnail:write'])]
    private ?int $width = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(min: 1)]
    #[Groups(['thumbnail:read', 'thumbnail:write'])]
    private ?int $height = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(min: 1)]
    #[Groups(['thumbnail:read', 'thumbnail:write'])]
    private ?int $size = null;

    // Crop Data (stores custom crop coordinates from react-cropper)
    // Format: {"x": 10, "y": 20, "width": 300, "height": 200, "unit": "px"} or {"p": {"x": 5.5, "y": 10.2, "width": 80.5, "height": 70.3}, "unit": "percent"}
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['thumbnail:read', 'thumbnail:write', 'thumbnail:crop'])]
    private ?array $cropData = null;

    // Relationships
    #[ORM\ManyToOne(targetEntity: Image::class, inversedBy: 'thumbnails')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['thumbnail:read', 'thumbnail:create'])]
    #[MaxDepth(1)]
    private ?Image $image = null;

    #[ORM\ManyToOne(targetEntity: ThumbnailProfile::class, inversedBy: 'thumbnails')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['thumbnail:read', 'thumbnail:create'])]
    #[MaxDepth(1)]
    private ?ThumbnailProfile $profile = null;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['thumbnail:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    // Getters and setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;
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

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(?Image $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getProfile(): ?ThumbnailProfile
    {
        return $this->profile;
    }

    public function setProfile(?ThumbnailProfile $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCropData(): ?array
    {
        return $this->cropData;
    }

    public function setCropData(?array $cropData): self
    {
        $this->cropData = $cropData;
        return $this;
    }

    /**
     * Computed property - URL
     */
    #[Groups(['thumbnail:read'])]
    public function getUrl(): string
    {
        return '/media/' . $this->path;
    }

    /**
     * Computed property - formatted size
     */
    #[Groups(['thumbnail:read'])]
    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB'];
        $size = $this->size ?? 0;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Computed property - aspect ratio
     */
    #[Groups(['thumbnail:read'])]
    public function getAspectRatio(): float
    {
        return $this->height > 0 ? round($this->width / $this->height, 2) : 0;
    }
}
