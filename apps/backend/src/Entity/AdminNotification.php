<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use App\Repository\AdminNotificationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AdminNotificationRepository::class)]
#[ORM\Table(name: 'admin_notifications')]
#[ORM\Index(name: 'idx_notif_recipient_unread', columns: ['recipient_user_id', 'is_read', 'created_at'])]
#[ORM\Index(name: 'idx_notif_type_created', columns: ['type', 'created_at'])]
#[ORM\Index(name: 'idx_notif_cleanup', columns: ['created_at', 'is_read'])]
class AdminNotification
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['notification:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipientUser;

    #[ORM\Column(length: 30, enumType: NotificationType::class)]
    #[Groups(['notification:read'])]
    private NotificationType $type;

    #[ORM\Column(length: 10, enumType: NotificationImportance::class)]
    #[Groups(['notification:read'])]
    private NotificationImportance $importance = NotificationImportance::MEDIUM;

    #[ORM\Column(length: 255)]
    #[Groups(['notification:read'])]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['notification:read'])]
    private ?string $message = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['notification:read'])]
    private ?string $relatedEntityType = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['notification:read'])]
    private ?int $relatedEntityId = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['notification:read'])]
    private ?string $actionUrl = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['notification:read'])]
    private bool $isRead = false;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['notification:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['notification:read'])]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRecipientUser(): User
    {
        return $this->recipientUser;
    }

    public function setRecipientUser(User $recipientUser): static
    {
        $this->recipientUser = $recipientUser;

        return $this;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function setType(NotificationType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getImportance(): NotificationImportance
    {
        return $this->importance;
    }

    public function setImportance(NotificationImportance $importance): static
    {
        $this->importance = $importance;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getRelatedEntityType(): ?string
    {
        return $this->relatedEntityType;
    }

    public function setRelatedEntityType(?string $relatedEntityType): static
    {
        $this->relatedEntityType = $relatedEntityType;

        return $this;
    }

    public function getRelatedEntityId(): ?int
    {
        return $this->relatedEntityId;
    }

    public function setRelatedEntityId(?int $relatedEntityId): static
    {
        $this->relatedEntityId = $relatedEntityId;

        return $this;
    }

    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }

    public function setActionUrl(?string $actionUrl): static
    {
        $this->actionUrl = $actionUrl;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function markAsRead(): static
    {
        $this->isRead = true;
        $this->readAt = new \DateTimeImmutable();

        return $this;
    }
}
