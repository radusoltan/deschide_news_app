<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\ImageRepository;
use App\State\ImageProcessor;
use App\State\ImageProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ImageRepository::class)]
#[ORM\Table(name: 'images')]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
#[UniqueEntity('filename')]
#[ORM\Index(name: 'idx_image_filename', columns: ['filename'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/images/{id}',
            normalizationContext: [
                'groups' => ['image:read', 'image:detail', 'thumbnail:read', 'thumbnail_profile:read'],
                'enable_max_depth' => true
            ]
        ),
        new GetCollection(
            uriTemplate: '/images',
            normalizationContext: ['groups' => ['image:read', 'image:list']],
            paginationItemsPerPage: 50
        ),
        new Post(
            uriTemplate: '/images',
            inputFormats: ['multipart' => ['multipart/form-data']],
            denormalizationContext: ['groups' => ['image:write', 'image:create']],
            validationContext: ['groups' => ['Default', 'image:create']],
            description: 'Upload a new image file with optional metadata (alt, caption, description, imageAuthor)'
        ),
        new Put(
            uriTemplate: '/images/{id}',
            denormalizationContext: ['groups' => ['image:write']],
            description: 'Update translatable fields (alt, caption, description) and imageAuthor. File cannot be changed after upload.'
        ),
        new Delete(
            uriTemplate: '/images/{id}'
        ),
        new Post(
            uriTemplate: '/images/{id}/thumbnails/crop',
            denormalizationContext: ['groups' => ['image:crop']],
            normalizationContext: ['groups' => ['thumbnail:read']],
            description: 'Apply custom crop coordinates to generate/regenerate a thumbnail for a specific profile and format'
        ),
        new Post(
            uriTemplate: '/images/{id}/thumbnails/reset-crop',
            denormalizationContext: ['groups' => ['image:crop:reset']],
            normalizationContext: ['groups' => ['thumbnail:read']],
            description: 'Reset crop to default (regenerate thumbnail without custom crop data)'
        )
    ],
    provider: ImageProvider::class,
    processor: ImageProcessor::class
)]
class Image implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['image:read'])]
    private ?int $id = null;

    // Upload file (not persisted)
    #[Vich\UploadableField(mapping: 'images', fileNameProperty: 'filename', size: 'size', mimeType: 'mimeType', originalName: 'originalFilename')]
    #[Assert\NotNull(groups: ['image:create'], message: 'Please upload an image file.')]
    #[Assert\File(
        maxSize: '10M',
        mimeTypes: ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'],
        mimeTypesMessage: 'Please upload a valid image file (JPEG, PNG, GIF, or WebP).',
        groups: ['image:create']
    )]
    #[Groups(['image:create'])]
    private ?File $file = null;

    // File Information
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[Groups(['image:read', 'article:read'])]
    private ?string $filename = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['image:read', 'article:read'])]
    private ?string $originalFilename = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    #[Groups(['image:read', 'article:read'])]
    private ?string $path = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['image:read', 'article:read'])]
    private ?string $mimeType = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['image:read', 'article:read'])]
    private ?int $size = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['image:read', 'article:read'])]
    private ?int $width = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['image:read', 'article:read'])]
    private ?int $height = null;

    // Translatable fields
    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['image:read', 'image:write', 'article:read'])]
    private ?string $alt = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 500)]
    #[Groups(['image:read', 'image:write'])]
    private ?string $caption = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    #[Groups(['image:read', 'image:write'])]
    private ?string $description = null;

    // Metadata
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['image:read', 'image:write'])]
    private ?string $imageAuthor = null;

    // Relationships
    #[ORM\OneToMany(targetEntity: ArticleImage::class, mappedBy: 'image', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $articleImages;

    #[ORM\OneToMany(targetEntity: Thumbnail::class, mappedBy: 'image', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['image:read', 'article:read'])]
    #[MaxDepth(1)]
    private Collection $thumbnails;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['image:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['image:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    // For translations
    #[Gedmo\Locale]
    #[Groups(['image:read'])]
    private ?string $locale = null;

    public function __construct()
    {
        $this->articleImages = new ArrayCollection();
        $this->thumbnails = new ArrayCollection();
    }

    // Getters and setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): self
    {
        $this->file = $file;

        // Update updatedAt to trigger Gedmo update and extract dimensions
        if ($file) {
            $this->updatedAt = new \DateTimeImmutable();

            // Extract image dimensions if it's an image file
            if (str_starts_with($file->getMimeType() ?? '', 'image/')) {
                $imageSize = @getimagesize($file->getPathname());
                if ($imageSize !== false) {
                    $this->width = $imageSize[0];
                    $this->height = $imageSize[1];
                }
            }
        }

        return $this;
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

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;
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

    /**
     * Lifecycle callback to set path after file upload
     */
    #[ORM\PostLoad]
    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    public function updatePath(): void
    {
        if ($this->filename && !$this->path) {
            $this->path = 'images/' . $this->filename;
        }
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;
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

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;
        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;
        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): self
    {
        $this->alt = $alt;
        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): self
    {
        $this->caption = $caption;
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

    public function getImageAuthor(): ?string
    {
        return $this->imageAuthor;
    }

    public function setImageAuthor(?string $imageAuthor): self
    {
        $this->imageAuthor = $imageAuthor;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
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
     * @return Collection<int, ArticleImage>
     */
    public function getArticleImages(): Collection
    {
        return $this->articleImages;
    }

    /**
     * @return Collection<int, Thumbnail>
     */
    public function getThumbnails(): Collection
    {
        return $this->thumbnails;
    }

    /**
     * Computed property - aspect ratio
     */
    #[Groups(['image:read'])]
    public function getAspectRatio(): float
    {
        return $this->height > 0 ? round($this->width / $this->height, 2) : 0;
    }

    /**
     * Computed property - formatted size
     */
    #[Groups(['image:read'])]
    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size ?? 0;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    // Temporary properties for crop operations (not persisted)
    #[Groups(['image:crop'])]
    private ?string $profile = null;

    #[Groups(['image:crop'])]
    private ?string $format = null;

    #[Groups(['image:crop'])]
    private ?array $cropData = null;

    public function getProfile(): ?string
    {
        return $this->profile;
    }

    public function setProfile(?string $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): self
    {
        $this->format = $format;
        return $this;
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
}
