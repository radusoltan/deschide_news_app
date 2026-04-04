<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\AiPromptTemplateRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AiPromptTemplateRepository::class)]
#[ORM\Table(name: 'ai_prompt_templates')]
#[ORM\Index(name: 'idx_ai_tpl_category', columns: ['category'])]
#[ORM\Index(name: 'idx_ai_tpl_agent_type', columns: ['agent_type'])]
#[ORM\Index(name: 'idx_ai_tpl_active', columns: ['is_active'])]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['ai_tpl:read']],
            paginationItemsPerPage: 50,
        ),
        new Get(
            normalizationContext: ['groups' => ['ai_tpl:read', 'ai_tpl:detail']],
        ),
    ],
    order: ['sortOrder' => 'ASC', 'name' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['category' => 'exact', 'agentType' => 'exact'])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive'])]
class AiPromptTemplate implements Translatable
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['ai_tpl:read'])]
    private Uuid $id;

    #[Gedmo\Translatable]
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['ai_tpl:read'])]
    private string $name;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['ai_tpl:read'])]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['research', 'content', 'translation', 'briefing'])]
    #[Groups(['ai_tpl:read'])]
    private string $category;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['research', 'content', 'translation', 'briefing'])]
    #[Groups(['ai_tpl:read'])]
    private string $agentType;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Groups(['ai_tpl:read', 'ai_tpl:detail'])]
    private string $promptTemplate;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['ai_tpl:read'])]
    private array $requiredFields = [];

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['ai_tpl:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['ai_tpl:read'])]
    private int $sortOrder = 0;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['ai_tpl:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['ai_tpl:read'])]
    private ?DateTimeImmutable $updatedAt = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getAgentType(): string
    {
        return $this->agentType;
    }

    public function setAgentType(string $agentType): static
    {
        $this->agentType = $agentType;

        return $this;
    }

    public function getPromptTemplate(): string
    {
        return $this->promptTemplate;
    }

    public function setPromptTemplate(string $promptTemplate): static
    {
        $this->promptTemplate = $promptTemplate;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRequiredFields(): array
    {
        return $this->requiredFields;
    }

    /**
     * @param list<string> $requiredFields
     */
    public function setRequiredFields(array $requiredFields): static
    {
        $this->requiredFields = $requiredFields;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

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
}
