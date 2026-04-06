<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\KeywordAddedBy;
use App\Repository\RelevanceKeywordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RelevanceKeywordRepository::class)]
#[ORM\Table(name: 'relevance_keywords')]
#[ORM\Index(name: 'idx_rk_tier', columns: ['tier'])]
#[ORM\Index(name: 'idx_rk_is_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_rk_language', columns: ['language'])]
class RelevanceKeyword
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $keyword;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $tier;

    #[ORM\Column(length: 5)]
    private string $language;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(length: 20, enumType: KeywordAddedBy::class)]
    private KeywordAddedBy $addedBy = KeywordAddedBy::MANUAL;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
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
