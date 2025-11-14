<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Enum\ArticleStatus;
use App\Enum\AuthorStatus;
use App\Repository\AuthorRepository;
use App\State\AuthorProcessor;
use App\State\AuthorProvider;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuthorRepository::class)]
#[ORM\Table(name: 'authors')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('email')]
#[UniqueEntity('slug')]
// Single column indexes
#[ORM\Index(name: 'idx_author_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_author_email', columns: ['email'])]
#[ORM\Index(name: 'idx_author_status', columns: ['status'])]
#[ORM\Index(name: 'idx_author_is_active', columns: ['is_active'])]
// Composite indexes for common queries
#[ORM\Index(name: 'idx_author_active_status', columns: ['is_active', 'status'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/authors/{id}',
            normalizationContext: ['groups' => ['author:read', 'author:detail']]
        ),
        new GetCollection(
            uriTemplate: '/authors',
            normalizationContext: ['groups' => ['author:read', 'author:list']],
            paginationItemsPerPage: 50
        ),
        new Post(
            uriTemplate: '/authors',
            denormalizationContext: ['groups' => ['author:write', 'author:create']]
        ),
        new Put(
            uriTemplate: '/authors/{id}',
            denormalizationContext: ['groups' => ['author:write']]
        ),
        new Delete(
            uriTemplate: '/authors/{id}'
        ),
    ],
    provider: AuthorProvider::class,
    processor: AuthorProcessor::class
)]
class Author implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['author:read'])]
    private ?int $id = null;

    // Basic Information
    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['author:read', 'author:write'])]
    private ?string $firstName = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['author:read', 'author:write'])]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Assert\Email]
    #[Groups(['author:read', 'author:create'])]
    private ?string $email = null;

    #[Gedmo\Slug(fields: ['firstName', 'lastName'], unique: true, updatable: true)]
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[Groups(['author:read'])]
    private ?string $slug = null;

    // Translatable fields
    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['author:read', 'author:write'])]
    private ?string $bio = null;

    // Relationships
    #[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'authors')]
    private Collection $articles;

    // Metadata
    #[ORM\Column(type: Types::STRING, length: 20, enumType: AuthorStatus::class)]
    #[Groups(['author:read', 'author:write'])]
    private AuthorStatus $status = AuthorStatus::ACTIVE;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['author:read', 'author:write'])]
    private bool $isActive = true;

    // Social Media
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['author:read', 'author:write'])]
    private ?string $twitter = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Url]
    #[Groups(['author:read', 'author:write'])]
    private ?string $facebook = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Url]
    #[Groups(['author:read', 'author:write'])]
    private ?string $linkedin = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Url]
    #[Groups(['author:read', 'author:write'])]
    private ?string $website = null;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['author:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['author:read'])]
    private ?DateTimeImmutable $updatedAt = null;

    // For translations
    #[Gedmo\Locale]
    #[Groups(['author:read'])]
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

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

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

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;

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
            $article->addAuthor($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->removeElement($article)) {
            $article->removeAuthor($this);
        }

        return $this;
    }

    public function getStatus(): AuthorStatus
    {
        return $this->status;
    }

    public function setStatus(AuthorStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getTwitter(): ?string
    {
        return $this->twitter;
    }

    public function setTwitter(?string $twitter): self
    {
        $this->twitter = $twitter;

        return $this;
    }

    public function getFacebook(): ?string
    {
        return $this->facebook;
    }

    public function setFacebook(?string $facebook): self
    {
        $this->facebook = $facebook;

        return $this;
    }

    public function getLinkedin(): ?string
    {
        return $this->linkedin;
    }

    public function setLinkedin(?string $linkedin): self
    {
        $this->linkedin = $linkedin;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): self
    {
        $this->website = $website;

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
     * Computed property - full name.
     */
    #[Groups(['author:read'])]
    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /**
     * Computed property - article count
     * OPTIMIZATION: Only include in author detail view, not when embedded in articles.
     */
    #[Groups(['author:detail'])]
    public function getArticleCount(): int
    {
        return $this->articles
            ->filter(fn ($article) => $article->getStatus() === ArticleStatus::PUBLISHED)
            ->count();
    }

    /**
     * Computed property - initials.
     */
    #[Groups(['author:read'])]
    public function getInitials(): string
    {
        $first = mb_substr($this->firstName ?? '', 0, 1);
        $last = mb_substr($this->lastName ?? '', 0, 1);

        return mb_strtoupper($first . $last);
    }
}
