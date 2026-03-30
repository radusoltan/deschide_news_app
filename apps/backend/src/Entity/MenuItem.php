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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\MenuItemType;
use App\Enum\MenuType;
use App\State\MenuItemProcessor;
use App\State\MenuItemProvider;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'menu_items')]
#[ORM\Index(name: 'idx_menu_item_menu_active', columns: ['menu', 'is_active', 'position'])]
#[ORM\Index(name: 'idx_menu_item_category', columns: ['category_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/menu-items',
            normalizationContext: ['groups' => ['menu-item:read']],
            paginationItemsPerPage: 50
        ),
        new Get(
            uriTemplate: '/menu-items/{id}',
            normalizationContext: ['groups' => ['menu-item:read']]
        ),
        new Post(
            uriTemplate: '/menu-items',
            denormalizationContext: ['groups' => ['menu-item:write']]
        ),
        new Patch(
            uriTemplate: '/menu-items/{id}',
            denormalizationContext: ['groups' => ['menu-item:write']]
        ),
        new Delete(
            uriTemplate: '/menu-items/{id}'
        ),
    ],
    order: ['position' => 'ASC'],
    provider: MenuItemProvider::class,
    processor: MenuItemProcessor::class
)]
#[ApiFilter(SearchFilter::class, properties: [
    'menu' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive'])]
#[ApiFilter(OrderFilter::class, properties: ['position'])]
class MenuItem implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['menu-item:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: MenuType::class)]
    #[Assert\NotNull]
    #[Groups(['menu-item:read', 'menu-item:write'])]
    private ?MenuType $menu = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: MenuItemType::class)]
    #[Assert\NotNull]
    #[Groups(['menu-item:read', 'menu-item:write'])]
    private ?MenuItemType $type = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['menu-item:read', 'menu-item:write'])]
    private ?string $label = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    #[Assert\Url]
    #[Groups(['menu-item:read', 'menu-item:write'])]
    private ?string $url = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['menu-item:read', 'menu-item:write'])]
    private ?Category $category = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Assert\GreaterThanOrEqual(0)]
    #[Groups(['menu-item:read', 'menu-item:write'])]
    private int $position = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['menu-item:read', 'menu-item:write'])]
    #[SerializedName('isActive')]
    private bool $isActive = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['menu-item:read', 'menu-item:write'])]
    #[SerializedName('openInNewTab')]
    private bool $openInNewTab = false;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['menu-item:read', 'menu-item:write'])]
    private ?string $cssClass = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['menu-item:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['menu-item:read'])]
    private ?DateTimeImmutable $updatedAt = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    // --- Getters and Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMenu(): ?MenuType
    {
        return $this->menu;
    }

    public function setMenu(MenuType $menu): self
    {
        $this->menu = $menu;

        return $this;
    }

    public function getType(): ?MenuItemType
    {
        return $this->type;
    }

    public function setType(MenuItemType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

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

    public function isOpenInNewTab(): bool
    {
        return $this->openInNewTab;
    }

    public function setOpenInNewTab(bool $openInNewTab): self
    {
        $this->openInNewTab = $openInNewTab;

        return $this;
    }

    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    public function setCssClass(?string $cssClass): self
    {
        $this->cssClass = $cssClass;

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

    // --- Validation ---

    #[Assert\Callback]
    public function validateTypeConstraints(ExecutionContextInterface $context): void
    {
        if ($this->type === MenuItemType::EXTERNAL_LINK && (null === $this->url || '' === $this->url)) {
            $context->buildViolation('URL is required when type is "external_link".')
                ->atPath('url')
                ->addViolation();
        }

        if ($this->type === MenuItemType::CATEGORY && null === $this->category) {
            $context->buildViolation('Category is required when type is "category".')
                ->atPath('category')
                ->addViolation();
        }
    }
}
