<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Enum\ArticleStatus;
use App\Enum\CategoryStatus;
use App\Repository\CategoryRepository;
use App\State\CategoryProcessor;
use App\State\CategoryProvider;
use App\Validator as AppAssert;
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

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Cache(usage: 'READ_ONLY', region: 'long_lived')]  // L2 cache: categories rarely change
#[UniqueEntity('slug', message: 'This slug is already in use. Please choose a different slug.')]
#[ORM\Table(name: 'categories')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_category_status', columns: ['status'])]
#[ORM\Index(name: 'idx_category_on_front_page', columns: ['on_front_page'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/categories/{id}',
            normalizationContext: ['groups' => ['category:read', 'category:detail']]
        ),
        new GetCollection(
            uriTemplate: '/categories',
            normalizationContext: ['groups' => ['category:read', 'category:list']],
            paginationItemsPerPage: 50
        ),
        new Post(
            uriTemplate: '/categories',
            denormalizationContext: ['groups' => ['category:write']]
        ),
        new Put(
            uriTemplate: '/categories/{id}',
            denormalizationContext: ['groups' => ['category:write']]
        ),
        new Delete(
            uriTemplate: '/categories/{id}'
        ),
    ],
    provider: CategoryProvider::class,
    processor: CategoryProcessor::class
)]
#[ApiFilter(SearchFilter::class, properties: [
    'status' => 'exact',
    'title' => 'partial',
    'slug' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['onFrontPage'])]
class Category implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['category:read', 'article:read'])]
    private ?int $id = null;

    // Translatable fields
    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['category:read', 'category:write', 'article:read'])]
    private ?string $title = null;

    #[Gedmo\Slug(fields: ['title'], unique: true, updatable: true)]
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[AppAssert\ReservedSlug]
    #[Groups(['category:read', 'article:read'])]
    private ?string $slug = null;

    // Relationships
    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'category')]
    private Collection $articles;

    // Status & Metadata
    #[ORM\Column(type: Types::STRING, length: 20, enumType: CategoryStatus::class)]
    #[Groups(['category:read', 'category:write'])]
    private CategoryStatus $status = CategoryStatus::ACTIVE;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['category:read', 'category:write'])]
    private bool $onFrontPage = false;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['category:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['category:read'])]
    private ?DateTimeImmutable $updatedAt = null;

    // For translations
    #[Gedmo\Locale]
    #[Groups(['category:read'])]
    private ?string $locale = null;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
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
            $article->setCategory($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->removeElement($article)) {
            // set the owning side to null (unless already changed)
            if ($article->getCategory() === $this) {
                $article->setCategory(null);
            }
        }

        return $this;
    }

    public function getStatus(): CategoryStatus
    {
        return $this->status;
    }

    public function setStatus(CategoryStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isOnFrontPage(): bool
    {
        return $this->onFrontPage;
    }

    public function setOnFrontPage(bool $onFrontPage): self
    {
        $this->onFrontPage = $onFrontPage;

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
     * Computed property - article count
     * OPTIMIZATION: Only include in category detail view, not when embedded in articles.
     */
    #[Groups(['category:detail'])]
    public function getArticleCount(): int
    {
        return $this->articles
            ->filter(fn ($article) => $article->getStatus() === ArticleStatus::PUBLISHED)
            ->count();
    }
}
