<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Topic;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Topic;
use App\Repository\TopicRepository;
use App\Service\Topic\RuleBasedTopicClassifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(RuleBasedTopicClassifier::class)]
class RuleBasedTopicClassifierTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private TopicRepository&MockObject $topicRepo;
    private RuleBasedTopicClassifier $classifier;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->topicRepo = $this->createMock(TopicRepository::class);

        $this->classifier = new RuleBasedTopicClassifier(
            $this->em,
            $this->topicRepo,
            new NullLogger(),
        );
    }

    public function testClassifyPoliticaCategory(): void
    {
        $topic = $this->createTopic(1, 'politica');
        $this->topicRepo->method('findOneBy')
            ->with(['slug' => 'politica'])
            ->willReturn($topic);

        $article = $this->createArticleWithCategory('Politică');

        $result = $this->classifier->classify($article);

        self::assertCount(1, $result);
        self::assertSame('politica', $result[0]->getSlug());
    }

    public function testClassifyEconomieCategory(): void
    {
        $topic = $this->createTopic(11, 'economie');
        $this->topicRepo->method('findOneBy')
            ->with(['slug' => 'economie'])
            ->willReturn($topic);

        $article = $this->createArticleWithCategory('Economie');

        $result = $this->classifier->classify($article);

        self::assertCount(1, $result);
        self::assertSame('economie', $result[0]->getSlug());
    }

    public function testSkipsSocietateCategory(): void
    {
        $article = $this->createArticleWithCategory('Societate');

        $result = $this->classifier->classify($article);

        self::assertSame([], $result);
    }

    public function testSkipsExterneCategory(): void
    {
        $article = $this->createArticleWithCategory('Externe');

        $result = $this->classifier->classify($article);

        self::assertSame([], $result);
    }

    public function testSkipsEditorialeCategory(): void
    {
        $article = $this->createArticleWithCategory('Editoriale');

        $result = $this->classifier->classify($article);

        self::assertSame([], $result);
    }

    public function testSkipsArticleWithExistingTopics(): void
    {
        $existingTopic = $this->createTopic(99, 'existing');
        $article = $this->createArticleWithCategory('Politică', [$existingTopic]);

        $result = $this->classifier->classify($article);

        self::assertSame([], $result);
    }

    public function testSkipsArticleWithNoCategory(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getTopics')->willReturn(new ArrayCollection());
        $article->method('getCategory')->willReturn(null);

        $result = $this->classifier->classify($article);

        self::assertSame([], $result);
    }

    public function testClassifyBatchReturnsCount(): void
    {
        $politicaTopic = $this->createTopic(1, 'politica');
        $alegeriTopic = $this->createTopic(2, 'alegeri');

        $this->topicRepo->method('findOneBy')
            ->willReturnCallback(fn(array $criteria) => match ($criteria['slug']) {
                'politica' => $politicaTopic,
                'alegeri' => $alegeriTopic,
                default => null,
            });

        $this->em->expects(self::atLeastOnce())->method('flush');

        $articles = [
            $this->createArticleWithCategory('Politică'),
            $this->createArticleWithCategory('Societate'), // skipped
            $this->createArticleWithCategory('Alegeri'),
        ];

        $count = $this->classifier->classifyBatch($articles);

        self::assertSame(2, $count);
    }

    public function testGetHandledCategories(): void
    {
        $handled = RuleBasedTopicClassifier::getHandledCategories();

        self::assertContains('Politică', $handled);
        self::assertContains('Economie', $handled);
        self::assertContains('Alegeri', $handled);
        self::assertNotContains('Societate', $handled);
        self::assertNotContains('Externe', $handled);
        self::assertNotContains('Editoriale', $handled);
    }

    public function testGetAiCategories(): void
    {
        $aiCats = RuleBasedTopicClassifier::getAiCategories();

        self::assertContains('Societate', $aiCats);
        self::assertContains('Externe', $aiCats);
        self::assertCount(2, $aiCats);
    }

    private function createTopic(int $id, string $slug): Topic
    {
        $topic = new Topic();
        $ref = new \ReflectionProperty(Topic::class, 'id');
        $ref->setValue($topic, $id);
        $topic->setTitle($slug);

        $slugRef = new \ReflectionProperty(Topic::class, 'slug');
        $slugRef->setValue($topic, $slug);

        return $topic;
    }

    /**
     * @param Topic[] $existingTopics
     */
    private function createArticleWithCategory(string $categoryTitle, array $existingTopics = []): Article
    {
        $category = $this->createMock(Category::class);
        $category->method('getTitle')->willReturn($categoryTitle);

        $article = $this->createMock(Article::class);
        $article->method('getTopics')->willReturn(new ArrayCollection($existingTopics));
        $article->method('getCategory')->willReturn($category);

        return $article;
    }
}
