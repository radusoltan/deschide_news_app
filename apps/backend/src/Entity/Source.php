<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SourceCategory;
use App\Repository\SourceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SourceRepository::class)]
#[ORM\Table(name: 'sources')]
#[ORM\Index(name: 'idx_source_is_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_source_country', columns: ['country'])]
#[ORM\Index(name: 'idx_source_category', columns: ['source_category'])]
#[UniqueEntity('name', message: 'A source with this name already exists.')]
class Source
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['source:read', 'cluster:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['source:read', 'source:write', 'cluster:read'])]
    private string $name;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Groups(['source:read', 'source:write'])]
    private ?string $rssUrl = null;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.5])]
    #[Groups(['source:read', 'source:write'])]
    private float $credibilityWeight = 0.5;

    /** ISO 3166-1 alpha-2 country code */
    #[ORM\Column(length: 2, nullable: true)]
    #[Groups(['source:read', 'source:write'])]
    private ?string $country = null;

    #[ORM\Column(length: 30, nullable: true, enumType: SourceCategory::class)]
    #[Groups(['source:read', 'source:write'])]
    private ?SourceCategory $sourceCategory = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 60])]
    #[Groups(['source:read', 'source:write'])]
    private int $fetchFrequencyMinutes = 60;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['source:read', 'source:write'])]
    private bool $isActive = true;

    #[ORM\Column(length: 20, options: ['default' => 'rss'])]
    #[Groups(['source:read', 'source:write'])]
    private string $type = 'rss';

    /** Domain pattern for matching PressReleases to this source */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['source:read', 'source:write'])]
    private ?string $domainPattern = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['source:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getRssUrl(): ?string { return $this->rssUrl; }
    public function setRssUrl(?string $rssUrl): static { $this->rssUrl = $rssUrl; return $this; }

    public function getCredibilityWeight(): float { return $this->credibilityWeight; }
    public function setCredibilityWeight(float $credibilityWeight): static { $this->credibilityWeight = $credibilityWeight; return $this; }

    public function getCountry(): ?string { return $this->country; }
    public function setCountry(?string $country): static { $this->country = $country; return $this; }

    public function getSourceCategory(): ?SourceCategory { return $this->sourceCategory; }
    public function setSourceCategory(?SourceCategory $sourceCategory): static { $this->sourceCategory = $sourceCategory; return $this; }

    public function getFetchFrequencyMinutes(): int { return $this->fetchFrequencyMinutes; }
    public function setFetchFrequencyMinutes(int $fetchFrequencyMinutes): static { $this->fetchFrequencyMinutes = $fetchFrequencyMinutes; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getDomainPattern(): ?string { return $this->domainPattern; }
    public function setDomainPattern(?string $domainPattern): static { $this->domainPattern = $domainPattern; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
