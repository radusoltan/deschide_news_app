<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\TagRepository;
use App\State\TagProcessor;
use App\State\TagProvider;
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

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[UniqueEntity('slug', message: 'This slug is already in use.')]
#[ORM\Table(name: 'tags')]
#[ORM\Index(name: 'idx_tag_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_tag_usage_count', columns: ['usage_count'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/tags/{id}',
            normalizationContext: ['groups' => ['tag:read'], 'enable_max_depth' => true],
            cacheHeaders: [
                'max_age' => 300,           // 5 minutes client cache
                'shared_max_age' => 600,    // 10 minutes proxy/CDN cache
                'vary' => ['Accept', 'Accept-Language'],
            ]
        ),
        new GetCollection(
            uriTemplate: '/tags',
            normalizationContext: ['groups' => ['tag:read'], 'enable_max_depth' => true],
            paginationItemsPerPage: 30,
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true,
            cacheHeaders: [
                'max_age' => 300,           // 5 minutes client cache
                'shared_max_age' => 600,    // 10 minutes proxy/CDN cache
                'vary' => ['Accept', 'Accept-Language'],
            ]
        ),
        new Post(
            uriTemplate: '/tags',
            denormalizationContext: ['groups' => ['tag:write']]
        ),
        new Put(
            uriTemplate: '/tags/{id}',
            denormalizationContext: ['groups' => ['tag:write']]
        ),
        new Delete(
            uriTemplate: '/tags/{id}'
        ),
    ],
    provider: TagProvider::class,
    processor: TagProcessor::class
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'slug' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'usageCount' => 'DESC',
    'name' => 'ASC',
    'createdAt' => 'DESC',
])]
class Tag implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['tag:read', 'article:read'])]
    private ?int $id = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['tag:read', 'tag:write', 'article:read'])]
    private ?string $name = null;

    #[Gedmo\Translatable]
    #[Gedmo\Slug(fields: ['name'], unique: true, updatable: true)]
    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Groups(['tag:read', 'article:read'])]
    private ?string $slug = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['tag:read', 'tag:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['tag:read'])]
    private int $usageCount = 0;

    #[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'tags')]
    private Collection $articles;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['tag:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['tag:read'])]
    private ?DateTimeImmutable $updatedAt = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): self
    {
        $this->usageCount = $usageCount;

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
            $article->addTag($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->removeElement($article)) {
            $article->removeTag($this);
        }

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function setTranslatableLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }
}
