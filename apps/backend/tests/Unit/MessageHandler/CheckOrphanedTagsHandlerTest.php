<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Tag;
use App\Message\CheckOrphanedTagsMessage;
use App\MessageHandler\CheckOrphanedTagsHandler;
use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CheckOrphanedTagsHandlerTest extends TestCase
{
    private TagRepository $tagRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private CheckOrphanedTagsHandler $handler;

    protected function setUp(): void
    {
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->handler = new CheckOrphanedTagsHandler(
            $this->tagRepository,
            $this->entityManager,
            $this->logger
        );
    }

    public function testInvokeWithSpecificTagIds(): void
    {
        $orphanedTag = $this->createStub(Tag::class);
        $orphanedTag->method('getId')->willReturn(1);
        $orphanedTag->method('getName')->willReturn('orphan');
        $orphanedTag->method('getUsageCount')->willReturn(0);
        $orphanedTag->method('getArticles')->willReturn(new ArrayCollection());

        $message = new CheckOrphanedTagsMessage([1]);

        $this->tagRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($orphanedTag);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($orphanedTag);

        $this->entityManager->expects($this->once())
            ->method('flush');

        ($this->handler)($message);
    }

    public function testInvokeSkipsNonOrphanedTags(): void
    {
        $activeTag = $this->createStub(Tag::class);
        $activeTag->method('getUsageCount')->willReturn(5);
        $activeTag->method('getArticles')->willReturn(new ArrayCollection([new \stdClass()]));

        $message = new CheckOrphanedTagsMessage([1]);

        $this->tagRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($activeTag);

        $this->entityManager->expects($this->never())
            ->method('remove');

        $this->entityManager->expects($this->never())
            ->method('flush');

        ($this->handler)($message);
    }

    public function testInvokeSkipsMissingTags(): void
    {
        $message = new CheckOrphanedTagsMessage([999]);

        $this->tagRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->entityManager->expects($this->never())
            ->method('remove');

        ($this->handler)($message);
    }

    public function testInvokeWithEmptyTagIdsChecksAll(): void
    {
        $orphanedTag = $this->createStub(Tag::class);
        $orphanedTag->method('getId')->willReturn(1);
        $orphanedTag->method('getName')->willReturn('unused');
        $orphanedTag->method('getArticles')->willReturn(new ArrayCollection());

        $message = new CheckOrphanedTagsMessage([]);

        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([$orphanedTag]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('where')->willReturn($qb);
        $qb->method('getQuery')->willReturn($query);

        $this->tagRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('t')
            ->willReturn($qb);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($orphanedTag);

        $this->entityManager->expects($this->once())
            ->method('flush');

        ($this->handler)($message);
    }

    public function testInvokeWithEmptyTagIdsNoOrphans(): void
    {
        $message = new CheckOrphanedTagsMessage([]);

        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('where')->willReturn($qb);
        $qb->method('getQuery')->willReturn($query);

        $this->tagRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('t')
            ->willReturn($qb);

        $this->entityManager->expects($this->never())
            ->method('remove');

        ($this->handler)($message);
    }
}
