<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Topic;

use App\Command\Topic\ClassifyArticlesCommand;
use App\Entity\Article;
use App\Service\Topic\BatchResult;
use App\Service\Topic\BatchTopicClassifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[AllowMockObjectsWithoutExpectations]
class ClassifyArticlesCommandTest extends TestCase
{
    public function testCommandName(): void
    {
        $command = $this->buildCommand([], $this->createMock(BatchTopicClassifier::class));
        self::assertSame('app:articles:classify-topics', $command->getName());
    }

    public function testCommandOptions(): void
    {
        $command = $this->buildCommand([], $this->createMock(BatchTopicClassifier::class));
        $def = $command->getDefinition();

        self::assertTrue($def->hasOption('limit'));
        self::assertTrue($def->hasOption('offset'));
        self::assertTrue($def->hasOption('batch-size'));
        self::assertTrue($def->hasOption('category'));
        self::assertTrue($def->hasOption('confidence-threshold'));
        self::assertTrue($def->hasOption('dry-run'));
    }

    public function testDryRunSkipsClassifier(): void
    {
        $article = $this->makeArticleStub(7, 'Some title');

        $classifier = $this->createMock(BatchTopicClassifier::class);
        $classifier->expects(self::never())->method('classifyBatch');

        $command = $this->buildCommand([$article], $classifier);
        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('DRY RUN', $tester->getDisplay());
        self::assertStringContainsString('#7', $tester->getDisplay());
    }

    public function testSkipsWhenNoUnclassifiedArticles(): void
    {
        $classifier = $this->createMock(BatchTopicClassifier::class);
        $classifier->expects(self::never())->method('classifyBatch');

        $command = $this->buildCommand([], $classifier);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('No unclassified published articles found', $tester->getDisplay());
    }

    public function testCallsClassifierWhenArticlesPresent(): void
    {
        $article = $this->makeArticleStub(42, 'Real article');

        $result = new BatchResult();
        $result->classified = 1;
        $result->totalAssignments = 2;

        $classifier = $this->createMock(BatchTopicClassifier::class);
        $classifier->expects(self::once())
            ->method('classifyBatch')
            ->with(self::identicalTo([$article]), 20, self::isCallable())
            ->willReturn($result);

        $command = $this->buildCommand([$article], $classifier);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Backfill Summary', $tester->getDisplay());
    }

    public function testReportsWarningOnFailedChunks(): void
    {
        $article = $this->makeArticleStub(42, 'Failing article');

        $result = new BatchResult();
        $result->classified = 0;
        $result->failedChunks = 1;

        $classifier = $this->createMock(BatchTopicClassifier::class);
        $classifier->method('classifyBatch')->willReturn($result);

        $command = $this->buildCommand([$article], $classifier);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('1 chunk(s) failed', $tester->getDisplay());
    }

    /**
     * @param Article[] $articles
     */
    private function buildCommand(array $articles, BatchTopicClassifier $classifier): ClassifyArticlesCommand
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $command = new class($em, $classifier, $articles) extends ClassifyArticlesCommand {
            /**
             * @param Article[] $articles
             */
            public function __construct(
                EntityManagerInterface $em,
                BatchTopicClassifier $classifier,
                private readonly array $articles,
            ) {
                parent::__construct($em, $classifier);
                $this->setName('app:articles:classify-topics');
            }

            protected function loadUnclassifiedArticles(mixed $categoryId, int $offset, int $limit): array
            {
                return $this->articles;
            }

            protected function loadTopTopicsHitDuringSession(array $articles): array
            {
                return [];
            }
        };

        $app = new Application();
        $app->addCommand($command);

        return $command;
    }

    private function makeArticleStub(int $id, string $title): Article
    {
        $article = new Article();
        $article->setTitle($title);
        $ref = new \ReflectionProperty(Article::class, 'id');
        $ref->setValue($article, $id);

        return $article;
    }
}
