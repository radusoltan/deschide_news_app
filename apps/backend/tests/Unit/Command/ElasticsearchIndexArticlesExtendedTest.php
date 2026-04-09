<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ElasticsearchIndexArticlesCommand;
use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Service\Elasticsearch\ElasticDocumentService;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ElasticsearchIndexArticlesExtendedTest extends TestCase
{
    public function testIndexArticlesWithEmptyResult(): void
    {
        $service = $this->createMock(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);

        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('0 articles to index', $output);
        $this->assertStringContainsString('Total articles indexed: 0', $output);
    }

    public function testIndexArticlesWithSpecificLocale(): void
    {
        $service = $this->createMock(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('bulkIndexDocuments')->willReturn(['indexed' => 0, 'errors' => 0]);

        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--locale' => 'en']);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('en', $output);
    }

    public function testIndexArticlesWithStatusFilter(): void
    {
        $service = $this->createMock(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);

        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--status' => 'published', '--locale' => 'ro']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testIndexArticlesWithIncludeArchived(): void
    {
        $service = $this->createMock(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);

        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--include-archived' => true, '--locale' => 'ro']);

        $this->assertSame(0, $tester->getStatusCode());
    }
}
