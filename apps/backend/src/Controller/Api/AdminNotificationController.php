<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\AdminNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/admin/notifications')]
#[IsGranted(new Expression("is_granted('ROLE_EDITOR') or is_granted('ROLE_ADMIN')"))]
final class AdminNotificationController extends AbstractController
{
    public function __construct(
        private readonly AdminNotificationRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));

        $isRead = $request->query->has('isRead')
            ? filter_var($request->query->get('isRead'), \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE)
            : null;

        $typeValue = $request->query->get('type');
        $type = $typeValue ? NotificationType::tryFrom($typeValue) : null;

        $paginator = $this->repository->findPaginatedByUser($user, $page, $limit, $isRead, $type);
        $totalItems = \count($paginator);

        $items = [];
        foreach ($paginator as $notification) {
            $items[] = $notification;
        }

        return $this->json([
            'items' => $items,
            'totalItems' => $totalItems,
            'page' => $page,
            'limit' => $limit,
        ], context: ['groups' => ['notification:read']]);
    }

    #[Route('/unread-count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'count' => $this->repository->countUnreadByUser($user),
        ]);
    }

    #[Route('/{id}/read', methods: ['PATCH'])]
    public function markAsRead(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->json(['error' => 'Invalid notification ID'], Response::HTTP_BAD_REQUEST);
        }

        $notification = $this->repository->find(Uuid::fromString($id));

        if (!$notification) {
            return $this->json(['error' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        if ($notification->getRecipientUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        $notification->markAsRead();
        $this->entityManager->flush();

        return $this->json($notification, context: ['groups' => ['notification:read']]);
    }

    #[Route('/mark-all-read', methods: ['POST'])]
    public function markAllRead(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $updated = $this->repository->markAllAsReadByUser($user);

        return $this->json(['updated' => $updated]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->json(['error' => 'Invalid notification ID'], Response::HTTP_BAD_REQUEST);
        }

        $notification = $this->repository->find(Uuid::fromString($id));

        if (!$notification) {
            return $this->json(['error' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        if ($notification->getRecipientUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($notification);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
