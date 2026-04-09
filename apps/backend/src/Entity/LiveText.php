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
use App\Enum\LiveTextStatus;
use App\Repository\LiveTextRepository;
use DateTimeInterface;
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

#[ORM\Entity(repositoryClass: LiveTextRepository::class)]
#[UniqueEntity('slug', message: 'This slug is already in use. Please choose a different slug.')]
#[ORM\Table(name: 'live_texts')]
#[ORM\HasLifecycleCallbacks]
// Single column indexes
#[ORM\Index(name: 'idx_live_text_status', columns: ['status'])]
#[ORM\Index(name: 'idx_live_text_start_time', columns: ['start_time'])]
#[ORM\Index(name: 'idx_live_text_end_time', columns: ['end_time'])]
// Composite indexes for common queries
#[ORM\Index(name: 'idx_livetext_status_start', columns: ['status', 'start_time'])]
#[ORM\Index(name: 'idx_livetext_status_end', columns: ['status', 'end_time'])]
#[ORM\Index(name: 'idx_livetext_category_status_start', columns: ['category_id', 'status', 'start_time'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/live_texts/{id}',
            normalizationContext: ['groups' => ['livetext:read', 'livetext:detail', 'category:read', 'author:read'], 'enable_max_depth' => true]
        ),
        new GetCollection(
            uriTemplate: '/live_texts',
            normalizationContext: ['groups' => ['livetext:read', 'livetext:list', 'category:read', 'author:read'], 'enable_max_depth' => true],
            paginationItemsPerPage: 20,
            paginationPartial: true,
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true
        ),
        new Get(
            uriTemplate: '/live_texts/{id}/key_points',
            normalizationContext: ['groups' => ['livetext_post:read', 'author:read'], 'enable_max_depth' => true],
            provider: \App\State\LiveTextKeyPointsProvider::class
        ),
        new Get(
            uriTemplate: '/live_texts/{id}/analytics',
            normalizationContext: ['groups' => ['livetext_analytics:read'], 'enable_max_depth' => true],
            provider: \App\State\LiveTextAnalyticsProvider::class,
            output: \App\Dto\LiveText\LiveTextAnalyticsDto::class
        ),
        new Post(
            uriTemplate: '/live_texts',
            denormalizationContext: ['groups' => ['livetext:write']]
        ),
        new Put(
            uriTemplate: '/live_texts/{id}',
            denormalizationContext: ['groups' => ['livetext:write']]
        ),
        new Delete(
            uriTemplate: '/live_texts/{id}'
        ),
    ],
    provider: \App\State\LiveTextProvider::class,
    processor: \App\State\LiveTextProcessor::class
)]
#[ApiFilter(SearchFilter::class, properties: [
    'category' => 'exact',
    'category.id' => 'exact',
    'status' => 'exact',
    'title' => 'partial',
    'slug' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'startTime' => 'DESC',
    'endTime' => 'DESC',
    'createdAt' => 'DESC',
    'title' => 'ASC',
])]
class LiveText implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['livetext:read'])]
    private ?int $id = null;

    // Translatable fields
    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['livetext:read', 'livetext:write'])]
    private ?string $title = null;

    #[Gedmo\Slug(fields: ['title'])]
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[Groups(['livetext:read', 'livetext:write'])]
    private ?string $slug = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['livetext:read', 'livetext:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: LiveTextStatus::class)]
    #[Groups(['livetext:read', 'livetext:write'])]
    private LiveTextStatus $status = LiveTextStatus::DRAFT;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['livetext:read', 'livetext:write'])]
    private ?DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['livetext:read', 'livetext:write'])]
    private ?DateTimeInterface $endTime = null;

    // Locale (for Gedmo Translatable)
    #[Gedmo\Locale]
    #[Groups(['livetext:read', 'livetext:write'])]
    private ?string $locale = null;

    // Relationships
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['livetext:read', 'livetext:write'])]
    #[MaxDepth(1)]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['livetext:read', 'livetext:write'])]
    #[MaxDepth(1)]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: LiveTextTemplate::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['livetext:read', 'livetext:write', 'livetext:detail'])]
    #[MaxDepth(1)]
    private ?LiveTextTemplate $template = null;

    /**
     * Sport match details (optional, only for sport events).
     */
    #[ORM\OneToOne(mappedBy: 'liveText', targetEntity: LiveTextSportMatch::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['livetext:read', 'livetext:detail'])]
    #[MaxDepth(2)]
    private ?LiveTextSportMatch $sportMatch = null;

    /**
     * @var Collection<int, LiveTextCollaborator>
     */
    #[ORM\OneToMany(targetEntity: LiveTextCollaborator::class, mappedBy: 'liveText', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['livetext:read', 'livetext:detail'])]
    #[MaxDepth(2)]
    private Collection $collaborators;

    /**
     * @var Collection<int, LiveTextPost>
     */
    #[ORM\OneToMany(targetEntity: LiveTextPost::class, mappedBy: 'liveText', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['livetext:read', 'livetext:detail'])]
    #[MaxDepth(2)]
    private Collection $posts;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['livetext:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['livetext:read'])]
    private ?DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->collaborators = new ArrayCollection();
        $this->posts = new ArrayCollection();
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): LiveTextStatus
    {
        return $this->status;
    }

    public function setStatus(LiveTextStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStartTime(): ?DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(?DateTimeInterface $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(?DateTimeInterface $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getTemplate(): ?LiveTextTemplate
    {
        return $this->template;
    }

    public function setTemplate(?LiveTextTemplate $template): static
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return Collection<int, LiveTextCollaborator>
     */
    public function getCollaborators(): Collection
    {
        return $this->collaborators;
    }

    public function addCollaborator(LiveTextCollaborator $collaborator): static
    {
        if (!$this->collaborators->contains($collaborator)) {
            $this->collaborators->add($collaborator);
            $collaborator->setLiveText($this);
        }

        return $this;
    }

    public function removeCollaborator(LiveTextCollaborator $collaborator): static
    {
        if ($this->collaborators->removeElement($collaborator)) {
            // set the owning side to null (unless already changed)
            if ($collaborator->getLiveText() === $this) {
                $collaborator->setLiveText(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LiveTextPost>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(LiveTextPost $post): static
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setLiveText($this);
        }

        return $this;
    }

    public function removePost(LiveTextPost $post): static
    {
        if ($this->posts->removeElement($post)) {
            // set the owning side to null (unless already changed)
            if ($post->getLiveText() === $this) {
                $post->setLiveText(null);
            }
        }

        return $this;
    }

    public function getSportMatch(): ?LiveTextSportMatch
    {
        return $this->sportMatch;
    }

    public function setSportMatch(?LiveTextSportMatch $sportMatch): static
    {
        // Unset the owning side of the relation if necessary
        if ($sportMatch === null && $this->sportMatch !== null) {
            $this->sportMatch->setLiveText(null);
        }

        // Set the owning side of the relation if necessary
        if ($sportMatch !== null && $sportMatch->getLiveText() !== $this) {
            $sportMatch->setLiveText($this);
        }

        $this->sportMatch = $sportMatch;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function setTranslatableLocale(?string $locale): void
    {
        $this->locale = $locale;
    }
}
