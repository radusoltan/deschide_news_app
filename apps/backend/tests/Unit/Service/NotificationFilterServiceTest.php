<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\UserRepository;
use App\Service\NotificationFilterService;
use PHPUnit\Framework\TestCase;

class NotificationFilterServiceTest extends TestCase
{
    private UserRepository $userRepository;
    private NotificationFilterService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->service = new NotificationFilterService($this->userRepository);
    }

    public function testGetRecipientsForArticlePublishedQueriesEditorAndAdmin(): void
    {
        $editor = $this->createStub(User::class);
        $admin = $this->createStub(User::class);

        $this->userRepository->method('findByRoles')->willReturn([$editor, $admin]);

        $recipients = $this->service->getRecipients(NotificationType::ARTICLE_PUBLISHED);

        $this->assertCount(2, $recipients);
    }

    public function testGetRecipientsForArticleUpdatedQueriesEditorAndAdmin(): void
    {
        $this->userRepository->method('findByRoles')->willReturn([]);

        $recipients = $this->service->getRecipients(NotificationType::ARTICLE_UPDATED);

        $this->assertIsArray($recipients);
    }

    public function testGetRecipientsForUserLoginQueriesAdminOnly(): void
    {
        $admin = $this->createStub(User::class);
        $this->userRepository->method('findByRoles')->willReturn([$admin]);

        $recipients = $this->service->getRecipients(NotificationType::USER_LOGIN);

        $this->assertCount(1, $recipients);
    }

    public function testGetRecipientsForUserActionQueriesAdminOnly(): void
    {
        $this->userRepository->method('findByRoles')->willReturn([]);

        $recipients = $this->service->getRecipients(NotificationType::USER_ACTION);

        $this->assertIsArray($recipients);
    }

    public function testGetRecipientsForSystemErrorQueriesAdminOnly(): void
    {
        $this->userRepository->method('findByRoles')->willReturn([]);

        $recipients = $this->service->getRecipients(NotificationType::SYSTEM_ERROR);

        $this->assertIsArray($recipients);
    }

    public function testGetRecipientsForJobFailedQueriesAdminOnly(): void
    {
        $this->userRepository->method('findByRoles')->willReturn([]);

        $recipients = $this->service->getRecipients(NotificationType::JOB_FAILED);

        $this->assertIsArray($recipients);
    }

    public function testGetRecipientsReturnsEmptyArrayWhenNoUsersMatchRoles(): void
    {
        $this->userRepository->method('findByRoles')->willReturn([]);

        $recipients = $this->service->getRecipients(NotificationType::ARTICLE_PUBLISHED);

        $this->assertEmpty($recipients);
    }

    public function testGetRecipientsReturnsMultipleUsers(): void
    {
        $users = [
            $this->createStub(User::class),
            $this->createStub(User::class),
            $this->createStub(User::class),
        ];
        $this->userRepository->method('findByRoles')->willReturn($users);

        $recipients = $this->service->getRecipients(NotificationType::SYSTEM_ERROR);

        $this->assertCount(3, $recipients);
    }
}
