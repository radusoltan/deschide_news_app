<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ElasticsearchIndexArticlesCommand;
use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Tag;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use App\Service\Elasticsearch\ElasticDocumentService;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ElasticsearchIndexArticlesCommandTest extends TestCase
{
    // ── Metadata Tests ──────────────────────────────────────────────────────

    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:elasticsearch:index-articles', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasAllOptions(): void
    {
        $command = $this->buildCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('locale'));
        $this->assertTrue($definition->hasOption('status'));
        $this->assertTrue($definition->hasOption('include-archived'));
        $this->assertTrue($definition->hasOption('batch-size'));
    }

    public function testLocaleOptionHasShortcutL(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('locale');
        $this->assertSame('l', $option->getShortcut());
    }

    public function testBatchSizeOptionDefaultsTo100(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('100', $command->getDefinition()->getOption('batch-size')->getDefault());
    }

    // ── ES Disabled ─────────────────────────────────────────────────────────

    public function testExecuteWhenEsIsDisabledReturnsSuccessEarly(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(false);
        $em = $this->createStub(EntityManagerInterface::class);

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $tester = $this->runCommand($command, []);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('disabled', $tester->getDisplay());
    }

    public function testExecuteWhenEsIsDisabledDoesNotQueryDatabase(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('getRepository');

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $tester = $this->runCommand($command, []);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    // ── Default Locales (all 3) ─────────────────────────────────────────────

    public function testExecuteWithDefaultLocalesIndexesAllThree(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('bulkIndexDocuments')->willReturn(['indexed' => 0, 'errors' => 0]);

        $em = $this->buildEmWithEmptyArticles();

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $tester = $this->runCommand($command, []);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('ro', $output);
        $this->assertStringContainsString('en', $output);
        $this->assertStringContainsString('ru', $output);
        $this->assertStringContainsString('Total articles indexed: 0', $output);
    }

    // ── Specific Locale ─────────────────────────────────────────────────────

    public function testExecuteWithLocaleRoOnlyIndexesRo(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('bulkIndexDocuments')->willReturn(['indexed' => 0, 'errors' => 0]);

        $em = $this->buildEmWithEmptyArticles();

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $tester = $this->runCommand($command, ['--locale' => 'ro']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('locale: ro', $output);
    }

    public function testExecuteWithLocaleEnOnlyIndexesEn(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('bulkIndexDocuments')->willReturn(['indexed' => 0, 'errors' => 0]);

        $em = $this->buildEmWithEmptyArticles();

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $tester = $this->runCommand($command, ['--locale' => 'en']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('locale: en', $tester->getDisplay());
    }

    // ── Exception Handling ──────────────────────────────────────────────────

    public function testExecuteWhenExceptionThrownReturnsFailure(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);

        $query = $this->createStub(Query::class);
        $query->method('getResult')->willThrowException(new Exception('Query error'));

        $qb = $this->buildQueryBuilder($query);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $tester = $this->runCommand($command, []);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Failed to index articles', $output);
        $this->assertStringContainsString('Query error', $output);
    }

    public function testExecuteWithBulkIndexExceptionReturnsFailure(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('bulkIndexDocuments')
            ->willThrowException(new Exception('Bulk index failed'));

        [$em, $article] = $this->buildEmWithOneArticle();

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $tester = $this->runCommand($command, ['--locale' => 'ro']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Bulk index failed', $tester->getDisplay());
    }

    // ── With Articles ───────────────────────────────────────────────────────

    public function testExecuteWithArticlesCallsBulkIndex(): void
    {
        $service = $this->createMock(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->atLeastOnce())
            ->method('bulkIndexDocuments')
            ->willReturn(['indexed' => 1, 'errors' => 0]);

        [$em] = $this->buildEmWithOneArticle();

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $tester = $this->runCommand($command, ['--locale' => 'ro']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('1 articles to index', $tester->getDisplay());
    }

    public function testExecuteWithArticlesShowsCorrectTotalIndexed(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('bulkIndexDocuments')
            ->willReturn(['indexed' => 1, 'errors' => 0]);

        [$em] = $this->buildEmWithOneArticle();

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $tester = $this->runCommand($command, ['--locale' => 'ro']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Total articles indexed: 1', $tester->getDisplay());
    }

    // ── Status Filter ───────────────────────────────────────────────────────

    public function testExecuteWithStatusFilterDoesNotFail(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);

        $em = $this->buildEmWithEmptyArticles();

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $tester = $this->runCommand($command, [
            '--status' => 'published',
            '--locale' => 'ro',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    // ── Include Archived ────────────────────────────────────────────────────

    public function testExecuteWithIncludeArchivedDoesNotFail(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);

        $em = $this->buildEmWithEmptyArticles();

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $tester = $this->runCommand($command, [
            '--include-archived' => true,
            '--locale' => 'ro',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    // ── Batch Size ──────────────────────────────────────────────────────────

    public function testExecuteWithCustomBatchSizeDoesNotFail(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);

        $em = $this->buildEmWithEmptyArticles();

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $tester = $this->runCommand($command, [
            '--batch-size' => '10',
            '--locale' => 'ro',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    // ── Error Count Reported ────────────────────────────────────────────────

    public function testExecuteReportsErrorsFromBulkIndex(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('bulkIndexDocuments')
            ->willReturn(['indexed' => 0, 'errors' => 2]);

        [$em] = $this->buildEmWithOneArticle();

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $tester = $this->runCommand($command, ['--locale' => 'ro']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Errors: 2', $tester->getDisplay());
    }

    // ── Helper Methods ──────────────────────────────────────────────────────

    private function runCommand(ElasticsearchIndexArticlesCommand $command, array $input): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($application->find('app:elasticsearch:index-articles'));
        $tester->execute($input);

        return $tester;
    }

    private function buildCommand(): ElasticsearchIndexArticlesCommand
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $em = $this->createStub(EntityManagerInterface::class);

        return new ElasticsearchIndexArticlesCommand($service, $em);
    }

    private function buildQueryBuilder(Query $query): QueryBuilder
    {
        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    private function buildEmWithEmptyArticles(): EntityManagerInterface
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->buildQueryBuilder($query);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }

    /**
     * Build an EntityManager that returns one article stub with all fields populated.
     *
     * @return array{0: EntityManagerInterface, 1: Article}
     */
    private function buildEmWithOneArticle(): array
    {
        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(1);
        $category->method('getTitle')->willReturn('Politica');
        $category->method('getSlug')->willReturn('politica');

        $authorEntity = $this->createStub(Author::class);
        $authorEntity->method('getId')->willReturn(1);
        $authorEntity->method('getFullName')->willReturn('Ion Munteanu');

        $tag = $this->createStub(Tag::class);
        $tag->method('getId')->willReturn(1);
        $tag->method('getName')->willReturn('Alegeri');
        $tag->method('getSlug')->willReturn('alegeri');

        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Test Article');
        $article->method('getSlug')->willReturn('test-article');
        $article->method('getLead')->willReturn('Test lead content');
        $article->method('getContent')->willReturn('Test article content body');
        $article->method('getViewCount')->willReturn(100);
        $article->method('getPublishedAt')->willReturn(new DateTimeImmutable('2025-01-15'));
        $article->method('getPublishAt')->willReturn(null);
        $article->method('getCreatedAt')->willReturn(new DateTimeImmutable('2025-01-01'));
        $article->method('getArchivedAt')->willReturn(null);
        $article->method('getArchiveReason')->willReturn(null);
        $article->method('getCategory')->willReturn($category);
        $article->method('getBadge')->willReturn(null);
        $article->method('isFeatured')->willReturn(false);
        $article->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $article->method('getAuthors')->willReturn(new ArrayCollection([$authorEntity]));
        $article->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $article->method('getTags')->willReturn(new ArrayCollection([$tag]));

        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([$article]);

        $qb = $this->buildQueryBuilder($query);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return [$em, $article];
    }
}
