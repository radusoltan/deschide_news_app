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
use App\Repository\LiveTextPostRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LiveTextPostRepository::class)]
#[ORM\Table(name: 'live_text_posts')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_live_text_post_live_text', columns: ['live_text_id'])]
#[ORM\Index(name: 'idx_live_text_post_published_at', columns: ['published_at'])]
#[ORM\Index(name: 'idx_live_text_post_is_key_point', columns: ['is_key_point'])]
#[ORM\Index(name: 'idx_live_text_post_position', columns: ['position'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/live_text_posts/{id}',
            normalizationContext: ['groups' => ['livetext_post:read', 'livetext_post:detail', 'author:read'], 'enable_max_depth' => true]
        ),
        new GetCollection(
            uriTemplate: '/live_text_posts',
            normalizationContext: ['groups' => ['livetext_post:read', 'livetext_post:list', 'author:read'], 'enable_max_depth' => true],
            paginationItemsPerPage: 30,
            paginationPartial: true,
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true
        ),
        new Get(
            uriTemplate: '/live_text_posts/{id}/reactions/count',
            provider: \App\State\LiveTextPostReactionsProvider::class
        ),
        new Post(
            uriTemplate: '/live_text_posts',
            denormalizationContext: ['groups' => ['livetext_post:write']]
        ),
        new Put(
            uriTemplate: '/live_text_posts/{id}',
            denormalizationContext: ['groups' => ['livetext_post:write']]
        ),
        new Delete(
            uriTemplate: '/live_text_posts/{id}'
        ),
    ],
    provider: \App\State\LiveTextPostProvider::class,
    processor: \App\State\LiveTextPostProcessor::class
)]
#[ApiFilter(SearchFilter::class, properties: [
    'liveText' => 'exact',
    'liveText.id' => 'exact',
    'author' => 'exact',
    'author.id' => 'exact',
    'content' => 'partial',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'publishedAt' => 'DESC',
    'position' => 'ASC',
    'createdAt' => 'DESC',
])]
#[ApiFilter(BooleanFilter::class, properties: ['isKeyPoint'])]
class LiveTextPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['livetext_post:read', 'livetext:detail'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Content cannot be blank.')]
    #[Assert\Length(min: 1, max: 10000, minMessage: 'Content must be at least {{ limit }} characters long.', maxMessage: 'Content cannot be longer than {{ limit }} characters.')]
    #[Groups(['livetext_post:read', 'livetext_post:write', 'livetext:detail'])]
    private ?string $content = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['livetext_post:read', 'livetext_post:write', 'livetext:detail'])]
    private ?string $contentHtml = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['livetext_post:read', 'livetext_post:write', 'livetext:detail'])]
    private bool $isKeyPoint = false;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['livetext_post:read', 'livetext_post:write', 'livetext:detail'])]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['livetext_post:read', 'livetext_post:write', 'livetext:detail'])]
    private ?DateTimeInterface $publishedAt = null;

    // Relationships
    #[ORM\ManyToOne(targetEntity: LiveText::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Live text must be specified.')]
    #[Groups(['livetext_post:read', 'livetext_post:write'])]
    #[MaxDepth(1)]
    private ?LiveText $liveText = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Author must be specified.')]
    #[Groups(['livetext_post:read', 'livetext_post:write', 'livetext:detail'])]
    #[MaxDepth(1)]
    private ?User $author = null;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['livetext_post:read', 'livetext:detail'])]
    private ?DateTimeInterface $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['livetext_post:read', 'livetext:detail'])]
    private ?DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->publishedAt = new DateTime();
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getContentHtml(): ?string
    {
        return $this->contentHtml;
    }

    public function setContentHtml(?string $contentHtml): static
    {
        $this->contentHtml = $contentHtml;

        return $this;
    }

    public function isKeyPoint(): bool
    {
        return $this->isKeyPoint;
    }

    public function setIsKeyPoint(bool $isKeyPoint): static
    {
        $this->isKeyPoint = $isKeyPoint;

        return $this;
    }

    public function getIsKeyPoint(): bool
    {
        return $this->isKeyPoint;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getPublishedAt(): ?DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(DateTimeInterface $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getLiveText(): ?LiveText
    {
        return $this->liveText;
    }

    public function setLiveText(?LiveText $liveText): static
    {
        $this->liveText = $liveText;

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
}
