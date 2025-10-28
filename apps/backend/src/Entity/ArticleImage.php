<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\ArticleImageRepository;
use App\State\ArticleImageProcessor;
use App\State\ArticleImageProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArticleImageRepository::class)]
#[ORM\Table(name: 'article_image')]
#[ORM\UniqueConstraint(name: 'idx_article_image_unique', columns: ['article_id', 'image_id'])]
#[ORM\Index(name: 'idx_article_image_article', columns: ['article_id'])]
#[ORM\Index(name: 'idx_article_image_image', columns: ['image_id'])]
#[ORM\Index(name: 'idx_article_image_position', columns: ['article_id', 'position'])]
#[ORM\Index(name: 'idx_article_image_featured', columns: ['article_id', 'is_featured'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/article_images/{id}',
            normalizationContext: ['groups' => ['article_image:read']]
        ),
        new GetCollection(
            uriTemplate: '/article_images',
            normalizationContext: ['groups' => ['article_image:read']],
            paginationItemsPerPage: 50
        ),
        new Post(
            uriTemplate: '/article_images',
            denormalizationContext: ['groups' => ['article_image:write', 'article_image:create']]
        ),
        new Put(
            uriTemplate: '/article_images/{id}',
            denormalizationContext: ['groups' => ['article_image:write']]
        ),
        new Delete(
            uriTemplate: '/article_images/{id}'
        )
    ],
    provider: ArticleImageProvider::class,
    processor: ArticleImageProcessor::class
)]
class ArticleImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['article_image:read'])]
    private ?int $id = null;

    // Relationships
    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'articleImages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['article_image:read', 'article_image:create'])]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: Image::class, inversedBy: 'articleImages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['article_image:read', 'article_image:create'])]
    private ?Image $image = null;

    // Pivot Metadata
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Assert\Range(min: 0)]
    #[Groups(['article_image:read', 'article_image:write'])]
    private int $position = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['article_image:read', 'article_image:write'])]
    private bool $isFeatured = false;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['article_image:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct(?Article $article = null, ?Image $image = null, int $position = 0, bool $isFeatured = false)
    {
        $this->article = $article;
        $this->image = $image;
        $this->position = $position;
        $this->isFeatured = $isFeatured;
    }

    // Getters and setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): self
    {
        $this->article = $article;
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function getIsFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): self
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
