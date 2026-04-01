<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\AdminNotificationController;
use App\Entity\AdminNotification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\AdminNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AdminNotificationControllerTest extends TestCase
{
    private AdminNotificationRepository $repository;
    private EntityManagerInterface $entityManager;
    private AdminNotificationController $controller;
    private TokenStorageInterface $tokenStorage;
    private User $user;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(AdminNotificationRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tokenStorage = $this->createStub(TokenStorageInterface::class);

        $this->user = $this->createStub(User::class);
        $this->user->method('getId')->willReturn(1);

        // Set up user token
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($this->user);
        $this->tokenStorage->method('getToken')->willReturn($token);

        $this->controller = new AdminNotificationController(
            $this->repository,
            $this->entityManager,
        );

        // AbstractController needs a container for json() and getUser()
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['serializer', false],
            ['security.token_storage', true],
            ['twig', false],
        ]);
        $container->method('get')->willReturnMap([
            ['security.token_storage', $this->tokenStorage],
        ]);
        $this->controller->setContainer($container);
    }

    // ========================
    // list Tests
    // ========================

    #[Test]
    public function listReturnsNotificationsWithDefaults(): void
    {
        $paginator = $this->createStub(Paginator::class);
        $paginator->method('count')->willReturn(0);
        $paginator->method('getIterator')->willReturn(new \ArrayIterator([]));

        $this->repository->method('findPaginatedByUser')
            ->with($this->user, 1, 20, null, null)
            ->willReturn($paginator);

        $request = new Request();

        $response = $this->controller->list($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame([], $data['items']);
        $this->assertSame(0, $data['totalItems']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(20, $data['limit']);
    }

    #[Test]
    public function listRespectsPaginationParams(): void
    {
        $paginator = $this->createStub(Paginator::class);
        $paginator->method('count')->willReturn(5);
        $paginator->method('getIterator')->willReturn(new \ArrayIterator([]));

        $this->repository->method('findPaginatedByUser')
            ->with($this->user, 3, 50, null, null)
            ->willReturn($paginator);

        $request = new Request(query: ['page' => '3', 'limit' => '50']);

        $response = $this->controller->list($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(3, $data['page']);
        $this->assertSame(50, $data['limit']);
    }

    #[Test]
    public function listClampsPaginationValues(): void
    {
        $paginator = $this->createStub(Paginator::class);
        $paginator->method('count')->willReturn(0);
        $paginator->method('getIterator')->willReturn(new \ArrayIterator([]));

        // page=-5 should clamp to 1, limit=999 should clamp to 100
        $this->repository->method('findPaginatedByUser')
            ->with($this->user, 1, 100, null, null)
            ->willReturn($paginator);

        $request = new Request(query: ['page' => '-5', 'limit' => '999']);

        $response = $this->controller->list($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['page']);
        $this->assertSame(100, $data['limit']);
    }

    #[Test]
    public function listFiltersOnIsReadFalse(): void
    {
        $paginator = $this->createStub(Paginator::class);
        $paginator->method('count')->willReturn(0);
        $paginator->method('getIterator')->willReturn(new \ArrayIterator([]));

        $this->repository->method('findPaginatedByUser')
            ->with($this->user, 1, 20, false, null)
            ->willReturn($paginator);

        $request = new Request(query: ['isRead' => 'false']);

        $response = $this->controller->list($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function listFiltersOnIsReadTrue(): void
    {
        $paginator = $this->createStub(Paginator::class);
        $paginator->method('count')->willReturn(0);
        $paginator->method('getIterator')->willReturn(new \ArrayIterator([]));

        $this->repository->method('findPaginatedByUser')
            ->with($this->user, 1, 20, true, null)
            ->willReturn($paginator);

        $request = new Request(query: ['isRead' => 'true']);

        $response = $this->controller->list($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function listFiltersOnType(): void
    {
        $paginator = $this->createStub(Paginator::class);
        $paginator->method('count')->willReturn(0);
        $paginator->method('getIterator')->willReturn(new \ArrayIterator([]));

        $this->repository->method('findPaginatedByUser')
            ->with($this->user, 1, 20, null, NotificationType::ARTICLE_PUBLISHED)
            ->willReturn($paginator);

        $request = new Request(query: ['type' => 'article_published']);

        $response = $this->controller->list($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function listHandlesInvalidTypeGracefully(): void
    {
        $paginator = $this->createStub(Paginator::class);
        $paginator->method('count')->willReturn(0);
        $paginator->method('getIterator')->willReturn(new \ArrayIterator([]));

        // Invalid type => tryFrom returns null
        $this->repository->method('findPaginatedByUser')
            ->with($this->user, 1, 20, null, null)
            ->willReturn($paginator);

        $request = new Request(query: ['type' => 'nonexistent_type']);

        $response = $this->controller->list($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function listClampsLimitMinimum(): void
    {
        $paginator = $this->createStub(Paginator::class);
        $paginator->method('count')->willReturn(0);
        $paginator->method('getIterator')->willReturn(new \ArrayIterator([]));

        // limit=0 should clamp to 1
        $this->repository->method('findPaginatedByUser')
            ->with($this->user, 1, 1, null, null)
            ->willReturn($paginator);

        $request = new Request(query: ['limit' => '0']);

        $response = $this->controller->list($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['limit']);
    }

    // ========================
    // unreadCount Tests
    // ========================

    #[Test]
    public function unreadCountReturnsCount(): void
    {
        $this->repository->method('countUnreadByUser')->with($this->user)->willReturn(7);

        $response = $this->controller->unreadCount();

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame(7, $data['count']);
    }

    #[Test]
    public function unreadCountReturnsZeroWhenNoUnread(): void
    {
        $this->repository->method('countUnreadByUser')->with($this->user)->willReturn(0);

        $response = $this->controller->unreadCount();

        $data = json_decode($response->getContent(), true);
        $this->assertSame(0, $data['count']);
    }

    // ========================
    // markAsRead Tests
    // ========================

    #[Test]
    public function markAsReadReturnsBadRequestForInvalidUuid(): void
    {
        $response = $this->controller->markAsRead('not-a-uuid');

        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid notification ID', $data['error']);
    }

    #[Test]
    public function markAsReadReturns404WhenNotificationNotFound(): void
    {
        $uuid = Uuid::v7();
        $this->repository->method('find')->willReturn(null);

        $response = $this->controller->markAsRead((string) $uuid);

        $this->assertSame(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Notification not found', $data['error']);
    }

    #[Test]
    public function markAsReadReturns404WhenNotificationBelongsToAnotherUser(): void
    {
        $uuid = Uuid::v7();

        $otherUser = $this->createStub(User::class);
        $otherUser->method('getId')->willReturn(999);

        $notification = $this->createStub(AdminNotification::class);
        $notification->method('getRecipientUser')->willReturn($otherUser);

        $this->repository->method('find')->willReturn($notification);

        $response = $this->controller->markAsRead((string) $uuid);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function markAsReadSuccessfully(): void
    {
        $uuid = Uuid::v7();

        $notification = $this->createMock(AdminNotification::class);
        $notification->method('getRecipientUser')->willReturn($this->user);
        $notification->expects($this->once())->method('markAsRead');

        $this->repository->method('find')->willReturn($notification);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->markAsRead((string) $uuid);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================
    // markAllRead Tests
    // ========================

    #[Test]
    public function markAllReadReturnsUpdatedCount(): void
    {
        $this->repository->method('markAllAsReadByUser')->with($this->user)->willReturn(5);

        $response = $this->controller->markAllRead();

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame(5, $data['updated']);
    }

    #[Test]
    public function markAllReadReturnsZeroWhenNothingToUpdate(): void
    {
        $this->repository->method('markAllAsReadByUser')->with($this->user)->willReturn(0);

        $response = $this->controller->markAllRead();

        $data = json_decode($response->getContent(), true);
        $this->assertSame(0, $data['updated']);
    }

    // ========================
    // delete Tests
    // ========================

    #[Test]
    public function deleteReturnsBadRequestForInvalidUuid(): void
    {
        $response = $this->controller->delete('invalid-uuid');

        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid notification ID', $data['error']);
    }

    #[Test]
    public function deleteReturns404WhenNotificationNotFound(): void
    {
        $uuid = Uuid::v7();
        $this->repository->method('find')->willReturn(null);

        $response = $this->controller->delete((string) $uuid);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function deleteReturns404WhenNotificationBelongsToAnotherUser(): void
    {
        $uuid = Uuid::v7();

        $otherUser = $this->createStub(User::class);
        $otherUser->method('getId')->willReturn(999);

        $notification = $this->createStub(AdminNotification::class);
        $notification->method('getRecipientUser')->willReturn($otherUser);

        $this->repository->method('find')->willReturn($notification);

        $response = $this->controller->delete((string) $uuid);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function deleteRemovesNotificationSuccessfully(): void
    {
        $uuid = Uuid::v7();

        $notification = $this->createStub(AdminNotification::class);
        $notification->method('getRecipientUser')->willReturn($this->user);

        $this->repository->method('find')->willReturn($notification);
        $this->entityManager->expects($this->once())->method('remove')->with($notification);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->delete((string) $uuid);

        $this->assertSame(204, $response->getStatusCode());
    }
}
