<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AiMessageRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AiMessageRepository::class)]
#[ORM\Table(name: 'ai_messages')]
#[ORM\Index(name: 'idx_ai_msg_conversation', columns: ['conversation_id'])]
#[ORM\Index(name: 'idx_ai_msg_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_ai_msg_role', columns: ['role'])]
class AiMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['ai_msg:read', 'ai_conv:detail'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: AiConversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AiConversation $conversation;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['user', 'assistant', 'system'])]
    #[Groups(['ai_msg:read', 'ai_conv:detail'])]
    private string $role;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Groups(['ai_msg:read', 'ai_conv:detail'])]
    private string $content;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['ai_msg:read', 'ai_conv:detail'])]
    private ?string $model = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['ai_msg:read', 'ai_conv:detail'])]
    private ?string $agentType = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['ai_msg:read', 'ai_conv:detail'])]
    private ?int $tokensUsed = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['ai_msg:read', 'ai_conv:detail'])]
    private ?array $metadata = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['ai_msg:read', 'ai_conv:detail'])]
    private ?DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getConversation(): AiConversation
    {
        return $this->conversation;
    }

    public function setConversation(AiConversation $conversation): static
    {
        $this->conversation = $conversation;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getAgentType(): ?string
    {
        return $this->agentType;
    }

    public function setAgentType(?string $agentType): static
    {
        $this->agentType = $agentType;

        return $this;
    }

    public function getTokensUsed(): ?int
    {
        return $this->tokensUsed;
    }

    public function setTokensUsed(?int $tokensUsed): static
    {
        $this->tokensUsed = $tokensUsed;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }
}
