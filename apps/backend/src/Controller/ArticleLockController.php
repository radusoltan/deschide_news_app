<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use App\Entity\ArticleLock;
use App\Repository\ArticleLockRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/api')]
class ArticleLockController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArticleLockRepository $lockRepository
    ) {
    }

    #[Route('/articles/locks/active', name: 'article_locks_active', methods: ['GET'])]
    public function getActiveLocks(): JsonResponse
    {
        // Get all active locks with their articles and users
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('al', 'a', 'u')
            ->from(ArticleLock::class, 'al')
            ->join('al.article', 'a')
            ->join('al.lockedBy', 'u')
            ->where('al.expiresAt > :now')
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('al.lockedAt', 'DESC');

        $locks = $qb->getQuery()->getResult();

        $result = [];
        foreach ($locks as $lock) {
            $result[] = [
                'articleId' => $lock->getArticle()->getId(),
                'articleTitle' => $lock->getArticle()->getTitle(),
                'lockedBy' => [
                    'id' => $lock->getLockedBy()->getId(),
                    'firstName' => $lock->getLockedBy()->getFirstName(),
                    'lastName' => $lock->getLockedBy()->getLastName(),
                    'email' => $lock->getLockedBy()->getEmail(),
                ],
                'lockedAt' => $lock->getLockedAt()->format('c'),
                'expiresAt' => $lock->getExpiresAt()->format('c'),
            ];
        }

        return $this->json($result);
    }

    #[Route('/articles/{id}/lock/check', name: 'article_lock_check', methods: ['GET'])]
    public function checkLock(int $id): JsonResponse
    {
        $article = $this->entityManager->getRepository(Article::class)->find($id);

        if (!$article) {
            return $this->json(['error' => 'Article not found'], 404);
        }

        $lock = $this->lockRepository->findActiveLockForArticle($article);

        if (!$lock) {
            return $this->json(['locked' => false]);
        }

        $currentUser = $this->getUser();
        $isLockedByCurrentUser = $currentUser && $lock->getLockedBy()->getId() === $currentUser->getId();

        return $this->json([
            'locked' => true,
            'lockedBy' => [
                'id' => $lock->getLockedBy()->getId(),
                'firstName' => $lock->getLockedBy()->getFirstName(),
                'lastName' => $lock->getLockedBy()->getLastName(),
                'email' => $lock->getLockedBy()->getEmail(),
            ],
            'lockedAt' => $lock->getLockedAt()->format('c'),
            'expiresAt' => $lock->getExpiresAt()->format('c'),
            'isLockedByCurrentUser' => $isLockedByCurrentUser,
        ]);
    }

    #[Route('/articles/{id}/lock', name: 'article_lock_acquire', methods: ['POST'])]
    public function acquireLock(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        $article = $this->entityManager->getRepository(Article::class)->find($id);
        if (!$article) {
            return $this->json(['error' => 'Article not found'], 404);
        }

        // Check if article is already locked by someone else
        $existingLock = $this->lockRepository->findActiveLockForArticle($article);

        if ($existingLock) {
            // If locked by current user, just refresh it
            if ($existingLock->getLockedBy()->getId() === $user->getId()) {
                $existingLock->refreshExpiration();
                $this->entityManager->flush();

                return $this->json([
                    'id' => $existingLock->getId(),
                    'lockedAt' => $existingLock->getLockedAt()->format('c'),
                    'expiresAt' => $existingLock->getExpiresAt()->format('c'),
                ]);
            }

            // Locked by someone else
            return $this->json([
                'error' => \sprintf(
                    'Article is currently being edited by %s %s',
                    $existingLock->getLockedBy()->getFirstName(),
                    $existingLock->getLockedBy()->getLastName()
                ),
                'lockedBy' => [
                    'id' => $existingLock->getLockedBy()->getId(),
                    'firstName' => $existingLock->getLockedBy()->getFirstName(),
                    'lastName' => $existingLock->getLockedBy()->getLastName(),
                ],
            ], 409);
        }

        // Create new lock
        $lock = new ArticleLock();
        $lock->setArticle($article);
        $lock->setLockedBy($user);

        $this->entityManager->persist($lock);
        $this->entityManager->flush();

        return $this->json([
            'id' => $lock->getId(),
            'lockedAt' => $lock->getLockedAt()->format('c'),
            'expiresAt' => $lock->getExpiresAt()->format('c'),
        ], 201);
    }

    #[Route('/articles/{id}/lock/heartbeat', name: 'article_lock_heartbeat', methods: ['POST'])]
    public function heartbeat(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        $article = $this->entityManager->getRepository(Article::class)->find($id);
        if (!$article) {
            return $this->json(['error' => 'Article not found'], 404);
        }

        $lock = $this->lockRepository->findLockForArticleAndUser($article, $user);

        if (!$lock) {
            return $this->json(['error' => 'No active lock found'], 404);
        }

        $lock->refreshExpiration();
        $this->entityManager->flush();

        return $this->json([
            'id' => $lock->getId(),
            'lockedAt' => $lock->getLockedAt()->format('c'),
            'expiresAt' => $lock->getExpiresAt()->format('c'),
        ]);
    }

    #[Route('/articles/{id}/lock', name: 'article_lock_release', methods: ['DELETE'])]
    public function releaseLock(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        $article = $this->entityManager->getRepository(Article::class)->find($id);
        if (!$article) {
            return $this->json(['error' => 'Article not found'], 404);
        }

        $released = $this->lockRepository->releaseLock($article, $user);
        $this->entityManager->flush();

        return new Response('', 204);
    }
}
