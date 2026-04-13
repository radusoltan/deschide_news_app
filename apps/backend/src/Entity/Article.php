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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Enum\ArchiveReason;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\State\ArchivedArticleProvider;
use App\State\Article\ArticleCreateProcessor;
use App\State\Article\ArticleDeleteProcessor;
use App\State\Article\ArticleUpdateProcessor;
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
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'short_lived')]
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
#[ORM\Index(name: 'idx_article_content_hash', columns: ['content_hash'])]
#[ORM\Index(name: 'idx_article_ingested_at', columns: ['ingested_at'])]
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
            denormalizationContext: ['groups' => ['article:write']],
            processor: ArticleCreateProcessor::class
        ),
        new Put(
            uriTemplate: '/articles/{id}',
            denormalizationContext: ['groups' => ['article:write']],
            processor: ArticleUpdateProcessor::class
        ),
        new Patch(
            uriTemplate: '/articles/{id}',
            denormalizationContext: ['groups' => ['article:write']],
            processor: ArticleUpdateProcessor::class
        ),
        new Delete(
            uriTemplate: '/articles/{id}',
            processor: ArticleDeleteProcessor::class
        ),
    ],
    provider: ArticleProvider::class
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
    'authors.type' => 'exact',
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

    // SEO fields
    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::STRING, length: 60, nullable: true)]
    #[Assert\Length(max: 60, maxMessage: 'Meta title nu poate depăși {{ limit }} caractere.')]
    #[Groups(['article:read', 'article:write'])]
    private ?string $metaTitle = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::STRING, length: 160, nullable: true)]
    #[Assert\Length(max: 160, maxMessage: 'Meta description nu poate depăși {{ limit }} caractere.')]
    #[Groups(['article:read', 'article:write'])]
    private ?string $metaDescription = null;

    // Relationships
    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
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
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'related_article_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Assert\Count(max: 20, maxMessage: 'An article cannot have more than {{ limit }} related articles.')]
    #[Groups(['article:detail', 'article:write'])]
    private Collection $relatedArticles;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
    #[ORM\JoinTable(name: 'article_tag')]
    #[Groups(['article:read', 'article:write'])]
    #[MaxDepth(2)]
    private Collection $tags;

    #[ORM\ManyToMany(targetEntity: Topic::class, mappedBy: 'articles')]
    #[Groups(['article:read', 'article:write'])]
    #[MaxDepth(2)]
    private Collection $topics;

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

    // Short link webcode (auto-generated when article is published)
    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, unique: true)]
    #[Groups(['article:read'])]
    private ?string $webcode = null;

    // Source email ID (set when article is auto-created by email-press-redactor agent)
    #[ORM\Column(length: 64, nullable: true, unique: true)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $sourceEmail = null;

    // Content hash for deduplication (SHA-256 of normalized body)
    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['article:read'])]
    private ?string $contentHash = null;

    // Translation workflow fields
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['article:read', 'article:write'])]
    private bool $requestTranslation = false;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['article:read'])]
    private ?string $translationStatus = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['article:read'])]
    private ?\DateTimeImmutable $translatedAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['article:read'])]
    private ?string $translatedBy = null;

    // Per-locale publishing: locales where article is visible on frontend
    /** @var string[] */
    #[ORM\Column(type: 'text_array', options: ['default' => '{ro}'])]
    #[Groups(['article:read', 'article:list'])]
    private array $publishedLocales = ['ro'];

    // AI-generated internal summary (TL;DR for editorial workflow)
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['article:read', 'article:detail'])]
    private ?string $internalSummary = null;

    // Timestamp when AI ingestion pipeline completed (entity extraction, MOC update, etc.)
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['article:read'])]
    private ?DateTimeImmutable $ingestedAt = null;

    // AI generation metadata
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['article:read'])]
    private bool $aiGenerated = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['article:read'])]
    private ?int $sourceClusterId = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups(['article:read'])]
    private ?float $aiConfidenceScore = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['article:read'])]
    private ?int $aiSourceCount = null;

    /**
     * Non-persisted field populated by ArticleProvider / SlugController.
     * Contains slug translations for all locales: {"ro": "slug-ro", "en": "slug-en", "ru": "slug-ru"}
     *
     * @var array<string, string>|null
     */
    #[Groups(['article:read'])]
    private ?array $translatedSlugs = null;

    public function __construct()
    {
        $this->authors = new ArrayCollection();
        $this->articleImages = new ArrayCollection();
        $this->relatedArticles = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->topics = new ArrayCollection();
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

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): self
    {
        $this->metaTitle = $metaTitle;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): self
    {
        $this->metaDescription = $metaDescription;

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

    public function getIsFeatured(): bool
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

    public function getWebcode(): ?string
    {
        return $this->webcode;
    }

    public function setWebcode(?string $webcode): self
    {
        $this->webcode = $webcode;

        return $this;
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
     * Only validate publishAt is in the future when the article is being submitted for scheduling.
     */
    #[Assert\Callback]
    public function validatePublishAt(ExecutionContextInterface $context): void
    {
        if ($this->publishAt !== null && $this->status === ArticleStatus::SUBMITTED) {
            if ($this->publishAt <= new DateTimeImmutable()) {
                $context->buildViolation('Publish date must be in the future.')
                    ->atPath('publishAt')
                    ->addViolation();
            }
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

    /**
     * @return Collection<int, Topic>
     */
    public function getTopics(): Collection
    {
        return $this->topics;
    }

    public function addTopic(Topic $topic): self
    {
        if (!$this->topics->contains($topic)) {
            $this->topics->add($topic);
            $topic->addArticle($this);
        }

        return $this;
    }

    public function removeTopic(Topic $topic): self
    {
        if ($this->topics->removeElement($topic)) {
            $topic->removeArticle($this);
        }

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

    public function getSourceEmail(): ?string
    {
        return $this->sourceEmail;
    }

    public function setSourceEmail(?string $sourceEmail): static
    {
        $this->sourceEmail = $sourceEmail;

        return $this;
    }

    public function getContentHash(): ?string
    {
        return $this->contentHash;
    }

    public function setContentHash(?string $contentHash): static
    {
        $this->contentHash = $contentHash;

        return $this;
    }

    public function isRequestTranslation(): bool
    {
        return $this->requestTranslation;
    }

    public function setRequestTranslation(bool $requestTranslation): static
    {
        $this->requestTranslation = $requestTranslation;

        return $this;
    }

    public function getTranslationStatus(): ?string
    {
        return $this->translationStatus;
    }

    public function setTranslationStatus(?string $translationStatus): static
    {
        $this->translationStatus = $translationStatus;

        return $this;
    }

    public function getTranslatedAt(): ?\DateTimeImmutable
    {
        return $this->translatedAt;
    }

    public function setTranslatedAt(?\DateTimeImmutable $translatedAt): static
    {
        $this->translatedAt = $translatedAt;

        return $this;
    }

    public function getTranslatedBy(): ?string
    {
        return $this->translatedBy;
    }

    public function setTranslatedBy(?string $translatedBy): static
    {
        $this->translatedBy = $translatedBy;

        return $this;
    }

    public function getInternalSummary(): ?string
    {
        return $this->internalSummary;
    }

    public function setInternalSummary(?string $internalSummary): self
    {
        $this->internalSummary = $internalSummary;

        return $this;
    }

    public function getIngestedAt(): ?DateTimeImmutable
    {
        return $this->ingestedAt;
    }

    public function setIngestedAt(?DateTimeImmutable $ingestedAt): self
    {
        $this->ingestedAt = $ingestedAt;

        return $this;
    }

    public function isAiGenerated(): bool
    {
        return $this->aiGenerated;
    }

    public function setAiGenerated(bool $aiGenerated): self
    {
        $this->aiGenerated = $aiGenerated;

        return $this;
    }

    public function getSourceClusterId(): ?int
    {
        return $this->sourceClusterId;
    }

    public function setSourceClusterId(?int $sourceClusterId): self
    {
        $this->sourceClusterId = $sourceClusterId;

        return $this;
    }

    public function getAiConfidenceScore(): ?float
    {
        return $this->aiConfidenceScore;
    }

    public function setAiConfidenceScore(?float $aiConfidenceScore): self
    {
        $this->aiConfidenceScore = $aiConfidenceScore;

        return $this;
    }

    public function getAiSourceCount(): ?int
    {
        return $this->aiSourceCount;
    }

    public function setAiSourceCount(?int $aiSourceCount): self
    {
        $this->aiSourceCount = $aiSourceCount;

        return $this;
    }

    /**
     * @return array<string, string>|null
     */
    public function getTranslatedSlugs(): ?array
    {
        return $this->translatedSlugs;
    }

    /**
     * @param array<string, string>|null $translatedSlugs
     */
    public function setTranslatedSlugs(?array $translatedSlugs): self
    {
        $this->translatedSlugs = $translatedSlugs;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getPublishedLocales(): array
    {
        return $this->publishedLocales;
    }

    /**
     * @param string[] $locales
     */
    public function setPublishedLocales(array $locales): self
    {
        $this->publishedLocales = array_values(array_unique($locales));

        return $this;
    }

    public function addPublishedLocale(string $locale): self
    {
        if (!\in_array($locale, $this->publishedLocales, true)) {
            $this->publishedLocales[] = $locale;
        }

        return $this;
    }

    public function removePublishedLocale(string $locale): self
    {
        $this->publishedLocales = array_values(array_filter(
            $this->publishedLocales,
            static fn (string $l): bool => $l !== $locale,
        ));

        return $this;
    }

    public function isPublishedInLocale(string $locale): bool
    {
        return \in_array($locale, $this->publishedLocales, true);
    }
}
