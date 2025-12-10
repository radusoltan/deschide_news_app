<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\LiveTextCollaboratorRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LiveTextCollaboratorRepository::class)]
#[ORM\Table(name: 'live_text_collaborators')]
#[ORM\UniqueConstraint(name: 'unique_live_text_user', columns: ['live_text_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/live_text_collaborators/{id}',
            normalizationContext: ['groups' => ['collaborator:read'], 'enable_max_depth' => true]
        ),
        new GetCollection(
            uriTemplate: '/live_text_collaborators',
            normalizationContext: ['groups' => ['collaborator:read'], 'enable_max_depth' => true],
            paginationItemsPerPage: 50
        ),
        new Post(
            uriTemplate: '/live_text_collaborators',
            denormalizationContext: ['groups' => ['collaborator:write']]
        ),
        new Delete(
            uriTemplate: '/live_text_collaborators/{id}'
        ),
    ],
    processor: \App\State\LiveTextCollaboratorProcessor::class
)]
class LiveTextCollaborator
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['collaborator:read', 'livetext:read', 'livetext:detail'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => 'contributor'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['editor', 'contributor'], message: 'Role must be either "editor" or "contributor".')]
    #[Groups(['collaborator:read', 'collaborator:write', 'livetext:read', 'livetext:detail'])]
    private string $role = 'contributor';

    // Relationships
    #[ORM\ManyToOne(targetEntity: LiveText::class, inversedBy: 'collaborators')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Live text must be specified.')]
    #[Groups(['collaborator:read', 'collaborator:write'])]
    #[MaxDepth(1)]
    private ?LiveText $liveText = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'User must be specified.')]
    #[Groups(['collaborator:read', 'collaborator:write', 'livetext:read', 'livetext:detail'])]
    #[MaxDepth(1)]
    private ?User $user = null;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['collaborator:read', 'livetext:read'])]
    private ?DateTimeInterface $createdAt = null;

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
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

    public function getLiveText(): ?LiveText
    {
        return $this->liveText;
    }

    public function setLiveText(?LiveText $liveText): static
    {
        $this->liveText = $liveText;

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
