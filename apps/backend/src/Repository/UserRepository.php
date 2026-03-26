<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Find users that have any of the specified roles.
     * Uses native SQL because PostgreSQL JSON columns don't support LIKE in DQL.
     *
     * @param string[] $roles
     * @return User[]
     */
    public function findByRoles(array $roles): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $conditions = [];
        $params = [];
        foreach ($roles as $i => $role) {
            $conditions[] = \sprintf('CAST(u.roles AS TEXT) LIKE :role_%d', $i);
            $params[\sprintf('role_%d', $i)] = \sprintf('%%"%s"%%', $role);
        }

        $sql = \sprintf(
            'SELECT u.id FROM "user" u WHERE u.is_active = true AND (%s)',
            implode(' OR ', $conditions),
        );

        $result = $conn->executeQuery($sql, $params);
        $ids = array_column($result->fetchAllAssociative(), 'id');

        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
