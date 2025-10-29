<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\State\ArticleProcessor;
use App\State\ArticleProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'articles')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_article_status', columns: ['status'])]
#[ORM\Index(name: 'idx_article_published_at', columns: ['published_at'])]
#[ORM\Index(name: 'idx_article_publish_at', columns: ['publish_at'])]
#[ORM\Index(name: 'idx_article_featured', columns: ['is_featured'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/articles/{id}',
            normalizationContext: ['groups' => ['article:read', 'article:detail', 'category:read', 'author:read']]
        ),
        new GetCollection(
            uriTemplate: '/articles',
            normalizationContext: ['groups' => ['article:read', 'article:list', 'category:read', 'author:read']],
            paginationItemsPerPage: 20
        ),
        new Post(
            uriTemplate: '/articles',
            denormalizationContext: ['groups' => ['article:write']]
        ),
        new Put(
            uriTemplate: '/articles/{id}',
            denormalizationContext: ['groups' => ['article:write']]
        ),
        new Delete(
            uriTemplate: '/articles/{id}'
        )
    ],
    provider: ArticleProvider::class,
    processor: ArticleProcessor::class
)]
class Article implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['article:read'])]
    private ?int $id = null;

    // Translatable fields
    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $title = null;

    #[Gedmo\Translatable]
    #[Gedmo\Slug(fields: ['title'])]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['article:read'])]
    private ?string $slug = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 3000)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $lead = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['article:detail', 'article:write'])] // Content only in detail view
    private ?string $content = null;

    // Relationships
    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['article:read', 'article:write'])]
    private ?Category $category = null;

    #[ORM\ManyToMany(targetEntity: Author::class, inversedBy: 'articles')]
    #[ORM\JoinTable(name: 'article_author')]
    #[Groups(['article:read', 'article:write'])]
    private Collection $authors;

    #[ORM\OneToMany(targetEntity: ArticleImage::class, mappedBy: 'article', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $articleImages;

    #[ORM\ManyToMany(targetEntity: self::class)]
    #[ORM\JoinTable(name: 'related_articles')]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'related_article_id', referencedColumnName: 'id')]
    #[Assert\Count(max: 20, maxMessage: 'An article cannot have more than {{ limit }} related articles.')]
    #[Groups(['article:detail', 'article:write'])]
    private Collection $relatedArticles;

    // Non-translatable fields
    #[ORM\Column(type: Types::STRING, length: 20, enumType: ArticleStatus::class)]
    #[Groups(['article:read', 'article:write'])]
    private ArticleStatus $status = ArticleStatus::NEW;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, enumType: ArticleBadge::class)]
    #[Groups(['article:read', 'article:write'])]
    private ?ArticleBadge $badge = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['article:read', 'article:write'])]
    private bool $isFeatured = false;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['article:read'])]
    private int $viewCount = 0;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['article:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['article:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['article:read'])]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Assert\GreaterThan('now', message: 'Publish date must be in the future.')]
    #[Groups(['article:read', 'article:write'])]
    private ?\DateTimeImmutable $publishAt = null;

    // For translations
    #[Gedmo\Locale]
    #[Groups(['article:read'])]
    private ?string $locale = null;

    public function __construct()
    {
        $this->authors = new ArrayCollection();
        $this->articleImages = new ArrayCollection();
        $this->relatedArticles = new ArrayCollection();
    }

    // Getters and setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getLead(): ?string
    {
        return $this->lead;
    }

    public function setLead(?string $lead): self
    {
        $this->lead = $lead;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getStatus(): ArticleStatus
    {
        return $this->status;
    }

    public function setStatus(ArticleStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getBadge(): ?ArticleBadge
    {
        return $this->badge;
    }

    public function setBadge(?ArticleBadge $badge): self
    {
        $this->badge = $badge;
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

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function setViewCount(int $viewCount): self
    {
        $this->viewCount = $viewCount;
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

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getPublishAt(): ?\DateTimeImmutable
    {
        return $this->publishAt;
    }

    public function setPublishAt(?\DateTimeImmutable $publishAt): self
    {
        $this->publishAt = $publishAt;
        return $this;
    }

    public function setTranslatableLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return Collection<int, Author>
     */
    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function addAuthor(Author $author): self
    {
        if (!$this->authors->contains($author)) {
            $this->authors->add($author);
        }

        return $this;
    }

    public function removeAuthor(Author $author): self
    {
        $this->authors->removeElement($author);
        return $this;
    }

    /**
     * Lifecycle callback to set publishedAt when status becomes published
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updatePublishedAt(): void
    {
        if ($this->status === ArticleStatus::PUBLISHED && $this->publishedAt === null) {
            $this->publishedAt = new \DateTimeImmutable();
        }
    }

    /**
     * Computed property - reading time
     */
    public function getReadingTime(): int
    {
        if (!$this->content) {
            return 0;
        }
        $wordCount = str_word_count(strip_tags($this->content));
        return (int) ceil($wordCount / 200);
    }

    /**
     * @return Collection<int, Article>
     */
    public function getRelatedArticles(): Collection
    {
        return $this->relatedArticles;
    }

    public function addRelatedArticle(Article $article): self
    {
        if (!$this->relatedArticles->contains($article)) {
            $this->relatedArticles->add($article);
        }

        return $this;
    }

    public function removeRelatedArticle(Article $article): self
    {
        $this->relatedArticles->removeElement($article);
        return $this;
    }
}
