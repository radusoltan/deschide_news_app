<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Repository\AiConversationRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AiConversationRepository::class)]
#[ORM\Table(name: 'ai_conversations')]
#[ORM\Index(name: 'idx_ai_conv_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_ai_conv_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_ai_conv_status', columns: ['status'])]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['ai_conv:read']],
            paginationItemsPerPage: 20,
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true,
            security: "is_granted('ROLE_USER')",
        ),
        new Get(
            normalizationContext: ['groups' => ['ai_conv:read', 'ai_conv:detail']],
            security: "is_granted('ROLE_USER')",
        ),
        new Patch(
            denormalizationContext: ['groups' => ['ai_conv:write']],
            normalizationContext: ['groups' => ['ai_conv:read']],
            security: "is_granted('ROLE_USER')",
        ),
    ],
    order: ['createdAt' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['status' => 'exact', 'agentType' => 'exact'])]
class AiConversation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['ai_conv:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['ai_conv:read'])]
    private User $user;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['ai_conv:read', 'ai_conv:write'])]
    private ?string $title = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['vault', 'content', 'translation', 'briefing'])]
    #[Groups(['ai_conv:read'])]
    private ?string $agentType = null;

    #[ORM\Column(length: 20, options: ['default' => 'active'])]
    #[Assert\Choice(choices: ['active', 'archived'])]
    #[Groups(['ai_conv:read', 'ai_conv:write'])]
    private string $status = 'active';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['ai_conv:read'])]
    private int $messageCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['ai_conv:read'])]
    private int $totalTokensUsed = 0;

    /** @var Collection<int, AiMessage> */
    #[ORM\OneToMany(targetEntity: AiMessage::class, mappedBy: 'conversation', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    #[Groups(['ai_conv:detail'])]
    private Collection $messages;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['ai_conv:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['ai_conv:read'])]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->messages = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
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

    public function getAgentType(): ?string
    {
        return $this->agentType;
    }

    public function setAgentType(?string $agentType): static
    {
        $this->agentType = $agentType;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getMessageCount(): int
    {
        return $this->messageCount;
    }

    public function setMessageCount(int $messageCount): static
    {
        $this->messageCount = $messageCount;

        return $this;
    }

    public function incrementMessageCount(): static
    {
        ++$this->messageCount;

        return $this;
    }

    public function getTotalTokensUsed(): int
    {
        return $this->totalTokensUsed;
    }

    public function setTotalTokensUsed(int $totalTokensUsed): static
    {
        $this->totalTokensUsed = $totalTokensUsed;

        return $this;
    }

    public function addTokensUsed(int $tokens): static
    {
        $this->totalTokensUsed += $tokens;

        return $this;
    }

    /**
     * @return Collection<int, AiMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(AiMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
        }

        return $this;
    }

    public function removeMessage(AiMessage $message): static
    {
        $this->messages->removeElement($message);

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
}
