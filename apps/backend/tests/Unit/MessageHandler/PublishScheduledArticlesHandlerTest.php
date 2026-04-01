<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Message\PublishScheduledArticles;
use App\MessageHandler\PublishScheduledArticlesHandler;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class PublishScheduledArticlesHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private PublishScheduledArticlesHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->handler = new PublishScheduledArticlesHandler(
            $this->entityManager,
            $this->logger
        );
    }

    public function testInvokePublishesScheduledArticles(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Test Article');
        $article->method('getPublishAt')->willReturn(new \DateTimeImmutable('-1 hour'));
        $article->method('getPublishedAt')->willReturn(null);

        $article->expects($this->once())
            ->method('setStatus')
            ->with(ArticleStatus::PUBLISHED);

        $article->expects($this->once())
            ->method('setPublishedAt')
            ->with($this->isInstanceOf(\DateTimeImmutable::class));

        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([$article]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturn($qb);
        $qb->method('from')->willReturn($qb);
        $qb->method('where')->willReturn($qb);
        $qb->method('andWhere')->willReturn($qb);
        $qb->method('setParameter')->willReturn($qb);
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($article);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $message = new PublishScheduledArticles();
        ($this->handler)($message);
    }

    public function testInvokeDoesNothingWhenNoArticles(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturn($qb);
        $qb->method('from')->willReturn($qb);
        $qb->method('where')->willReturn($qb);
        $qb->method('andWhere')->willReturn($qb);
        $qb->method('setParameter')->willReturn($qb);
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $this->entityManager->expects($this->never())
            ->method('persist');

        // flush is still called after the loop
        $this->entityManager->expects($this->never())
            ->method('flush');

        $message = new PublishScheduledArticles();
        ($this->handler)($message);
    }

    public function testInvokeHandlesArticlePublishException(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Failing Article');
        $article->method('getPublishAt')->willReturn(new \DateTimeImmutable('-1 hour'));
        $article->method('getPublishedAt')->willReturn(null);
        $article->method('setStatus')->willThrowException(new \RuntimeException('Status error'));

        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([$article]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturn($qb);
        $qb->method('from')->willReturn($qb);
        $qb->method('where')->willReturn($qb);
        $qb->method('andWhere')->willReturn($qb);
        $qb->method('setParameter')->willReturn($qb);
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        // Should not throw - exception is caught per article
        $message = new PublishScheduledArticles();
        ($this->handler)($message);
        $this->assertTrue(true);
    }
}
