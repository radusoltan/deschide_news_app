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
use App\Enum\ArchiveReason;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\State\ArchivedArticleProvider;
use App\State\ArticleProcessor;
use App\State\ArticleProvider;
use App\Validator\ReservedSlug;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[UniqueEntity('slug', message: 'This slug is already in use. Please choose a different slug.')]
#[ORM\Table(name: 'articles')]
#[ORM\HasLifecycleCallbacks]
// Single column indexes
#[ORM\Index(name: 'idx_article_status', columns: ['status'])]
#[ORM\Index(name: 'idx_article_published_at', columns: ['published_at'])]
#[ORM\Index(name: 'idx_article_publish_at', columns: ['publish_at'])]
#[ORM\Index(name: 'idx_article_featured', columns: ['is_featured'])]
#[ORM\Index(name: 'idx_article_archived_at', columns: ['archived_at'])]
// Composite indexes for common queries
#[ORM\Index(name: 'idx_article_status_category', columns: ['status', 'category_id'])]
#[ORM\Index(name: 'idx_article_status_published', columns: ['status', 'published_at'])]
#[ORM\Index(name: 'idx_article_featured_published', columns: ['is_featured', 'published_at'])]
#[ORM\Index(name: 'idx_article_category_status_published', columns: ['category_id', 'status', 'published_at'])]
#[ORM\Index(name: 'idx_article_status_archived', columns: ['status', 'archived_at'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/articles/{id}',
            normalizationContext: ['groups' => ['article:read', 'article:detail', 'category:read', 'author:read'], 'enable_max_depth' => true],
            cacheHeaders: [
                'max_age' => 3600,           // 1 hour client cache
                'shared_max_age' => 7200,    // 2 hours proxy/CDN cache
                'vary' => ['Accept', 'Accept-Language'],
            ]
        ),
        new GetCollection(
            uriTemplate: '/articles',
            normalizationContext: ['groups' => ['article:read', 'article:list', 'category:read', 'author:read'], 'enable_max_depth' => true],
            paginationItemsPerPage: 20,
            paginationPartial: true,
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true,
            cacheHeaders: [
                'max_age' => 1800,           // 30 minutes client cache (lists change more often)
                'shared_max_age' => 3600,    // 1 hour proxy/CDN cache
                'vary' => ['Accept', 'Accept-Language'],
            ]
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
        ),
    ],
    provider: ArticleProvider::class,
    processor: ArticleProcessor::class
)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/archived_articles/{id}',
            normalizationContext: ['groups' => ['article:read', 'article:detail', 'category:read', 'author:read'], 'enable_max_depth' => true],
            cacheHeaders: [
                'max_age' => 7200,           // 2 hours client cache (archives change rarely)
                'shared_max_age' => 14400,   // 4 hours proxy/CDN cache
                'vary' => ['Accept', 'Accept-Language'],
            ]
        ),
        new GetCollection(
            uriTemplate: '/archived_articles',
            normalizationContext: ['groups' => ['article:read', 'article:list', 'category:read', 'author:read'], 'enable_max_depth' => true],
            paginationItemsPerPage: 20,
            paginationPartial: true,
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true,
            cacheHeaders: [
                'max_age' => 3600,           // 1 hour client cache
                'shared_max_age' => 7200,    // 2 hours proxy/CDN cache
                'vary' => ['Accept', 'Accept-Language'],
            ]
        ),
    ],
    provider: ArchivedArticleProvider::class
)]
#[ApiFilter(SearchFilter::class, properties: [
    // NOTE: 'category' and 'category.id' filters are handled by ArticleProvider
    // to avoid conflicts with nested array parameter parsing
    'title' => 'partial',
    'slug' => 'exact',
    'tags' => 'exact',
    'tags.id' => 'exact',
    'tags.slug' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'publishedAt' => 'DESC',
    'createdAt' => 'DESC',
    'viewCount' => 'DESC',
    'title' => 'ASC',
])]
#[ApiFilter(BooleanFilter::class, properties: ['isFeatured'])]
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
    #[Gedmo\Slug(fields: ['title'], unique: true, updatable: true)]
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[ReservedSlug]
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
    #[Groups(['article:read', 'article:detail', 'article:list'])]
    #[MaxDepth(2)]
    private Collection $articleImages;

    #[ORM\ManyToMany(targetEntity: self::class)]
    #[ORM\JoinTable(name: 'related_articles')]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'related_article_id', referencedColumnName: 'id')]
    #[Assert\Count(max: 20, maxMessage: 'An article cannot have more than {{ limit }} related articles.')]
    #[Groups(['article:detail', 'article:write'])]
    private Collection $relatedArticles;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
    #[ORM\JoinTable(name: 'article_tag')]
    #[Groups(['article:read', 'article:write'])]
    #[MaxDepth(2)]
    private Collection $tags;

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
    private ?DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['article:read'])]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['article:read'])]
    private ?DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Assert\GreaterThan('now', message: 'Publish date must be in the future.')]
    #[Groups(['article:read', 'article:write'])]
    private ?DateTimeImmutable $publishAt = null;

    // Archive fields
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['article:read'])]
    private ?DateTimeImmutable $archivedAt = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true, enumType: ArchiveReason::class)]
    #[Groups(['article:read', 'article:write'])]
    private ?ArchiveReason $archiveReason = null;

    // For translations
    #[Gedmo\Locale]
    #[Groups(['article:read'])]
    private ?string $locale = null;

    public function __construct()
    {
        $this->authors = new ArrayCollection();
        $this->articleImages = new ArrayCollection();
        $this->relatedArticles = new ArrayCollection();
        $this->tags = new ArrayCollection();
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

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
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

    public function getPublishAt(): ?DateTimeImmutable
    {
        return $this->publishAt;
    }

    public function setPublishAt(?DateTimeImmutable $publishAt): self
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
     * Lifecycle callback to set publishedAt when status becomes published.
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updatePublishedAt(): void
    {
        if ($this->status === ArticleStatus::PUBLISHED && $this->publishedAt === null) {
            $this->publishedAt = new DateTimeImmutable();
        }
    }

    /**
     * Computed property - reading time.
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

    /**
     * @return Collection<int, ArticleImage>
     */
    public function getArticleImages(): Collection
    {
        return $this->articleImages;
    }

    public function addArticleImage(ArticleImage $articleImage): self
    {
        if (!$this->articleImages->contains($articleImage)) {
            $this->articleImages->add($articleImage);
            $articleImage->setArticle($this);
        }

        return $this;
    }

    public function removeArticleImage(ArticleImage $articleImage): self
    {
        if ($this->articleImages->removeElement($articleImage)) {
            // set the owning side to null (unless already changed)
            if ($articleImage->getArticle() === $this) {
                $articleImage->setArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function getArchivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?DateTimeImmutable $archivedAt): self
    {
        $this->archivedAt = $archivedAt;

        return $this;
    }

    public function getArchiveReason(): ?ArchiveReason
    {
        return $this->archiveReason;
    }

    public function setArchiveReason(?ArchiveReason $archiveReason): self
    {
        $this->archiveReason = $archiveReason;

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->status === ArticleStatus::ARCHIVED;
    }

    public function archive(ArchiveReason $reason): self
    {
        $this->status = ArticleStatus::ARCHIVED;
        $this->archivedAt = new DateTimeImmutable();
        $this->archiveReason = $reason;

        return $this;
    }

    public function unarchive(): self
    {
        $this->status = ArticleStatus::PUBLISHED;
        $this->archivedAt = null;
        $this->archiveReason = null;

        return $this;
    }
}
