<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete as DeleteOperation;
use ApiPlatform\Metadata\Post as PostOperation;
use App\Entity\LiveTextPost;
use App\Entity\LiveTextReaction;
use App\Entity\User;
use App\Enum\ReactionType;
use App\Repository\LiveTextReactionRepository;
use App\State\LiveTextReactionProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LiveTextReactionProcessorTest extends TestCase
{
    private LiveTextReactionProcessor $processor;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private Security $security;
    private LiveTextReactionRepository $reactionRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->security = $this->createStub(Security::class);
        $this->reactionRepository = $this->createStub(LiveTextReactionRepository::class);

        $this->processor = new LiveTextReactionProcessor(
            $this->entityManager,
            $this->requestStack,
            $this->security,
            $this->reactionRepository
        );
    }

    // ========================
    // CREATE Operation Tests
    // ========================

    #[Test]
    public function itCreatesReactionForAuthenticatedUser(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $this->security->method('getUser')->willReturn($user);

        $request = $this->createStub(Request::class);
        $request->method('getClientIp')->willReturn('127.0.0.1');
        $request->headers = new HeaderBag(['User-Agent' => 'TestBrowser']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $post = $this->createStub(LiveTextPost::class);

        $reaction = $this->createMock(LiveTextReaction::class);
        $reaction->method('getLiveTextPost')->willReturn($post);
        $reaction->method('getReactionType')->willReturn(ReactionType::LIKE);
        $reaction->expects($this->once())->method('setUser')->with($user);

        $this->reactionRepository->method('getUserReaction')
            ->with($post, 1, '127.0.0.1')
            ->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist')->with($reaction);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new PostOperation();
        $result = $this->processor->process($reaction, $operation);

        $this->assertInstanceOf(LiveTextReaction::class, $result);
    }

    #[Test]
    public function itReturnsExistingReactionWhenSameType(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $this->security->method('getUser')->willReturn($user);

        $request = $this->createStub(Request::class);
        $request->method('getClientIp')->willReturn('127.0.0.1');
        $request->headers = new HeaderBag(['User-Agent' => 'TestBrowser']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $post = $this->createStub(LiveTextPost::class);

        $existingReaction = $this->createMock(LiveTextReaction::class);
        $existingReaction->method('getReactionType')->willReturn(ReactionType::LIKE);

        $reaction = $this->createMock(LiveTextReaction::class);
        $reaction->method('getLiveTextPost')->willReturn($post);
        $reaction->method('getReactionType')->willReturn(ReactionType::LIKE);

        $this->reactionRepository->method('getUserReaction')
            ->with($post, 1, '127.0.0.1')
            ->willReturn($existingReaction);

        // Should NOT persist because it's the same reaction
        $this->entityManager->expects($this->never())->method('persist');

        $operation = new PostOperation();
        $result = $this->processor->process($reaction, $operation);

        $this->assertSame($existingReaction, $result);
    }

    #[Test]
    public function itUpdatesExistingReactionWhenDifferentType(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $this->security->method('getUser')->willReturn($user);

        $request = $this->createStub(Request::class);
        $request->method('getClientIp')->willReturn('127.0.0.1');
        $request->headers = new HeaderBag(['User-Agent' => 'TestBrowser']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $post = $this->createStub(LiveTextPost::class);

        $existingReaction = $this->createMock(LiveTextReaction::class);
        $existingReaction->method('getReactionType')->willReturn(ReactionType::LIKE);
        $existingReaction->expects($this->once())->method('setReactionType')->with(ReactionType::LOVE);

        $reaction = $this->createMock(LiveTextReaction::class);
        $reaction->method('getLiveTextPost')->willReturn($post);
        $reaction->method('getReactionType')->willReturn(ReactionType::LOVE);

        $this->reactionRepository->method('getUserReaction')
            ->with($post, 1, '127.0.0.1')
            ->willReturn($existingReaction);

        $this->entityManager->expects($this->once())->method('flush');

        $operation = new PostOperation();
        $result = $this->processor->process($reaction, $operation);

        $this->assertSame($existingReaction, $result);
    }

    #[Test]
    public function itThrowsExceptionForAnonymousUserWithNoIp(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $request = $this->createStub(Request::class);
        $request->method('getClientIp')->willReturn(null);
        $request->headers = new HeaderBag();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $reaction = $this->createMock(LiveTextReaction::class);
        $reaction->method('getLiveTextPost')->willReturn($this->createMock(LiveTextPost::class));
        $reaction->method('getReactionType')->willReturn(ReactionType::LIKE);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Unable to determine client IP address');

        $operation = new PostOperation();
        $this->processor->process($reaction, $operation);
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesOwnReaction(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $this->security->method('getUser')->willReturn($user);

        $reactionUser = $this->createStub(User::class);
        $reactionUser->method('getId')->willReturn(1);

        $reaction = $this->createMock(LiveTextReaction::class);
        $reaction->method('getUser')->willReturn($reactionUser);

        $this->entityManager->expects($this->once())->method('remove')->with($reaction);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new DeleteOperation();
        $result = $this->processor->process($reaction, $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itThrowsExceptionWhenDeletingOthersReaction(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $this->security->method('getUser')->willReturn($user);

        $otherUser = $this->createStub(User::class);
        $otherUser->method('getId')->willReturn(2);

        $reaction = $this->createMock(LiveTextReaction::class);
        $reaction->method('getUser')->willReturn($otherUser);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('You can only delete your own reactions');

        $operation = new DeleteOperation();
        $this->processor->process($reaction, $operation);
    }

    #[Test]
    public function itDeletesAnonymousReaction(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $reaction = $this->createMock(LiveTextReaction::class);
        $reaction->method('getUser')->willReturn(null); // anonymous reaction

        $this->entityManager->expects($this->once())->method('remove')->with($reaction);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new DeleteOperation();
        $result = $this->processor->process($reaction, $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itThrowsExceptionWhenDeletingReactionWithNoCurrentUser(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $reactionUser = $this->createStub(User::class);
        $reactionUser->method('getId')->willReturn(5);

        $reaction = $this->createMock(LiveTextReaction::class);
        $reaction->method('getUser')->willReturn($reactionUser);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('You can only delete your own reactions');

        $operation = new DeleteOperation();
        $this->processor->process($reaction, $operation);
    }

    #[Test]
    public function itCreatesReactionForAnonymousUserWithIp(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $request = $this->createStub(Request::class);
        $request->method('getClientIp')->willReturn('192.168.1.100');
        $request->headers = new HeaderBag(['User-Agent' => 'Mozilla/5.0']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $post = $this->createStub(LiveTextPost::class);

        $reaction = $this->createMock(LiveTextReaction::class);
        $reaction->method('getLiveTextPost')->willReturn($post);
        $reaction->method('getReactionType')->willReturn(ReactionType::LIKE);
        $reaction->expects($this->once())->method('setIpAddress')->with('192.168.1.100');
        $reaction->expects($this->once())->method('setUserAgent');

        $this->reactionRepository->method('getUserReaction')
            ->with($post, null, '192.168.1.100')
            ->willReturn(null);

        // Rate limit: under threshold
        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();

        $query = $this->createStub(\Doctrine\ORM\Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $this->entityManager->expects($this->once())->method('persist')->with($reaction);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new PostOperation();
        $result = $this->processor->process($reaction, $operation);

        $this->assertInstanceOf(LiveTextReaction::class, $result);
    }

    #[Test]
    public function itThrowsTooManyRequestsWhenRateLimitExceeded(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $request = $this->createStub(Request::class);
        $request->method('getClientIp')->willReturn('10.0.0.1');
        $request->headers = new HeaderBag();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $post = $this->createStub(LiveTextPost::class);

        $reaction = $this->createMock(LiveTextReaction::class);
        $reaction->method('getLiveTextPost')->willReturn($post);
        $reaction->method('getReactionType')->willReturn(ReactionType::LIKE);

        $this->reactionRepository->method('getUserReaction')
            ->with($post, null, '10.0.0.1')
            ->willReturn(null);

        // Rate limit: at threshold (10 reactions)
        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();

        $query = $this->createStub(\Doctrine\ORM\Query::class);
        $query->method('getSingleScalarResult')->willReturn(10);
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException::class);
        $this->expectExceptionMessage('Too many reactions');

        $operation = new PostOperation();
        $this->processor->process($reaction, $operation);
    }

    // ========================
    // Passthrough Tests
    // ========================

    #[Test]
    public function itReturnsDataForUnsupportedOperation(): void
    {
        $reaction = $this->createMock(LiveTextReaction::class);

        $operation = new \ApiPlatform\Metadata\Put();
        $result = $this->processor->process($reaction, $operation);

        $this->assertSame($reaction, $result);
    }
}
