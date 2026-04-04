<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use App\Entity\Article;
use App\Entity\ArticleLock;
use App\Entity\User;
use App\Repository\ArticleLockRepository;
use App\State\ArticleLockProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ArticleLockProcessorTest extends TestCase
{
    private ArticleLockProcessor $processor;
    private EntityManagerInterface $entityManager;
    private ArticleLockRepository $lockRepository;
    private Security $security;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->lockRepository = $this->createMock(ArticleLockRepository::class);
        $this->security = $this->createStub(Security::class);

        $this->processor = new ArticleLockProcessor(
            $this->entityManager,
            $this->lockRepository,
            $this->security
        );
    }

    // ========================
    // Authentication Tests
    // ========================

    #[Test]
    public function itRequiresAuthentication(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Authentication required');

        $operation = $this->createLockOperation();
        $this->processor->process(null, $operation, ['articleId' => 1]);
    }

    // ========================
    // Article ID Validation Tests
    // ========================

    #[Test]
    public function itRequiresArticleId(): void
    {
        $user = $this->createStub(User::class);
        $this->security->method('getUser')->willReturn($user);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Article ID required');

        $operation = $this->createLockOperation();
        $this->processor->process(null, $operation, []);
    }

    #[Test]
    public function itThrowsNotFoundWhenArticleDoesNotExist(): void
    {
        $user = $this->createStub(User::class);
        $this->security->method('getUser')->willReturn($user);

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(Article::class)
            ->willReturn($articleRepo);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Article not found');

        $operation = $this->createLockOperation();
        $this->processor->process(null, $operation, ['articleId' => 999]);
    }

    // ========================
    // Lock Acquisition Tests
    // ========================

    #[Test]
    public function itCreatesNewLockWhenArticleIsNotLocked(): void
    {
        $user = $this->createStub(User::class);
        $this->security->method('getUser')->willReturn($user);

        $article = $this->createStub(Article::class);
        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(1)->willReturn($article);

        $this->entityManager->method('getRepository')
            ->with(Article::class)
            ->willReturn($articleRepo);

        $this->lockRepository->method('findActiveLockForArticle')
            ->with($article)
            ->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(ArticleLock::class));
        $this->entityManager->expects($this->once())->method('flush');

        $operation = $this->createLockOperation();
        $result = $this->processor->process(null, $operation, ['articleId' => 1]);

        $this->assertInstanceOf(ArticleLock::class, $result);
    }

    #[Test]
    public function itRefreshesExistingLockBySameUser(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $this->security->method('getUser')->willReturn($user);

        $article = $this->createStub(Article::class);
        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(1)->willReturn($article);

        $this->entityManager->method('getRepository')
            ->with(Article::class)
            ->willReturn($articleRepo);

        $existingLock = $this->createMock(ArticleLock::class);
        $lockedByUser = $this->createStub(User::class);
        $lockedByUser->method('getId')->willReturn(1);
        $existingLock->method('getLockedBy')->willReturn($lockedByUser);
        $existingLock->expects($this->once())->method('refreshExpiration');

        $this->lockRepository->method('findActiveLockForArticle')
            ->with($article)
            ->willReturn($existingLock);

        $operation = $this->createLockOperation();
        $result = $this->processor->process(null, $operation, ['articleId' => 1]);

        $this->assertInstanceOf(ArticleLock::class, $result);
    }

    #[Test]
    public function itThrowsConflictWhenLockedByAnotherUser(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $this->security->method('getUser')->willReturn($user);

        $article = $this->createStub(Article::class);
        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(1)->willReturn($article);

        $this->entityManager->method('getRepository')
            ->with(Article::class)
            ->willReturn($articleRepo);

        $otherUser = $this->createStub(User::class);
        $otherUser->method('getId')->willReturn(2);
        $otherUser->method('getFirstName')->willReturn('Jane');
        $otherUser->method('getLastName')->willReturn('Doe');

        $existingLock = $this->createMock(ArticleLock::class);
        $existingLock->method('getLockedBy')->willReturn($otherUser);

        $this->lockRepository->method('findActiveLockForArticle')
            ->with($article)
            ->willReturn($existingLock);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Article is currently being edited by Jane Doe');

        $operation = $this->createLockOperation();
        $this->processor->process(null, $operation, ['articleId' => 1]);
    }

    // ========================
    // Heartbeat Tests
    // ========================

    #[Test]
    public function itRefreshesLockOnHeartbeat(): void
    {
        // Note: The heartbeat operation name '_api_article_lock_heartbeat_post'
        // matches str_contains('lock') && POST, so the acquireLock path is taken.
        // When the existing lock belongs to the current user, acquireLock
        // refreshes the expiration (same effect as the heartbeat path).
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $this->security->method('getUser')->willReturn($user);

        $article = $this->createStub(Article::class);
        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(1)->willReturn($article);

        $this->entityManager->method('getRepository')
            ->with(Article::class)
            ->willReturn($articleRepo);

        $lock = $this->createMock(ArticleLock::class);
        $lockedByUser = $this->createStub(User::class);
        $lockedByUser->method('getId')->willReturn(1);
        $lock->method('getLockedBy')->willReturn($lockedByUser);
        $lock->expects($this->once())->method('refreshExpiration');

        $this->lockRepository->method('findActiveLockForArticle')
            ->with($article)
            ->willReturn($lock);

        $operation = $this->createHeartbeatOperation();
        $result = $this->processor->process(null, $operation, ['articleId' => 1]);

        $this->assertInstanceOf(ArticleLock::class, $result);
    }

    #[Test]
    public function itCreatesNewLockOnHeartbeatWithNoExistingLock(): void
    {
        // Note: The heartbeat operation name '_api_article_lock_heartbeat_post'
        // matches str_contains('lock') && POST, so the acquireLock path is taken.
        // When no existing lock is found, acquireLock creates a new lock.
        $user = $this->createStub(User::class);
        $this->security->method('getUser')->willReturn($user);

        $article = $this->createStub(Article::class);
        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(1)->willReturn($article);

        $this->entityManager->method('getRepository')
            ->with(Article::class)
            ->willReturn($articleRepo);

        $this->lockRepository->method('findActiveLockForArticle')
            ->with($article)
            ->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(ArticleLock::class));
        $this->entityManager->expects($this->once())->method('flush');

        $operation = $this->createHeartbeatOperation();
        $result = $this->processor->process(null, $operation, ['articleId' => 1]);

        $this->assertInstanceOf(ArticleLock::class, $result);
    }

    // ========================
    // Lock Release Tests
    // ========================

    #[Test]
    public function itReleasesLockOnDelete(): void
    {
        $user = $this->createStub(User::class);
        $this->security->method('getUser')->willReturn($user);

        $article = $this->createStub(Article::class);
        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(1)->willReturn($article);

        $this->entityManager->method('getRepository')
            ->with(Article::class)
            ->willReturn($articleRepo);

        $this->lockRepository->expects($this->once())
            ->method('releaseLock')
            ->with($article, $user)
            ->willReturn(true);

        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Delete(name: 'unlock');
        $result = $this->processor->process(null, $operation, ['articleId' => 1]);

        $this->assertNull($result);
    }

    // ========================
    // Helper Methods
    // ========================

    private function createLockOperation(): Operation
    {
        return new Post(name: '_api_article_lock_post');
    }

    private function createHeartbeatOperation(): Operation
    {
        return new Post(name: '_api_article_lock_heartbeat_post');
    }
}
