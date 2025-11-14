<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\ReactionType;
use App\Repository\LiveTextReactionRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LiveTextReactionRepository::class)]
#[ORM\Table(name: 'live_text_reactions')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_reaction_post', columns: ['live_text_post_id'])]
#[ORM\Index(name: 'idx_reaction_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_reaction_ip', columns: ['ip_address'])]
#[ORM\Index(name: 'idx_reaction_type', columns: ['reaction_type'])]
#[ORM\UniqueConstraint(name: 'unique_user_post_reaction', columns: ['live_text_post_id', 'user_id'])]
#[ORM\UniqueConstraint(name: 'unique_ip_post_reaction', columns: ['live_text_post_id', 'ip_address'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/live_text_reactions/{id}',
            normalizationContext: ['groups' => ['reaction:read']]
        ),
        new GetCollection(
            uriTemplate: '/live_text_reactions',
            normalizationContext: ['groups' => ['reaction:read', 'reaction:list']],
            paginationItemsPerPage: 50
        ),
        new Post(
            uriTemplate: '/live_text_reactions',
            denormalizationContext: ['groups' => ['reaction:write']]
        ),
        new Delete(
            uriTemplate: '/live_text_reactions/{id}'
        ),
    ],
    provider: \App\State\LiveTextReactionProvider::class,
    processor: \App\State\LiveTextReactionProcessor::class
)]
class LiveTextReaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['reaction:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LiveTextPost::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Live text post must be specified.')]
    #[Groups(['reaction:read', 'reaction:write'])]
    private ?LiveTextPost $liveTextPost = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['reaction:read', 'reaction:write'])]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ReactionType::class)]
    #[Assert\NotBlank]
    #[Groups(['reaction:read', 'reaction:write'])]
    private ReactionType $reactionType;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    #[Groups(['reaction:read'])]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['reaction:read'])]
    private ?string $userAgent = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['reaction:read'])]
    private ?DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLiveTextPost(): ?LiveTextPost
    {
        return $this->liveTextPost;
    }

    public function setLiveTextPost(?LiveTextPost $liveTextPost): static
    {
        $this->liveTextPost = $liveTextPost;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getReactionType(): ReactionType
    {
        return $this->reactionType;
    }

    public function setReactionType(ReactionType $reactionType): static
    {
        $this->reactionType = $reactionType;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

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
}
