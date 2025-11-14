<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use App\Entity\ArticleLock;
use App\Repository\ArticleLockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<ArticleLock>
 */
final class ArticleLockProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArticleLockRepository $lockRepository,
        private readonly Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        $articleId = $uriVariables['articleId'] ?? null;
        if (!$articleId) {
            throw new BadRequestException('Article ID required');
        }

        $article = $this->entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new NotFoundHttpException('Article not found');
        }

        $operationName = $operation->getName();

        // Handle lock acquisition
        if (str_contains($operationName, 'lock') && $operation->getMethod() === 'POST') {
            return $this->acquireLock($article, $user);
        }

        // Handle heartbeat
        if (str_contains($operationName, 'heartbeat')) {
            return $this->refreshLock($article, $user);
        }

        // Handle lock release
        if ($operation->getMethod() === 'DELETE') {
            return $this->releaseLock($article, $user);
        }

        throw new BadRequestException('Invalid operation');
    }

    private function acquireLock(Article $article, $user): ArticleLock
    {
        // Check if article is already locked by someone else
        $existingLock = $this->lockRepository->findActiveLockForArticle($article);

        if ($existingLock) {
            // If locked by current user, just refresh it
            if ($existingLock->getLockedBy()->getId() === $user->getId()) {
                $existingLock->refreshExpiration();
                $this->entityManager->flush();

                return $existingLock;
            }

            // Locked by someone else
            throw new ConflictHttpException(\sprintf('Article is currently being edited by %s %s', $existingLock->getLockedBy()->getFirstName(), $existingLock->getLockedBy()->getLastName()));
        }

        // Create new lock
        $lock = new ArticleLock();
        $lock->setArticle($article);
        $lock->setLockedBy($user);

        $this->entityManager->persist($lock);
        $this->entityManager->flush();

        return $lock;
    }

    private function refreshLock(Article $article, $user): ArticleLock
    {
        $lock = $this->lockRepository->findLockForArticleAndUser($article, $user);

        if (!$lock) {
            throw new NotFoundHttpException('No active lock found for this article');
        }

        $lock->refreshExpiration();
        $this->entityManager->flush();

        return $lock;
    }

    private function releaseLock(Article $article, $user): null
    {
        $released = $this->lockRepository->releaseLock($article, $user);

        if (!$released) {
            // Lock might have expired or didn't exist, but that's OK
            // We just want to ensure it's released
        }

        $this->entityManager->flush();

        return null;
    }
}
