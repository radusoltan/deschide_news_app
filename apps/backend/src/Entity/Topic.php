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
])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive'])]
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

    /** @var Collection<int, Article> */
    #[ORM\ManyToMany(targetEntity: Article::class, inversedBy: 'topics')]
    #[ORM\JoinTable(name: 'article_topics')]
    private Collection $articles;

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
