<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\UserRepository;

readonly class NotificationFilterService
{
    private const array TYPE_ROLE_MAP = [
        'article_published' => ['ROLE_EDITOR', 'ROLE_ADMIN'],
        'article_updated' => ['ROLE_EDITOR', 'ROLE_ADMIN'],
        'user_login' => ['ROLE_ADMIN'],
        'user_action' => ['ROLE_ADMIN'],
        'system_error' => ['ROLE_ADMIN'],
        'job_failed' => ['ROLE_ADMIN'],
        'article_auto_created' => ['ROLE_EDITOR', 'ROLE_ADMIN'],
        'article_translated' => ['ROLE_EDITOR', 'ROLE_ADMIN'],
    ];

    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @return User[]
     */
    public function getRecipients(NotificationType $type): array
    {
        $roles = self::TYPE_ROLE_MAP[$type->value] ?? ['ROLE_SUPER_ADMIN'];

        return $this->userRepository->findByRoles($roles);
    }
}
