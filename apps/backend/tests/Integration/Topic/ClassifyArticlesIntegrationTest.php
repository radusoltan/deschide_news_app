<?php

declare(strict_types=1);

namespace App\Tests\Integration\Topic;

use App\Command\Topic\ClassifyArticlesCommand;
use App\Entity\Article;
use App\Entity\Topic;
use App\Enum\ArticleStatus;
use App\Service\Topic\BatchTopicClassifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * End-to-end integration for the Sprint 51c backfill plumbing.
 *
 * Exercises the real EntityManager, the real NOT EXISTS filter on
 * article_topics, and the real CLI, but stubs BatchTopicClassifier so the
 * test doesn't hit Gemini (that integration lives in
 * ClassifyArticlesGeminiIntegrationTest, @group gemini, opt-in).
 */
class ClassifyArticlesIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    /** @var array<int, int> */
    private array $articleIdsToClean = [];

    /** @var array<int, int> */
    private array $topicIdsToClean = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        foreach ($this->articleIdsToClean as $id) {
            $this->em->getConnection()->executeStatement(
                'DELETE FROM article_topics WHERE article_id = :id',
                ['id' => $id],
            );
            $this->em->getConnection()->executeStatement(
                'DELETE FROM articles WHERE id = :id',
                ['id' => $id],
            );
        }
        foreach ($this->topicIdsToClean as $id) {
            $this->em->getConnection()->executeStatement(
                'DELETE FROM topics WHERE id = :id',
                ['id' => $id],
            );
        }

        $this->em->close();
        parent::tearDown();
    }

    public function testCommandFiltersOutAlreadyClassifiedArticles(): void
    {
        $classified = $this->seedArticle('t51c7-int-classified-' . uniqid());
        $unclassified = $this->seedArticle('t51c7-int-unclassified-' . uniqid());
        $topic = $this->seedTopic('t51c7-int-topic-' . uniqid());

        $classified->addTopic($topic);
        $this->em->flush();
        $this->em->clear();

        $classifier = static::getContainer()->get(BatchTopicClassifier::class);
        self::assertInstanceOf(BatchTopicClassifier::class, $classifier);

        $command = new ClassifyArticlesCommand($this->em, $classifier);
        $command->setName('app:articles:classify-topics');
        $tester = new CommandTester($command);

        // dry-run: the classifier stays untouched, but the SELECT must be
        // executed against the real DB so we can prove the NOT EXISTS filter.
        $tester->execute(['--dry-run' => true, '--limit' => 100]);

        $display = $tester->getDisplay();
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('DRY RUN', $display);
        self::assertStringContainsString('#' . $unclassified->getId(), $display, 'Unclassified article must be listed');
        self::assertStringNotContainsString('#' . $classified->getId(), $display, 'Classified article must be skipped');
    }

    public function testClassifierAttachesTopicsToUnclassifiedArticles(): void
    {
        $article = $this->seedArticle('t51c7-int-run-' . uniqid());
        $topicA = $this->seedTopic('t51c7-int-topicA-' . uniqid());
        $topicB = $this->seedTopic('t51c7-int-topicB-' . uniqid());
        $topicIdA = $topicA->getId();
        $topicIdB = $topicB->getId();

        $classifier = static::getContainer()->get(BatchTopicClassifier::class);
        self::assertInstanceOf(BatchTopicClassifier::class, $classifier);

        // Inject a stub that returns a deterministic mapping for our article.
        $stub = new class($this->em, $topicIdA, $topicIdB) extends BatchTopicClassifier {
            public function __construct(
                private readonly EntityManagerInterface $em2,
                private readonly int $topicIdA,
                private readonly int $topicIdB,
            ) {
                // Intentionally skip parent::__construct — we override classifyBatch.
            }

            public function classifyBatch(array $articles, int $batchSize = 20, ?callable $onProgress = null): \App\Service\Topic\BatchResult
            {
                $result = new \App\Service\Topic\BatchResult();
                $topicA = $this->em2->find(Topic::class, $this->topicIdA);
                $topicB = $this->em2->find(Topic::class, $this->topicIdB);
                foreach ($articles as $article) {
                    $article->addTopic($topicA);
                    $article->addTopic($topicB);
                    $result->classified++;
                    $result->totalAssignments += 2;
                }
                $this->em2->flush();
                if ($onProgress !== null) {
                    $onProgress(1, 1, $result);
                }

                return $result;
            }
        };

        $command = new ClassifyArticlesCommand($this->em, $stub);
        $command->setName('app:articles:classify-topics');
        $tester = new CommandTester($command);
        // Limit must exceed the count of pre-existing unclassified PUBLISHED
        // articles left over by sibling integration tests; otherwise the
        // ORDER BY id ASC + LIMIT window excludes the seeded article and the
        // stub never sees it. Sibling testCommandFiltersOutAlreadyClassified...
        // uses --limit 100 for the same reason. Long-term DAMA isolation
        // tracked under T52.15.
        $tester->execute(['--limit' => 100]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Backfill Summary', $tester->getDisplay());

        // Article now has both topics linked.
        $this->em->clear();
        /** @var Article $reloaded */
        $reloaded = $this->em->find(Article::class, $article->getId());
        self::assertCount(2, $reloaded->getTopics());
    }

    private function seedArticle(string $slug): Article
    {
        $article = new Article();
        $article->setTranslatableLocale('ro');
        $article->setTitle('T51c.7 integration ' . $slug);
        $article->setSlug($slug);
        $article->setStatus(ArticleStatus::PUBLISHED);
        $article->setPublishedAt(new \DateTimeImmutable());
        $article->setPublishedLocales(['ro']);
        $this->em->persist($article);
        $this->em->flush();

        $this->articleIdsToClean[] = $article->getId();

        return $article;
    }

    private function seedTopic(string $slug): Topic
    {
        $topic = new Topic();
        $topic->setTranslatableLocale('ro');
        $topic->setTitle(ucfirst(str_replace('-', ' ', $slug)));
        $topic->setSlug($slug);
        $topic->setIsActive(true);
        $this->em->persist($topic);
        $this->em->flush();

        $this->topicIdsToClean[] = $topic->getId();

        return $topic;
    }
}
