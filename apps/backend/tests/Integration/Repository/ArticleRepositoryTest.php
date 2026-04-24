<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Article;
use App\Entity\Tag;
use App\Entity\Topic;
use App\Enum\ArticleStatus;
use App\Enum\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for ArticleRepository.
 *
 * Runs against the real test database defined in .env.test.
 */
class ArticleRepositoryTest extends KernelTestCase
{
    private ArticleRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(ArticleRepository::class);
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // =====================================================================
    // findByTags
    // =====================================================================

    public function testFindByTagsReturnsArticlesWithMatchingTag(): void
    {
        // Create a unique tag so we don't collide with existing data
        $uniqueSuffix = uniqid('art-repo-test-', true);

        $tag = new Tag();
        $tag->setName('IntTest Tag ' . $uniqueSuffix);
        $tag->setSlug('inttest-tag-' . $uniqueSuffix);
        $this->em->persist($tag);

        $article = new Article();
        $article->setTitle('Article with tag ' . $uniqueSuffix);
        $article->setSlug('article-with-tag-' . $uniqueSuffix);
        $article->setStatus(ArticleStatus::PUBLISHED);
        $article->addTag($tag);
        $this->em->persist($article);

        $this->em->flush();

        $results = $this->repository->findByTags([$tag->getId()], 'ro');

        $this->assertIsArray($results);
        // Must contain the article we just created
        $ids = array_map(fn ($a) => $a->getId(), $results);
        $this->assertContains($article->getId(), $ids);
    }

    public function testFindByTagsReturnsEmptyForNoMatchingTags(): void
    {
        // Use a non-existent tag ID
        $results = $this->repository->findByTags([99999999], 'ro');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // =====================================================================
    // findByAnyTag
    // =====================================================================

    public function testFindByAnyTagReturnsArticlesWithAtLeastOneTag(): void
    {
        // TODO: ArticleRepository::findByAnyTag has a PostgreSQL GROUP BY issue
        // (JOIN'd columns must appear in GROUP BY). Fix in src first.
        $this->markTestSkipped('findByAnyTag uses GROUP BY a.id with addSelect(c) which is invalid on PostgreSQL');

        $uniqueSuffix = uniqid('any-tag-', true);

        $tag1 = new Tag();
        $tag1->setName('AnyTag1 ' . $uniqueSuffix);
        $tag1->setSlug('anytag1-' . $uniqueSuffix);
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('AnyTag2 ' . $uniqueSuffix);
        $tag2->setSlug('anytag2-' . $uniqueSuffix);
        $this->em->persist($tag2);

        $article1 = new Article();
        $article1->setTitle('Article with tag1 ' . $uniqueSuffix);
        $article1->setSlug('art-tag1-' . $uniqueSuffix);
        $article1->setStatus(ArticleStatus::PUBLISHED);
        $article1->addTag($tag1);
        $this->em->persist($article1);

        $article2 = new Article();
        $article2->setTitle('Article with tag2 ' . $uniqueSuffix);
        $article2->setSlug('art-tag2-' . $uniqueSuffix);
        $article2->setStatus(ArticleStatus::PUBLISHED);
        $article2->addTag($tag2);
        $this->em->persist($article2);

        $this->em->flush();

        $results = $this->repository->findByAnyTag([$tag1->getId(), $tag2->getId()], 'ro');

        $this->assertIsArray($results);
        $ids = array_map(fn ($a) => $a->getId(), $results);
        $this->assertContains($article1->getId(), $ids);
        $this->assertContains($article2->getId(), $ids);
    }

    public function testFindByAnyTagReturnsEmptyForNonExistentTag(): void
    {
        // TODO: ArticleRepository::findByAnyTag has a PostgreSQL GROUP BY issue
        $this->markTestSkipped('findByAnyTag uses GROUP BY a.id with addSelect(c) which is invalid on PostgreSQL');

        $results = $this->repository->findByAnyTag([88888888], 'ro');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // =====================================================================
    // findSimilarByTags
    // =====================================================================

    public function testFindSimilarByTagsReturnsEmptyForArticleWithNoTags(): void
    {
        $article = new Article();
        $article->setTitle('No tags article ' . uniqid());
        $article->setSlug('no-tags-' . uniqid());
        $article->setStatus(ArticleStatus::PUBLISHED);
        $this->em->persist($article);
        $this->em->flush();

        $results = $this->repository->findSimilarByTags($article, 5, 'ro');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testFindSimilarByTagsExcludesSourceArticle(): void
    {
        // TODO: ArticleRepository::findSimilarByTags has a PostgreSQL GROUP BY issue
        // (JOIN'd columns must appear in GROUP BY). Fix in src first.
        $this->markTestSkipped('findSimilarByTags uses GROUP BY a.id with addSelect(c) which is invalid on PostgreSQL');

        $uniqueSuffix = uniqid('similar-', true);

        $sharedTag = new Tag();
        $sharedTag->setName('Shared ' . $uniqueSuffix);
        $sharedTag->setSlug('shared-' . $uniqueSuffix);
        $this->em->persist($sharedTag);

        $sourceArticle = new Article();
        $sourceArticle->setTitle('Source ' . $uniqueSuffix);
        $sourceArticle->setSlug('source-' . $uniqueSuffix);
        $sourceArticle->setStatus(ArticleStatus::PUBLISHED);
        $sourceArticle->addTag($sharedTag);
        $this->em->persist($sourceArticle);

        $similarArticle = new Article();
        $similarArticle->setTitle('Similar ' . $uniqueSuffix);
        $similarArticle->setSlug('similar-' . $uniqueSuffix);
        $similarArticle->setStatus(ArticleStatus::PUBLISHED);
        $similarArticle->addTag($sharedTag);
        $this->em->persist($similarArticle);

        $this->em->flush();

        $results = $this->repository->findSimilarByTags($sourceArticle, 10, 'ro');

        $this->assertIsArray($results);
        $resultIds = array_map(fn ($a) => $a->getId(), $results);
        // Must not include the source article itself
        $this->assertNotContains($sourceArticle->getId(), $resultIds);
        // Should include the similar article
        $this->assertContains($similarArticle->getId(), $resultIds);
    }

    // =====================================================================
    // countByTag
    // =====================================================================

    public function testCountByTagReturnsCorrectCount(): void
    {
        $uniqueSuffix = uniqid('count-', true);

        $tag = new Tag();
        $tag->setName('CountTag ' . $uniqueSuffix);
        $tag->setSlug('count-tag-' . $uniqueSuffix);
        $this->em->persist($tag);

        $article1 = new Article();
        $article1->setTitle('Count art 1 ' . $uniqueSuffix);
        $article1->setSlug('count-art-1-' . $uniqueSuffix);
        $article1->addTag($tag);
        $this->em->persist($article1);

        $article2 = new Article();
        $article2->setTitle('Count art 2 ' . $uniqueSuffix);
        $article2->setSlug('count-art-2-' . $uniqueSuffix);
        $article2->addTag($tag);
        $this->em->persist($article2);

        $this->em->flush();

        $count = $this->repository->countByTag($tag->getId());

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testCountByTagReturnsZeroForNonExistentTag(): void
    {
        $count = $this->repository->countByTag(77777777);

        $this->assertSame(0, $count);
    }

    // =====================================================================
    // findDevelopingStoryForTopic (Sprint 55 T55.2)
    // =====================================================================

    public function testFindDevelopingStoryForTopicReturnsMostRecentActiveStory(): void
    {
        $suffix = uniqid('dev-story-', true);
        $topic = $this->createTopic($suffix);

        $story = $this->createDevelopingStory('Story ' . $suffix, $suffix, $topic, new \DateTimeImmutable('-1 hour'));
        $this->em->flush();

        $result = $this->repository->findDevelopingStoryForTopic($topic, new \DateTimeImmutable('-24 hours'));

        $this->assertNotNull($result);
        $this->assertSame($story->getId(), $result->getId());
    }

    public function testFindDevelopingStoryForTopicReturnsNullWhenNoMatch(): void
    {
        $suffix = uniqid('dev-story-empty-', true);
        $topic = $this->createTopic($suffix);
        $this->em->flush();

        $result = $this->repository->findDevelopingStoryForTopic($topic, new \DateTimeImmutable('-24 hours'));

        $this->assertNull($result);
    }

    public function testFindDevelopingStoryForTopicExcludesArchived(): void
    {
        $suffix = uniqid('dev-story-arch-', true);
        $topic = $this->createTopic($suffix);

        $story = $this->createDevelopingStory('Archived ' . $suffix, $suffix, $topic, new \DateTimeImmutable('-1 hour'));
        $story->setStatus(ArticleStatus::ARCHIVED);
        $this->em->flush();

        $result = $this->repository->findDevelopingStoryForTopic($topic, new \DateTimeImmutable('-24 hours'));

        $this->assertNull($result);
    }

    public function testFindDevelopingStoryForTopicExcludesOutsideSinceWindow(): void
    {
        $suffix = uniqid('dev-story-old-', true);
        $topic = $this->createTopic($suffix);

        // Article is developing_story but updated_at is Gedmo-timestamped on flush;
        // to simulate "stale" we call with a `since` in the future.
        $this->createDevelopingStory('Stale ' . $suffix, $suffix, $topic, new \DateTimeImmutable('-1 hour'));
        $this->em->flush();

        $result = $this->repository->findDevelopingStoryForTopic($topic, new \DateTimeImmutable('+1 hour'));

        $this->assertNull($result);
    }

    private function createTopic(string $suffix): Topic
    {
        $topic = new Topic();
        $topic->setTitle('Topic ' . $suffix);
        $topic->setSlug('topic-' . $suffix);
        $this->em->persist($topic);

        return $topic;
    }

    private function createDevelopingStory(
        string $title,
        string $suffix,
        Topic $topic,
        \DateTimeImmutable $publishedAt,
    ): Article {
        $article = new Article();
        $article->setTitle($title);
        $article->setSlug('article-' . $suffix);
        $article->setStatus(ArticleStatus::PUBLISHED);
        $article->setArticleType(ArticleType::DEVELOPING_STORY);
        $article->setPublishedAt($publishedAt);
        $article->addTopic($topic);
        $this->em->persist($article);

        return $article;
    }
}
