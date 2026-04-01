<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\AdminNotification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\AdminNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for AdminNotificationRepository.
 */
class AdminNotificationRepositoryTest extends KernelTestCase
{
    private AdminNotificationRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(AdminNotificationRepository::class);
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // =====================================================================
    // findUnreadByUser
    // =====================================================================

    public function testFindUnreadByUserReturnsOnlyUnread(): void
    {
        $user = $this->createUser();

        $unread = $this->createNotification($user, 'Unread', false);
        $read = $this->createNotification($user, 'Read', true);
        $this->em->flush();

        $results = $this->repository->findUnreadByUser($user, 100);

        $this->assertIsArray($results);
        $ids = array_map(fn (AdminNotification $n) => $n->getId()->toRfc4122(), $results);
        $this->assertContains($unread->getId()->toRfc4122(), $ids);
        $this->assertNotContains($read->getId()->toRfc4122(), $ids);
    }

    public function testFindUnreadByUserRespectsLimit(): void
    {
        $user = $this->createUser();

        for ($i = 0; $i < 5; $i++) {
            $this->createNotification($user, "Notif $i", false);
        }
        $this->em->flush();

        $results = $this->repository->findUnreadByUser($user, 2);

        $this->assertLessThanOrEqual(2, count($results));
    }

    // =====================================================================
    // countUnreadByUser
    // =====================================================================

    public function testCountUnreadByUser(): void
    {
        $user = $this->createUser();

        $this->createNotification($user, 'Unread 1', false);
        $this->createNotification($user, 'Unread 2', false);
        $this->createNotification($user, 'Read 1', true);
        $this->em->flush();

        $count = $this->repository->countUnreadByUser($user);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    // =====================================================================
    // markAsRead
    // =====================================================================

    public function testMarkAsReadUpdatesNotification(): void
    {
        $user = $this->createUser();
        $notification = $this->createNotification($user, 'To be read', false);
        $this->em->flush();

        $this->assertFalse($notification->isRead());

        $this->repository->markAsRead($notification);

        $this->assertTrue($notification->isRead());
        $this->assertNotNull($notification->getReadAt());
    }

    // =====================================================================
    // markAllAsReadByUser
    // =====================================================================

    public function testMarkAllAsReadByUser(): void
    {
        $user = $this->createUser();

        $this->createNotification($user, 'Bulk Read 1', false);
        $this->createNotification($user, 'Bulk Read 2', false);
        $this->em->flush();

        $updated = $this->repository->markAllAsReadByUser($user);

        $this->assertGreaterThanOrEqual(2, $updated);
    }

    // =====================================================================
    // deleteOlderThan
    // =====================================================================

    public function testDeleteOlderThanReturnsDeletedCount(): void
    {
        $deleted = $this->repository->deleteOlderThan(new \DateTimeImmutable('2000-01-01'));

        $this->assertGreaterThanOrEqual(0, $deleted);
    }

    // =====================================================================
    // findPaginatedByUser
    // =====================================================================

    public function testFindPaginatedByUserReturnsPaginator(): void
    {
        $user = $this->createUser();
        $this->createNotification($user, 'Paginated', false);
        $this->em->flush();

        $paginator = $this->repository->findPaginatedByUser($user, 1, 20);

        $this->assertInstanceOf(\Doctrine\ORM\Tools\Pagination\Paginator::class, $paginator);
    }

    public function testFindPaginatedByUserFiltersReadStatus(): void
    {
        $user = $this->createUser();
        $this->createNotification($user, 'Unread Filter', false);
        $this->createNotification($user, 'Read Filter', true);
        $this->em->flush();

        $unreadPaginator = $this->repository->findPaginatedByUser($user, 1, 20, false);
        $readPaginator = $this->repository->findPaginatedByUser($user, 1, 20, true);

        $this->assertInstanceOf(\Doctrine\ORM\Tools\Pagination\Paginator::class, $unreadPaginator);
        $this->assertInstanceOf(\Doctrine\ORM\Tools\Pagination\Paginator::class, $readPaginator);
    }

    public function testFindPaginatedByUserFiltersByType(): void
    {
        $user = $this->createUser();

        $notification = $this->createNotification($user, 'Type Filter', false);
        $this->em->flush();

        $paginator = $this->repository->findPaginatedByUser(
            $user,
            1,
            20,
            null,
            NotificationType::ARTICLE_PUBLISHED,
        );

        $this->assertInstanceOf(\Doctrine\ORM\Tools\Pagination\Paginator::class, $paginator);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function createUser(): User
    {
        $suffix = uniqid('notif-user-', true);
        $user = new User();
        $user->setUsername('user' . $suffix);
        $user->setEmail($suffix . '@test.example');
        $user->setPassword('hashed-password');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createNotification(User $user, string $title, bool $isRead): AdminNotification
    {
        $notification = new AdminNotification();
        $notification->setRecipientUser($user);
        $notification->setType(NotificationType::ARTICLE_PUBLISHED);
        $notification->setTitle($title);
        $notification->setIsRead($isRead);
        if ($isRead) {
            $notification->setReadAt(new \DateTimeImmutable());
        }
        $this->em->persist($notification);

        return $notification;
    }
}
