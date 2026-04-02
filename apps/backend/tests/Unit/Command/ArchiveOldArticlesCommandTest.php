<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ArchiveOldArticlesCommand;
use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ArchiveOldArticlesCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new ArchiveOldArticlesCommand($em);
        $this->assertSame('app:archive-old-articles', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new ArchiveOldArticlesCommand($em);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new ArchiveOldArticlesCommand($em);
        $this->assertTrue($command->getDefinition()->hasOption('years'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('batch-size'));
    }

    private function buildEntityManagerMock(array $articles): EntityManagerInterface
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($articles);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Article::class)->willReturn($repo);

        return $em;
    }

    public function testExecuteDryRunWithNoArticles(): void
    {
        $em = $this->buildEntityManagerMock([]);

        $command = new ArchiveOldArticlesCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DRY-RUN', $output);
    }

    public function testExecuteWithNoArticles(): void
    {
        $em = $this->buildEntityManagerMock([]);

        $command = new ArchiveOldArticlesCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithArticles(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Old Article');
        $article->method('getPublishedAt')->willReturn(new \DateTimeImmutable('2015-01-01'));

        // Second query (statistics by year) should return empty
        $statsQuery = $this->createMock(Query::class);
        $statsQuery->method('getResult')->willReturn([]);

        $statsQb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $statsQb->method('select')->willReturnSelf();
        $statsQb->method('where')->willReturnSelf();
        $statsQb->method('andWhere')->willReturnSelf();
        $statsQb->method('setParameter')->willReturnSelf();
        $statsQb->method('orderBy')->willReturnSelf();
        $statsQb->method('groupBy')->willReturnSelf();
        $statsQb->method('getQuery')->willReturn($statsQuery);

        // First query returns articles, second returns empty (for statistics)
        $articlesQuery = $this->createMock(Query::class);
        $callCount = 0;
        $articlesQuery->method('getResult')->willReturnCallback(function () use (&$callCount, $article) {
            ++$callCount;

            return $callCount === 1 ? [$article] : [];
        });

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();

        // First createQueryBuilder call for articles, second for stats
        $qbCallCount = 0;
        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturnCallback(function () use (&$qbCallCount, $qb, $statsQb, $articlesQuery, $statsQuery) {
            ++$qbCallCount;
            if ($qbCallCount <= 1) {
                $qb->method('getQuery')->willReturn($articlesQuery);

                return $qb;
            }

            return $statsQb;
        });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Article::class)->willReturn($repo);
        // Command calls flush() but NOT persist() for archiving
        $em->expects($this->atLeastOnce())->method('flush');

        $command = new ArchiveOldArticlesCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        // Need to set input to confirm 'yes' for the confirmation prompt
        $tester->setInputs(['yes']);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithCustomYearsOption(): void
    {
        $em = $this->buildEntityManagerMock([]);

        $command = new ArchiveOldArticlesCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--years' => '2']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('2 years ago', $tester->getDisplay());
    }

    public function testExecuteNoArticlesShowsSuccessMessage(): void
    {
        $em = $this->buildEntityManagerMock([]);

        $command = new ArchiveOldArticlesCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No articles found to archive', $tester->getDisplay());
    }

    public function testExecuteDryRunWithArticlesShowsDryRunSummary(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Old Article Title');
        $article->method('getPublishedAt')->willReturn(new \DateTimeImmutable('2018-06-15'));

        // Stats query builder (for year stats at end)
        $statsQuery = $this->createStub(Query::class);
        $statsQuery->method('getResult')->willReturn([]);

        $statsQb = $this->createStub(QueryBuilder::class);
        $statsQb->method('select')->willReturnSelf();
        $statsQb->method('where')->willReturnSelf();
        $statsQb->method('andWhere')->willReturnSelf();
        $statsQb->method('setParameter')->willReturnSelf();
        $statsQb->method('orderBy')->willReturnSelf();
        $statsQb->method('groupBy')->willReturnSelf();
        $statsQb->method('getQuery')->willReturn($statsQuery);

        $articlesQuery = $this->createStub(Query::class);
        $articlesQuery->method('getResult')->willReturn([$article]);

        $articlesQb = $this->createStub(QueryBuilder::class);
        $articlesQb->method('select')->willReturnSelf();
        $articlesQb->method('where')->willReturnSelf();
        $articlesQb->method('andWhere')->willReturnSelf();
        $articlesQb->method('setParameter')->willReturnSelf();
        $articlesQb->method('orderBy')->willReturnSelf();
        $articlesQb->method('groupBy')->willReturnSelf();
        $articlesQb->method('getQuery')->willReturn($articlesQuery);

        $qbCallCount = 0;
        $repo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturnCallback(
            function () use (&$qbCallCount, $articlesQb, $statsQb) {
                ++$qbCallCount;

                return $qbCallCount === 1 ? $articlesQb : $statsQb;
            }
        );

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $command = new ArchiveOldArticlesCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('[DRY-RUN]', $output);
        $this->assertStringContainsString('Would archive', $output);
        $this->assertStringContainsString('Old Article Title', $output);
        $this->assertStringContainsString('Would have archived 1 articles', $output);
    }

    public function testExecuteUserCancelsArchiving(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Article');
        $article->method('getPublishedAt')->willReturn(new \DateTimeImmutable('2015-01-01'));

        // Stats query builder
        $statsQuery = $this->createStub(Query::class);
        $statsQuery->method('getResult')->willReturn([]);

        $statsQb = $this->createStub(QueryBuilder::class);
        $statsQb->method('select')->willReturnSelf();
        $statsQb->method('where')->willReturnSelf();
        $statsQb->method('andWhere')->willReturnSelf();
        $statsQb->method('setParameter')->willReturnSelf();
        $statsQb->method('orderBy')->willReturnSelf();
        $statsQb->method('groupBy')->willReturnSelf();
        $statsQb->method('getQuery')->willReturn($statsQuery);

        $articlesQuery = $this->createStub(Query::class);
        $articlesQuery->method('getResult')->willReturn([$article]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($articlesQuery);

        $qbCallCount = 0;
        $repo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturnCallback(
            function () use (&$qbCallCount, $qb, $statsQb) {
                ++$qbCallCount;

                return $qbCallCount === 1 ? $qb : $statsQb;
            }
        );

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $command = new ArchiveOldArticlesCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        // User answers 'no' to the confirmation prompt
        $tester->setInputs(['no']);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Archiving cancelled by user', $tester->getDisplay());
    }

    public function testYearsOptionDefaultsTo4(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new ArchiveOldArticlesCommand($em);
        $this->assertSame(4, (int) $command->getDefinition()->getOption('years')->getDefault());
    }

    public function testBatchSizeOptionDefaultsTo100(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new ArchiveOldArticlesCommand($em);
        $this->assertSame(100, (int) $command->getDefinition()->getOption('batch-size')->getDefault());
    }

    public function testExecuteArchivingShowsFoundCount(): void
    {
        $article1 = $this->createStub(Article::class);
        $article1->method('getId')->willReturn(1);
        $article1->method('getTitle')->willReturn('Article 1');
        $article1->method('getPublishedAt')->willReturn(new \DateTimeImmutable('2018-01-01'));

        $article2 = $this->createStub(Article::class);
        $article2->method('getId')->willReturn(2);
        $article2->method('getTitle')->willReturn('Article 2');
        $article2->method('getPublishedAt')->willReturn(new \DateTimeImmutable('2017-06-15'));

        $statsQuery = $this->createStub(Query::class);
        $statsQuery->method('getResult')->willReturn([
            ['year' => '2017', 'count' => 1],
            ['year' => '2018', 'count' => 1],
        ]);

        $statsQb = $this->createStub(QueryBuilder::class);
        $statsQb->method('select')->willReturnSelf();
        $statsQb->method('where')->willReturnSelf();
        $statsQb->method('andWhere')->willReturnSelf();
        $statsQb->method('setParameter')->willReturnSelf();
        $statsQb->method('orderBy')->willReturnSelf();
        $statsQb->method('groupBy')->willReturnSelf();
        $statsQb->method('getQuery')->willReturn($statsQuery);

        $articlesQuery = $this->createStub(Query::class);
        $articlesQuery->method('getResult')->willReturn([$article1, $article2]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($articlesQuery);

        $qbCallCount = 0;
        $repo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturnCallback(
            function () use (&$qbCallCount, $qb, $statsQb) {
                ++$qbCallCount;

                return $qbCallCount === 1 ? $qb : $statsQb;
            }
        );

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $command = new ArchiveOldArticlesCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Found 2 articles to archive', $output);
        $this->assertStringContainsString('Successfully archived 2 articles', $output);
        $this->assertStringContainsString('Statistics by Publication Year', $output);
    }
}
