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
use App\Enum\TopicStatus;
use App\Repository\TopicRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

#[Gedmo\Tree(type: 'nested')]
#[ORM\Entity(repositoryClass: TopicRepository::class)]
#[ORM\Table(name: 'topics')]
#[ORM\Index(name: 'idx_topic_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_topic_is_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_topic_lft_rgt', columns: ['lft', 'rgt'])]
#[ORM\Index(name: 'idx_topic_parent', columns: ['parent_id'])]
#[ORM\Index(name: 'idx_topic_root', columns: ['root_id'])]
#[ORM\Index(name: 'idx_topic_status', columns: ['status'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/topics/{id}',
            normalizationContext: ['groups' => ['topic:read'], 'enable_max_depth' => true],
            cacheHeaders: [
                'max_age' => 300,
                'shared_max_age' => 600,
                'vary' => ['Accept', 'Accept-Language'],
            ]
        ),
        new GetCollection(
            uriTemplate: '/topics',
            normalizationContext: ['groups' => ['topic:read'], 'enable_max_depth' => true],
            paginationItemsPerPage: 50,
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true,
            cacheHeaders: [
                'max_age' => 300,
                'shared_max_age' => 600,
                'vary' => ['Accept', 'Accept-Language'],
            ]
        ),
        new Post(
            uriTemplate: '/topics',
            denormalizationContext: ['groups' => ['topic:write']],
            security: "is_granted('ROLE_EDITOR')"
        ),
        new Put(
            uriTemplate: '/topics/{id}',
            denormalizationContext: ['groups' => ['topic:write']],
            security: "is_granted('ROLE_EDITOR')"
        ),
        new Delete(
            uriTemplate: '/topics/{id}',
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    order: ['lft' => 'ASC']
)]
#[ApiFilter(SearchFilter::class, properties: [
    'title' => 'partial',
    'slug' => 'exact',
    'status' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive', 'isSensitive', 'isStoryLeaf'])]
#[ApiFilter(OrderFilter::class, properties: ['title', 'lft', 'position'])]
class Topic implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['topic:read', 'article:read'])]
    private ?int $id = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['topic:read', 'topic:write', 'article:read'])]
    private ?string $title = null;

    #[Gedmo\Translatable]
    #[Gedmo\Slug(fields: ['title'], unique: true, updatable: true)]
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[Groups(['topic:read', 'article:read'])]
    private ?string $slug = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['topic:read', 'topic:write'])]
    private ?string $description = null;

    #[Gedmo\TreeParent]
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['topic:read', 'topic:write'])]
    private ?self $parent = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist'])]
    #[ORM\OrderBy(['lft' => 'ASC'])]
    #[Groups(['topic:read'])]
    #[MaxDepth(2)]
    private Collection $children;

    #[Gedmo\TreeLeft]
    #[ORM\Column(type: Types::INTEGER)]
    private int $lft = 0;

    #[Gedmo\TreeRight]
    #[ORM\Column(type: Types::INTEGER)]
    private int $rgt = 0;

    #[Gedmo\TreeLevel]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['topic:read'])]
    private int $lvl = 0;

    #[Gedmo\TreeRoot]
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'root_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?self $root = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['topic:read', 'topic:write'])]
    private int $position = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['topic:read', 'topic:write'])]
    private bool $isActive = true;

    #[ORM\Column(length: 20, options: ['default' => 'approved'])]
    #[Groups(['topic:read', 'topic:write'])]
    private string $reviewStatus = 'approved';

    /** Weight for importance scoring in StoryCluster calculations */
    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.5])]
    #[Groups(['topic:read', 'topic:write'])]
    private float $weight = 0.5;

    /** Blocks auto-publish gate when true (e.g. conflict zones, elections) */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['topic:read', 'topic:write'])]
    private bool $isSensitive = false;

    /** Lifecycle state: ACTIVE (in use), ARCHIVED (historical), PROPOSED (pending review) */
    #[ORM\Column(type: Types::STRING, length: 20, enumType: TopicStatus::class, options: ['default' => 'active'])]
    #[Groups(['topic:read', 'topic:write'])]
    private TopicStatus $status = TopicStatus::ACTIVE;

    /** Distinguishes stable taxonomy nodes from dynamic story-specific leaves */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['topic:read', 'topic:write'])]
    private bool $isStoryLeaf = false;

    /** When this topic became editorially active (for story leaves with temporal bounds) */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['topic:read', 'topic:write'])]
    private ?DateTimeImmutable $lifecycleStartedAt = null;

    /** When this topic was editorially concluded (for story leaves with temporal bounds) */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['topic:read', 'topic:write'])]
    private ?DateTimeImmutable $lifecycleEndedAt = null;

    /**
     * Keywords for Elasticsearch matching (title + content + summary).
     * Populated from YAML fixture; used by topic detection pipeline.
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['topic:read', 'topic:write'])]
    private ?array $keywords = null;

    /** @var Collection<int, Article> */
    #[ORM\ManyToMany(targetEntity: Article::class, inversedBy: 'topics')]
    #[ORM\JoinTable(name: 'article_topics')]
    private Collection $articles;

    /** @var Collection<int, PressReleaseTopic> */
    #[ORM\OneToMany(targetEntity: PressReleaseTopic::class, mappedBy: 'topic', cascade: ['remove'], orphanRemoval: true)]
    private Collection $pressReleaseTopics;

    /** NotebookLM notebook ID for this topic (provisioned by ensureNotebookForTopic) */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['topic:read', 'topic:write'])]
    private ?string $notebookLmId = null;

    /** When sources were last synced to the NotebookLM notebook */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['topic:read'])]
    private ?DateTimeImmutable $notebookLastSyncedAt = null;

    /** Number of sources currently in the NotebookLM notebook */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['topic:read'])]
    private int $notebookSourceCount = 0;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['topic:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['topic:read'])]
    private ?DateTimeImmutable $updatedAt = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    /**
     * Non-persisted field populated by TopicProvider.
     *
     * @var array<string, string>|null
     */
    #[Groups(['topic:read'])]
    private ?array $translatedSlugs = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->pressReleaseTopics = new ArrayCollection();
    }

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): self
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    public function getLft(): int
    {
        return $this->lft;
    }

    public function getRgt(): int
    {
        return $this->rgt;
    }

    public function getLvl(): int
    {
        return $this->lvl;
    }

    public function getRoot(): ?self
    {
        return $this->root;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        $this->articles->removeElement($article);

        return $this;
    }

    /** @return Collection<int, PressReleaseTopic> */
    public function getPressReleaseTopics(): Collection
    {
        return $this->pressReleaseTopics;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getReviewStatus(): string
    {
        return $this->reviewStatus;
    }

    public function setReviewStatus(string $reviewStatus): self
    {
        $this->reviewStatus = $reviewStatus;

        return $this;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function isSensitive(): bool
    {
        return $this->isSensitive;
    }

    public function getIsSensitive(): bool
    {
        return $this->isSensitive;
    }

    public function setIsSensitive(bool $isSensitive): self
    {
        $this->isSensitive = $isSensitive;

        return $this;
    }

    public function getStatus(): TopicStatus
    {
        return $this->status;
    }

    public function setStatus(TopicStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isStoryLeaf(): bool
    {
        return $this->isStoryLeaf;
    }

    public function getIsStoryLeaf(): bool
    {
        return $this->isStoryLeaf;
    }

    public function setIsStoryLeaf(bool $isStoryLeaf): self
    {
        $this->isStoryLeaf = $isStoryLeaf;

        return $this;
    }

    public function getLifecycleStartedAt(): ?DateTimeImmutable
    {
        return $this->lifecycleStartedAt;
    }

    public function setLifecycleStartedAt(?DateTimeImmutable $lifecycleStartedAt): self
    {
        $this->lifecycleStartedAt = $lifecycleStartedAt;

        return $this;
    }

    public function getLifecycleEndedAt(): ?DateTimeImmutable
    {
        return $this->lifecycleEndedAt;
    }

    public function setLifecycleEndedAt(?DateTimeImmutable $lifecycleEndedAt): self
    {
        $this->lifecycleEndedAt = $lifecycleEndedAt;

        return $this;
    }

    /**
     * @return list<string>|null
     */
    public function getKeywords(): ?array
    {
        return $this->keywords;
    }

    /**
     * @param list<string>|null $keywords
     */
    public function setKeywords(?array $keywords): self
    {
        $this->keywords = $keywords;

        return $this;
    }

    public function getNotebookLmId(): ?string
    {
        return $this->notebookLmId;
    }

    public function setNotebookLmId(?string $notebookLmId): self
    {
        $this->notebookLmId = $notebookLmId;

        return $this;
    }

    public function getNotebookLastSyncedAt(): ?DateTimeImmutable
    {
        return $this->notebookLastSyncedAt;
    }

    public function setNotebookLastSyncedAt(?DateTimeImmutable $notebookLastSyncedAt): self
    {
        $this->notebookLastSyncedAt = $notebookLastSyncedAt;

        return $this;
    }

    public function getNotebookSourceCount(): int
    {
        return $this->notebookSourceCount;
    }

    public function setNotebookSourceCount(int $notebookSourceCount): self
    {
        $this->notebookSourceCount = $notebookSourceCount;

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
}
