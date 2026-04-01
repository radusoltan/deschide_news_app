<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\PublishScheduledArticlesCommand;
use App\Entity\Article;
use App\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class PublishScheduledArticlesCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $command = new PublishScheduledArticlesCommand($em, $logger);
        $this->assertSame('app:publish-scheduled-articles', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $command = new PublishScheduledArticlesCommand($em, $logger);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testExecuteWithNoArticlesToPublish(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $logger = $this->createStub(LoggerInterface::class);

        $command = new PublishScheduledArticlesCommand($em, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No articles to publish', $tester->getDisplay());
    }

    public function testExecutePublishesScheduledArticles(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Test Article');
        $article->method('getPublishAt')->willReturn(new \DateTimeImmutable('-1 hour'));
        $article->method('getPublishedAt')->willReturn(null);
        $article->expects($this->once())->method('setStatus')->with(ArticleStatus::PUBLISHED);
        $article->expects($this->once())->method('setPublishedAt');

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$article]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->expects($this->atLeastOnce())->method('persist');
        $em->expects($this->once())->method('flush');

        $logger = $this->createStub(LoggerInterface::class);

        $command = new PublishScheduledArticlesCommand($em, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Successfully published', $tester->getDisplay());
    }

    public function testExecuteHandlesPerArticleException(): void
    {
        $goodArticle = $this->createMock(Article::class);
        $goodArticle->method('getId')->willReturn(1);
        $goodArticle->method('getTitle')->willReturn('Good Article');
        $goodArticle->method('getPublishAt')->willReturn(new \DateTimeImmutable('-1 hour'));
        $goodArticle->method('getPublishedAt')->willReturn(null);
        $goodArticle->expects($this->once())->method('setStatus')->with(ArticleStatus::PUBLISHED);
        $goodArticle->expects($this->once())->method('setPublishedAt');

        $badArticle = $this->createMock(Article::class);
        $badArticle->method('getId')->willReturn(2);
        $badArticle->method('getTitle')->willReturn('Bad Article');
        $badArticle->method('getPublishAt')->willReturn(new \DateTimeImmutable('-1 hour'));
        $badArticle->method('setStatus')->willThrowException(new Exception('Status update failed'));

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$badArticle, $goodArticle]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $logger = $this->createMock(LoggerInterface::class);
        // Error logged for the bad article
        $logger->expects($this->once())->method('error');
        // Info logged for the good article
        $logger->expects($this->once())->method('info');

        $command = new PublishScheduledArticlesCommand($em, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Failed to publish article [2]', $display);
        $this->assertStringContainsString('Status update failed', $display);
        $this->assertStringContainsString('Successfully published 1 article(s)', $display);
    }

    public function testExecuteWithMultipleArticlesPublishesAll(): void
    {
        $article1 = $this->createMock(Article::class);
        $article1->method('getId')->willReturn(1);
        $article1->method('getTitle')->willReturn('Article 1');
        $article1->method('getPublishAt')->willReturn(new \DateTimeImmutable('-2 hours'));
        $article1->method('getPublishedAt')->willReturn(null);

        $article2 = $this->createMock(Article::class);
        $article2->method('getId')->willReturn(2);
        $article2->method('getTitle')->willReturn('Article 2');
        $article2->method('getPublishAt')->willReturn(new \DateTimeImmutable('-30 minutes'));
        $article2->method('getPublishedAt')->willReturn(null);

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$article1, $article2]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->once())->method('flush');

        $logger = $this->createStub(LoggerInterface::class);

        $command = new PublishScheduledArticlesCommand($em, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Successfully published 2 article(s)', $tester->getDisplay());
    }

    public function testExecuteHandlesQueryException(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willThrowException(new Exception('Connection failed'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $command = new PublishScheduledArticlesCommand($em, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Connection failed', $tester->getDisplay());
    }
}
