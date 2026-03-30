<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdminNotification;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminNotification>
 */
class AdminNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminNotification::class);
    }

    /**
     * @return AdminNotification[]
     */
    public function findUnreadByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.recipientUser = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipientUser = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAsRead(AdminNotification $notification): void
    {
        $notification->markAsRead();
        $this->getEntityManager()->flush();
    }

    /**
     * @return int Number of notifications marked as read
     */
    public function markAllAsReadByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->set('n.readAt', ':now')
            ->where('n.recipientUser = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * @return int Number of notifications deleted
     */
    public function deleteOlderThan(\DateTimeImmutable $cutoff): int
    {
        return (int) $this->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }

    /**
     * @return Paginator<AdminNotification>
     */
    public function findPaginatedByUser(
        User $user,
        int $page = 1,
        int $limit = 20,
        ?bool $isRead = null,
        ?NotificationType $type = null,
    ): Paginator {
        $qb = $this->createQueryBuilder('n')
            ->where('n.recipientUser = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC');

        if ($isRead !== null) {
            $qb->andWhere('n.isRead = :isRead')
                ->setParameter('isRead', $isRead);
        }

        if ($type !== null) {
            $qb->andWhere('n.type = :type')
                ->setParameter('type', $type);
        }

        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($qb->getQuery());
    }
}
