<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\ArticleLockController;
use App\Entity\Article;
use App\Entity\ArticleLock;
use App\Entity\User;
use App\Repository\ArticleLockRepository;
use DateTimeImmutable;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Unit tests for ArticleLockController.
 *
 * Tests all five endpoints:
 * - GET  /api/articles/locks/active
 * - GET  /api/articles/{id}/lock/check
 * - POST /api/articles/{id}/lock
 * - POST /api/articles/{id}/lock/heartbeat
 * - DELETE /api/articles/{id}/lock
 */
class ArticleLockControllerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ArticleLockRepository $lockRepository;
    private ArticleLockController $controller;
    private ContainerInterface $container;
    private TokenStorageInterface $tokenStorage;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->lockRepository = $this->createStub(ArticleLockRepository::class);
        $this->tokenStorage = $this->createStub(TokenStorageInterface::class);

        $this->controller = new ArticleLockController(
            $this->entityManager,
            $this->lockRepository,
        );

        // AbstractController needs a container for json() and getUser()
        $this->container = $this->createStub(ContainerInterface::class);
        $this->container->method('has')->willReturnMap([
            ['serializer', false],
            ['security.token_storage', true],
            ['twig', false],
        ]);
        $this->container->method('get')->willReturnMap([
            ['security.token_storage', $this->tokenStorage],
        ]);
        $this->controller->setContainer($this->container);
    }

    // =============================================
    // Helpers
    // =============================================

    private function createUserStub(int $id, string $firstName = 'John', string $lastName = 'Doe', string $email = 'john@test.com'): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getFirstName')->willReturn($firstName);
        $user->method('getLastName')->willReturn($lastName);
        $user->method('getEmail')->willReturn($email);

        return $user;
    }

    private function createArticleStub(int $id, string $title = 'Test Article'): Article
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn($id);
        $article->method('getTitle')->willReturn($title);

        return $article;
    }

    private function createLockStub(
        int $id,
        Article $article,
        User $user,
        ?DateTimeImmutable $lockedAt = null,
        ?DateTimeImmutable $expiresAt = null,
    ): ArticleLock {
        $lock = $this->createStub(ArticleLock::class);
        $lock->method('getId')->willReturn($id);
        $lock->method('getArticle')->willReturn($article);
        $lock->method('getLockedBy')->willReturn($user);
        $lock->method('getLockedAt')->willReturn($lockedAt ?? new DateTimeImmutable('2026-01-01 12:00:00'));
        $lock->method('getExpiresAt')->willReturn($expiresAt ?? new DateTimeImmutable('2026-01-01 12:15:00'));

        return $lock;
    }

    private function setCurrentUser(?User $user): void
    {
        if ($user === null) {
            $this->tokenStorage->method('getToken')->willReturn(null);
        } else {
            $token = $this->createStub(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
            $this->tokenStorage->method('getToken')->willReturn($token);
        }
    }

    private function stubArticleRepository(?Article $article): void
    {
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->willReturn($article);
        $this->entityManager->method('getRepository')->willReturn($repo);
    }

    // =============================================
    // getActiveLocks
    // =============================================

    #[Test]
    public function getActiveLocksReturnsEmptyArrayWhenNoLocks(): void
    {
        $query = $this->getMockBuilder(\Doctrine\ORM\Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getResult')->willReturn([]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $response = $this->controller->getActiveLocks();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame([], $data);
    }

    #[Test]
    public function getActiveLocksReturnsFormattedLocks(): void
    {
        $user = $this->createUserStub(1, 'John', 'Doe', 'john@test.com');
        $article = $this->createArticleStub(42, 'Breaking News');
        $lockedAt = new DateTimeImmutable('2026-03-27 10:00:00');
        $expiresAt = new DateTimeImmutable('2026-03-27 10:15:00');
        $lock = $this->createLockStub(100, $article, $user, $lockedAt, $expiresAt);

        $query = $this->getMockBuilder(\Doctrine\ORM\Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getResult')->willReturn([$lock]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $response = $this->controller->getActiveLocks();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data);
        $this->assertSame(42, $data[0]['articleId']);
        $this->assertSame('Breaking News', $data[0]['articleTitle']);
        $this->assertSame(1, $data[0]['lockedBy']['id']);
        $this->assertSame('John', $data[0]['lockedBy']['firstName']);
        $this->assertSame('Doe', $data[0]['lockedBy']['lastName']);
        $this->assertSame('john@test.com', $data[0]['lockedBy']['email']);
        $this->assertSame($lockedAt->format('c'), $data[0]['lockedAt']);
        $this->assertSame($expiresAt->format('c'), $data[0]['expiresAt']);
    }

    #[Test]
    public function getActiveLocksReturnsMultipleLocks(): void
    {
        $user1 = $this->createUserStub(1, 'John', 'Doe', 'john@test.com');
        $user2 = $this->createUserStub(2, 'Jane', 'Smith', 'jane@test.com');
        $article1 = $this->createArticleStub(10, 'Article One');
        $article2 = $this->createArticleStub(20, 'Article Two');

        $lock1 = $this->createLockStub(1, $article1, $user1);
        $lock2 = $this->createLockStub(2, $article2, $user2);

        $query = $this->getMockBuilder(\Doctrine\ORM\Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getResult')->willReturn([$lock1, $lock2]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $response = $this->controller->getActiveLocks();

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertSame(10, $data[0]['articleId']);
        $this->assertSame(20, $data[1]['articleId']);
    }

    // =============================================
    // checkLock
    // =============================================

    #[Test]
    public function checkLockReturns404WhenArticleNotFound(): void
    {
        $this->stubArticleRepository(null);

        $response = $this->controller->checkLock(999);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Article not found', $data['error']);
    }

    #[Test]
    public function checkLockReturnsFalseWhenNoActiveLock(): void
    {
        $article = $this->createArticleStub(1);
        $this->stubArticleRepository($article);
        $this->lockRepository->method('findActiveLockForArticle')->willReturn(null);

        $response = $this->controller->checkLock(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['locked']);
    }

    #[Test]
    public function checkLockReturnsTrueWithLockDetailsWhenLocked(): void
    {
        $lockUser = $this->createUserStub(5, 'Alice', 'Wonder', 'alice@test.com');
        $article = $this->createArticleStub(1);
        $lockedAt = new DateTimeImmutable('2026-03-27 10:00:00');
        $expiresAt = new DateTimeImmutable('2026-03-27 10:15:00');
        $lock = $this->createLockStub(10, $article, $lockUser, $lockedAt, $expiresAt);

        $this->stubArticleRepository($article);
        $this->lockRepository->method('findActiveLockForArticle')->willReturn($lock);

        // No current user
        $this->setCurrentUser(null);

        $response = $this->controller->checkLock(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['locked']);
        $this->assertSame(5, $data['lockedBy']['id']);
        $this->assertSame('Alice', $data['lockedBy']['firstName']);
        $this->assertSame('Wonder', $data['lockedBy']['lastName']);
        $this->assertSame('alice@test.com', $data['lockedBy']['email']);
        $this->assertSame($lockedAt->format('c'), $data['lockedAt']);
        $this->assertSame($expiresAt->format('c'), $data['expiresAt']);
        $this->assertFalse($data['isLockedByCurrentUser']);
    }

    #[Test]
    public function checkLockIdentifiesCurrentUserAsLockOwner(): void
    {
        $currentUser = $this->createUserStub(7, 'Bob', 'Builder', 'bob@test.com');
        $article = $this->createArticleStub(1);
        $lock = $this->createLockStub(10, $article, $currentUser);

        $this->stubArticleRepository($article);
        $this->lockRepository->method('findActiveLockForArticle')->willReturn($lock);
        $this->setCurrentUser($currentUser);

        $response = $this->controller->checkLock(1);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['locked']);
        $this->assertTrue($data['isLockedByCurrentUser']);
    }

    #[Test]
    public function checkLockIdentifiesDifferentUserAsNotLockOwner(): void
    {
        $lockUser = $this->createUserStub(5, 'Alice', 'Wonder', 'alice@test.com');
        $currentUser = $this->createUserStub(7, 'Bob', 'Builder', 'bob@test.com');
        $article = $this->createArticleStub(1);
        $lock = $this->createLockStub(10, $article, $lockUser);

        $this->stubArticleRepository($article);
        $this->lockRepository->method('findActiveLockForArticle')->willReturn($lock);
        $this->setCurrentUser($currentUser);

        $response = $this->controller->checkLock(1);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['locked']);
        $this->assertFalse($data['isLockedByCurrentUser']);
    }

    // =============================================
    // acquireLock
    // =============================================

    #[Test]
    public function acquireLockReturns401WhenNotAuthenticated(): void
    {
        $this->setCurrentUser(null);

        $response = $this->controller->acquireLock(1);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Authentication required', $data['error']);
    }

    #[Test]
    public function acquireLockReturns404WhenArticleNotFound(): void
    {
        $user = $this->createUserStub(1);
        $this->setCurrentUser($user);
        $this->stubArticleRepository(null);

        $response = $this->controller->acquireLock(999);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Article not found', $data['error']);
    }

    #[Test]
    public function acquireLockRefreshesExistingLockForSameUser(): void
    {
        $user = $this->createUserStub(1, 'John', 'Doe', 'john@test.com');
        $article = $this->createArticleStub(42);
        $lockedAt = new DateTimeImmutable('2026-03-27 10:00:00');
        $expiresAt = new DateTimeImmutable('2026-03-27 10:15:00');

        $existingLock = $this->createStub(ArticleLock::class);
        $existingLock->method('getId')->willReturn(100);
        $existingLock->method('getLockedBy')->willReturn($user);
        $existingLock->method('getLockedAt')->willReturn($lockedAt);
        $existingLock->method('getExpiresAt')->willReturn($expiresAt);

        $this->setCurrentUser($user);
        $this->stubArticleRepository($article);
        $this->lockRepository->method('findActiveLockForArticle')->willReturn($existingLock);

        $response = $this->controller->acquireLock(42);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(100, $data['id']);
        $this->assertArrayHasKey('lockedAt', $data);
        $this->assertArrayHasKey('expiresAt', $data);
    }

    #[Test]
    public function acquireLockReturns409WhenLockedByAnotherUser(): void
    {
        $currentUser = $this->createUserStub(1, 'John', 'Doe', 'john@test.com');
        $otherUser = $this->createUserStub(2, 'Jane', 'Smith', 'jane@test.com');
        $article = $this->createArticleStub(42);

        $existingLock = $this->createStub(ArticleLock::class);
        $existingLock->method('getLockedBy')->willReturn($otherUser);

        $this->setCurrentUser($currentUser);
        $this->stubArticleRepository($article);
        $this->lockRepository->method('findActiveLockForArticle')->willReturn($existingLock);

        $response = $this->controller->acquireLock(42);

        $this->assertSame(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Jane Smith', $data['error']);
        $this->assertSame(2, $data['lockedBy']['id']);
        $this->assertSame('Jane', $data['lockedBy']['firstName']);
        $this->assertSame('Smith', $data['lockedBy']['lastName']);
    }

    #[Test]
    public function acquireLockCreatesNewLockWhenNoneExists(): void
    {
        $user = $this->createUserStub(1, 'John', 'Doe', 'john@test.com');
        $article = $this->createArticleStub(42);

        $this->setCurrentUser($user);
        $this->stubArticleRepository($article);
        $this->lockRepository->method('findActiveLockForArticle')->willReturn(null);

        $response = $this->controller->acquireLock(42);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('lockedAt', $data);
        $this->assertArrayHasKey('expiresAt', $data);
    }

    #[Test]
    public function acquireLockPersistsAndFlushesNewLock(): void
    {
        $user = $this->createUserStub(1);
        $article = $this->createArticleStub(42);

        $this->setCurrentUser($user);
        $this->stubArticleRepository($article);
        $this->lockRepository->method('findActiveLockForArticle')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->willReturn($article);
        $em->method('getRepository')->willReturn($repo);

        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(ArticleLock::class));
        $em->expects($this->once())->method('flush');

        $controller = new ArticleLockController($em, $this->lockRepository);
        $controller->setContainer($this->container);

        $response = $controller->acquireLock(42);

        $this->assertSame(201, $response->getStatusCode());
    }

    #[Test]
    public function acquireLockFlushesWhenRefreshingExistingLock(): void
    {
        $user = $this->createUserStub(1);
        $article = $this->createArticleStub(42);

        $existingLock = $this->createStub(ArticleLock::class);
        $existingLock->method('getId')->willReturn(100);
        $existingLock->method('getLockedBy')->willReturn($user);
        $existingLock->method('getLockedAt')->willReturn(new DateTimeImmutable());
        $existingLock->method('getExpiresAt')->willReturn(new DateTimeImmutable('+15 minutes'));

        $this->setCurrentUser($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->willReturn($article);
        $em->method('getRepository')->willReturn($repo);
        $em->expects($this->once())->method('flush');

        $lockRepo = $this->createStub(ArticleLockRepository::class);
        $lockRepo->method('findActiveLockForArticle')->willReturn($existingLock);

        $controller = new ArticleLockController($em, $lockRepo);
        $controller->setContainer($this->container);

        $response = $controller->acquireLock(42);

        $this->assertSame(200, $response->getStatusCode());
    }

    // =============================================
    // heartbeat
    // =============================================

    #[Test]
    public function heartbeatReturns401WhenNotAuthenticated(): void
    {
        $this->setCurrentUser(null);

        $response = $this->controller->heartbeat(1);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Authentication required', $data['error']);
    }

    #[Test]
    public function heartbeatReturns404WhenArticleNotFound(): void
    {
        $user = $this->createUserStub(1);
        $this->setCurrentUser($user);
        $this->stubArticleRepository(null);

        $response = $this->controller->heartbeat(999);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Article not found', $data['error']);
    }

    #[Test]
    public function heartbeatReturns404WhenNoActiveLockForUser(): void
    {
        $user = $this->createUserStub(1);
        $article = $this->createArticleStub(42);

        $this->setCurrentUser($user);
        $this->stubArticleRepository($article);
        $this->lockRepository->method('findLockForArticleAndUser')->willReturn(null);

        $response = $this->controller->heartbeat(42);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('No active lock found', $data['error']);
    }

    #[Test]
    public function heartbeatRefreshesAndReturnsLock(): void
    {
        $user = $this->createUserStub(1);
        $article = $this->createArticleStub(42);
        $lockedAt = new DateTimeImmutable('2026-03-27 10:00:00');
        $expiresAt = new DateTimeImmutable('2026-03-27 10:15:00');
        $lock = $this->createLockStub(100, $article, $user, $lockedAt, $expiresAt);

        $this->setCurrentUser($user);
        $this->stubArticleRepository($article);
        $this->lockRepository->method('findLockForArticleAndUser')->willReturn($lock);

        $response = $this->controller->heartbeat(42);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(100, $data['id']);
        $this->assertSame($lockedAt->format('c'), $data['lockedAt']);
        $this->assertSame($expiresAt->format('c'), $data['expiresAt']);
    }

    #[Test]
    public function heartbeatFlushesAfterRefresh(): void
    {
        $user = $this->createUserStub(1);
        $article = $this->createArticleStub(42);
        $lock = $this->createLockStub(100, $article, $user);

        $this->setCurrentUser($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->willReturn($article);
        $em->method('getRepository')->willReturn($repo);
        $em->expects($this->once())->method('flush');

        $lockRepo = $this->createStub(ArticleLockRepository::class);
        $lockRepo->method('findLockForArticleAndUser')->willReturn($lock);

        $controller = new ArticleLockController($em, $lockRepo);
        $controller->setContainer($this->container);

        $response = $controller->heartbeat(42);

        $this->assertSame(200, $response->getStatusCode());
    }

    // =============================================
    // releaseLock
    // =============================================

    #[Test]
    public function releaseLockReturns401WhenNotAuthenticated(): void
    {
        $this->setCurrentUser(null);

        $response = $this->controller->releaseLock(1);

        // releaseLock returns json error for 401
        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function releaseLockReturns404WhenArticleNotFound(): void
    {
        $user = $this->createUserStub(1);
        $this->setCurrentUser($user);
        $this->stubArticleRepository(null);

        $response = $this->controller->releaseLock(999);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function releaseLockReturns204OnSuccess(): void
    {
        $user = $this->createUserStub(1);
        $article = $this->createArticleStub(42);

        $this->setCurrentUser($user);
        $this->stubArticleRepository($article);
        $this->lockRepository->method('releaseLock')->willReturn(true);

        $response = $this->controller->releaseLock(42);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    #[Test]
    public function releaseLockReturns204EvenWhenNoLockExisted(): void
    {
        $user = $this->createUserStub(1);
        $article = $this->createArticleStub(42);

        $this->setCurrentUser($user);
        $this->stubArticleRepository($article);
        $this->lockRepository->method('releaseLock')->willReturn(false);

        $response = $this->controller->releaseLock(42);

        // The controller always returns 204, regardless of whether a lock was released
        $this->assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function releaseLockFlushesEntityManager(): void
    {
        $user = $this->createUserStub(1);
        $article = $this->createArticleStub(42);

        $this->setCurrentUser($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->willReturn($article);
        $em->method('getRepository')->willReturn($repo);
        $em->expects($this->once())->method('flush');

        $lockRepo = $this->createStub(ArticleLockRepository::class);
        $lockRepo->method('releaseLock')->willReturn(true);

        $controller = new ArticleLockController($em, $lockRepo);
        $controller->setContainer($this->container);

        $response = $controller->releaseLock(42);

        $this->assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function releaseLockReturnsPlainResponseNotJson(): void
    {
        $user = $this->createUserStub(1);
        $article = $this->createArticleStub(42);

        $this->setCurrentUser($user);
        $this->stubArticleRepository($article);
        $this->lockRepository->method('releaseLock')->willReturn(true);

        $response = $this->controller->releaseLock(42);

        // releaseLock returns new Response('', 204), not JsonResponse
        $this->assertInstanceOf(Response::class, $response);
        $this->assertNotInstanceOf(JsonResponse::class, $response);
    }

    // =============================================
    // acquireLock — 409 conflict response structure
    // =============================================

    #[Test]
    public function acquireLockConflictResponseContainsLockedByInfo(): void
    {
        $currentUser = $this->createUserStub(1);
        $otherUser = $this->createUserStub(2, 'Maria', 'Ionescu', 'maria@test.com');
        $article = $this->createArticleStub(42);

        $existingLock = $this->createStub(ArticleLock::class);
        $existingLock->method('getLockedBy')->willReturn($otherUser);

        $this->setCurrentUser($currentUser);
        $this->stubArticleRepository($article);
        $this->lockRepository->method('findActiveLockForArticle')->willReturn($existingLock);

        $response = $this->controller->acquireLock(42);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertStringContainsString('Maria', $data['error']);
        $this->assertStringContainsString('Ionescu', $data['error']);
        $this->assertArrayHasKey('lockedBy', $data);
        $this->assertSame(2, $data['lockedBy']['id']);
        $this->assertSame('Maria', $data['lockedBy']['firstName']);
        $this->assertSame('Ionescu', $data['lockedBy']['lastName']);
    }

    // =============================================
    // checkLock — response structure
    // =============================================

    #[Test]
    public function checkLockUnlockedResponseOnlyContainsLockedFalse(): void
    {
        $article = $this->createArticleStub(1);
        $this->stubArticleRepository($article);
        $this->lockRepository->method('findActiveLockForArticle')->willReturn(null);

        $response = $this->controller->checkLock(1);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(['locked' => false], $data);
    }

    #[Test]
    public function checkLockLockedResponseContainsAllExpectedFields(): void
    {
        $lockUser = $this->createUserStub(5, 'Alice', 'Wonder', 'alice@test.com');
        $article = $this->createArticleStub(1);
        $lock = $this->createLockStub(10, $article, $lockUser);

        $this->stubArticleRepository($article);
        $this->lockRepository->method('findActiveLockForArticle')->willReturn($lock);
        $this->setCurrentUser(null);

        $response = $this->controller->checkLock(1);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('locked', $data);
        $this->assertArrayHasKey('lockedBy', $data);
        $this->assertArrayHasKey('lockedAt', $data);
        $this->assertArrayHasKey('expiresAt', $data);
        $this->assertArrayHasKey('isLockedByCurrentUser', $data);
        $this->assertArrayHasKey('id', $data['lockedBy']);
        $this->assertArrayHasKey('firstName', $data['lockedBy']);
        $this->assertArrayHasKey('lastName', $data['lockedBy']);
        $this->assertArrayHasKey('email', $data['lockedBy']);
    }
}
