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
use App\Repository\ShortLinkRepository;
use App\State\ShortLinkProcessor;
use App\State\ShortLinkStatsProvider;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShortLinkRepository::class)]
#[ORM\Table(name: 'short_links')]
#[ORM\Index(name: 'idx_short_link_code', columns: ['code'])]
#[ORM\Index(name: 'idx_short_link_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_short_link_click_count', columns: ['click_count'])]
#[UniqueEntity('code', message: 'This short code is already in use.')]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/short_links/{id}',
            normalizationContext: ['groups' => ['short_link:read', 'short_link:detail']]
        ),
        new GetCollection(
            uriTemplate: '/short_links',
            normalizationContext: ['groups' => ['short_link:read', 'short_link:list']],
            paginationItemsPerPage: 20
        ),
        new Get(
            uriTemplate: '/short_links/{id}/stats',
            normalizationContext: ['groups' => ['short_link:stats']],
            provider: ShortLinkStatsProvider::class
        ),
        new Post(
            uriTemplate: '/short_links',
            denormalizationContext: ['groups' => ['short_link:write']],
            processor: ShortLinkProcessor::class
        ),
        new Delete(
            uriTemplate: '/short_links/{id}'
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'code' => 'exact',
    'title' => 'partial',
    'article' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'createdAt' => 'DESC',
    'clickCount' => 'DESC',
])]
class ShortLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['short_link:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 50)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_-]+$/',
        message: 'Short code can only contain letters, numbers, dashes and underscores.'
    )]
    #[Groups(['short_link:read', 'short_link:write'])]
    private ?string $code = null;

    #[ORM\Column(type: Types::STRING, length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    #[Assert\Url]
    #[Groups(['short_link:read', 'short_link:write'])]
    private ?string $originalUrl = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['short_link:read', 'short_link:write'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['short_link:read'])]
    private int $clickCount = 0;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['short_link:read', 'short_link:write'])]
    private ?Article $article = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['short_link:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['short_link:read'])]
    private ?User $createdBy = null;

    /**
     * @var Collection<int, ShortLinkInteraction>
     */
    #[ORM\OneToMany(targetEntity: ShortLinkInteraction::class, mappedBy: 'shortLink', cascade: ['remove'], orphanRemoval: true)]
    private Collection $interactions;

    public function __construct()
    {
        $this->interactions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getOriginalUrl(): ?string
    {
        return $this->originalUrl;
    }

    public function setOriginalUrl(string $originalUrl): self
    {
        $this->originalUrl = $originalUrl;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getClickCount(): int
    {
        return $this->clickCount;
    }

    public function setClickCount(int $clickCount): self
    {
        $this->clickCount = $clickCount;

        return $this;
    }

    public function incrementClickCount(): self
    {
        ++$this->clickCount;

        return $this;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): self
    {
        $this->article = $article;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return Collection<int, ShortLinkInteraction>
     */
    public function getInteractions(): Collection
    {
        return $this->interactions;
    }

    public function addInteraction(ShortLinkInteraction $interaction): self
    {
        if (!$this->interactions->contains($interaction)) {
            $this->interactions->add($interaction);
            $interaction->setShortLink($this);
        }

        return $this;
    }

    public function removeInteraction(ShortLinkInteraction $interaction): self
    {
        if ($this->interactions->removeElement($interaction)) {
            if ($interaction->getShortLink() === $this) {
                $interaction->setShortLink(null);
            }
        }

        return $this;
    }

    /**
     * Get the full short URL.
     */
    #[Groups(['short_link:read'])]
    public function getShortUrl(): string
    {
        return '/s/' . $this->code;
    }
}
