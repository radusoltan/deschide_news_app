<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Enum\TemplateType;
use App\Repository\LiveTextTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LiveTextTemplateRepository::class)]
#[ORM\Table(name: 'live_text_templates')]
#[ORM\Index(name: 'idx_template_type', columns: ['type'])]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['template:read']]
        ),
        new Get(
            normalizationContext: ['groups' => ['template:read', 'template:detail']]
        ),
        new Post(
            normalizationContext: ['groups' => ['template:read']],
            denormalizationContext: ['groups' => ['template:write']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Put(
            normalizationContext: ['groups' => ['template:read']],
            denormalizationContext: ['groups' => ['template:write']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Patch(
            normalizationContext: ['groups' => ['template:read']],
            denormalizationContext: ['groups' => ['template:write']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    paginationEnabled: false
)]
class LiveTextTemplate
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['template:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['template:read', 'template:write', 'livetext:read'])]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['template:read', 'template:write', 'template:detail'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, enumType: TemplateType::class)]
    #[Assert\NotNull]
    #[Groups(['template:read', 'template:write', 'livetext:read'])]
    private TemplateType $type;

    /**
     * JSON configuration for template customization
     * Structure:
     * {
     *   "colors": {
     *     "primary": "#ef4444",
     *     "secondary": "#dc2626",
     *     "accent": "#b91c1c",
     *     "background": "#fef2f2",
     *     "text": "#7f1d1d"
     *   },
     *   "layout": {
     *     "headerStyle": "bold|minimal|banner",
     *     "postStyle": "card|compact|full",
     *     "showTimeline": true,
     *     "sidebarPosition": "left|right"
     *   },
     *   "features": {
     *     "enableReactions": true,
     *     "enableKeyPoints": true,
     *     "enableTimeline": true,
     *     "autoRefresh": true,
     *     "refreshInterval": 30
     *   }
     * }.
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull]
    #[Groups(['template:read', 'template:write', 'template:detail'])]
    private array $config = [];

    /**
     * Indicates if this is a system-provided template (not editable).
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['template:read', 'template:detail'])]
    private bool $isSystem = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function getType(): TemplateType
    {
        return $this->type;
    }

    public function setType(TemplateType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function getIsSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): self
    {
        $this->isSystem = $isSystem;

        return $this;
    }

    /**
     * Get a specific config value by path (dot notation)
     * Example: getConfigValue('colors.primary') returns '#ef4444'.
     */
    public function getConfigValue(string $path, mixed $default = null): mixed
    {
        $keys = explode('.', $path);
        $value = $this->config;

        foreach ($keys as $key) {
            if (!\is_array($value) || !isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Set a specific config value by path (dot notation).
     */
    public function setConfigValue(string $path, mixed $value): self
    {
        $keys = explode('.', $path);
        $config = &$this->config;

        foreach ($keys as $i => $key) {
            if ($i === \count($keys) - 1) {
                $config[$key] = $value;
            } else {
                if (!isset($config[$key]) || !\is_array($config[$key])) {
                    $config[$key] = [];
                }
                $config = &$config[$key];
            }
        }

        return $this;
    }
}
