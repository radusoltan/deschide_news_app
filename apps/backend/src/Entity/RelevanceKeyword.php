<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Enum\KeywordAddedBy;
use App\Repository\RelevanceKeywordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RelevanceKeywordRepository::class)]
#[ORM\Table(name: 'relevance_keywords')]
#[ORM\Index(name: 'idx_rk_tier', columns: ['tier'])]
#[ORM\Index(name: 'idx_rk_is_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_rk_language', columns: ['language'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/relevance-keywords',
            normalizationContext: ['groups' => ['keyword:read']],
            security: "is_granted('ROLE_EDITOR')",
            paginationItemsPerPage: 30,
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true,
        ),
        new Get(
            uriTemplate: '/relevance-keywords/{id}',
            normalizationContext: ['groups' => ['keyword:read']],
            security: "is_granted('ROLE_EDITOR')",
        ),
        new Post(
            uriTemplate: '/relevance-keywords',
            normalizationContext: ['groups' => ['keyword:read']],
            denormalizationContext: ['groups' => ['keyword:write']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Put(
            uriTemplate: '/relevance-keywords/{id}',
            normalizationContext: ['groups' => ['keyword:read']],
            denormalizationContext: ['groups' => ['keyword:write']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Delete(
            uriTemplate: '/relevance-keywords/{id}',
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
    order: ['tier' => 'ASC', 'keyword' => 'ASC'],
    normalizationContext: ['groups' => ['keyword:read']],
    denormalizationContext: ['groups' => ['keyword:write']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'keyword' => 'partial',
    'language' => 'exact',
    'addedBy' => 'exact',
])]
#[ApiFilter(RangeFilter::class, properties: ['tier'])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive'])]
#[ApiFilter(OrderFilter::class, properties: ['tier', 'keyword', 'createdAt'])]
class RelevanceKeyword
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['keyword:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['keyword:read', 'keyword:write'])]
    private string $keyword;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 4)]
    #[Groups(['keyword:read', 'keyword:write'])]
    private int $tier;

    #[ORM\Column(length: 5)]
    #[Assert\Choice(choices: ['ro', 'en', 'ru'])]
    #[Groups(['keyword:read', 'keyword:write'])]
    private string $language;

    #[ORM\Column]
    #[Groups(['keyword:read', 'keyword:write'])]
    private bool $isActive = true;

    #[ORM\Column(length: 20, enumType: KeywordAddedBy::class)]
    #[Groups(['keyword:read', 'keyword:write'])]
    private KeywordAddedBy $addedBy = KeywordAddedBy::MANUAL;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['keyword:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function setKeyword(string $keyword): self
    {
        $this->keyword = $keyword;

        return $this;
    }

    public function getTier(): int
    {
        return $this->tier;
    }

    public function setTier(int $tier): self
    {
        $this->tier = $tier;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;

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

    public function getAddedBy(): KeywordAddedBy
    {
        return $this->addedBy;
    }

    public function setAddedBy(KeywordAddedBy $addedBy): self
    {
        $this->addedBy = $addedBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
