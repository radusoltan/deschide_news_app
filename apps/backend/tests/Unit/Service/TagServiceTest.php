<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Article;
use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Service\TagService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TagService.
 *
 * Tests business logic for tag management, article-tag relationships,
 * and usage count tracking according to Symfony best practices.
 */
class TagServiceTest extends TestCase
{
    private TagService $service;

    private EntityManagerInterface $entityManager;

    private TagRepository $tagRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tagRepository = $this->createMock(TagRepository::class);

        $this->service = new TagService(
            $this->entityManager,
            $this->tagRepository
        );
    }

    // ======================
    // syncArticleTags Tests
    // ======================

    #[Test]
    public function itSyncsArticleTagsSuccessfully(): void
    {
        $article = $this->createMock(Article::class);
        $existingTag = $this->createTag(1, 'OldTag');
        $existingTag->setUsageCount(1);

        // Article initially has one tag
        $article->method('getTags')->willReturn(new ArrayCollection([$existingTag]));

        // Mock tag repository to return new tag
        $newTag = $this->createTag(2, 'NewTag');
        $this->tagRepository->method('findOrCreateByName')
            ->with('NewTag', 'ro')
            ->willReturn($newTag);

        // Expect removeTag to be called for old tag
        $article->expects($this->once())
            ->method('removeTag')
            ->with($existingTag);

        // Expect addTag to be called for new tag
        $article->expects($this->once())
            ->method('addTag')
            ->with($newTag);

        // Expect flush to be called
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->syncArticleTags($article, ['NewTag'], 'ro');

        // Old tag usage count should be decremented
        $this->assertEquals(0, $existingTag->getUsageCount());
    }

    #[Test]
    public function itSkipsEmptyTagNames(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getTags')->willReturn(new ArrayCollection());

        // Should not call findOrCreateByName for empty strings
        $this->tagRepository->expects($this->never())
            ->method('findOrCreateByName');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->syncArticleTags($article, ['', '  ', null], 'ro');
    }

    // ======================
    // addTagsToArticle Tests
    // ======================

    #[Test]
    public function itAddsTagsToArticleWithoutRemovingExisting(): void
    {
        $article = $this->createMock(Article::class);
        $existingTag = $this->createTag(1, 'Existing');

        $existingTags = new ArrayCollection([$existingTag]);
        $article->method('getTags')->willReturn($existingTags);

        $newTag = $this->createTag(2, 'NewTag');
        $this->tagRepository->method('findOrCreateByName')
            ->with('NewTag', 'ro')
            ->willReturn($newTag);

        // Should call addTag for new tag
        $article->expects($this->once())
            ->method('addTag')
            ->with($newTag);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->addTagsToArticle($article, ['NewTag'], 'ro');

        // New tag usage count should be incremented
        $this->assertEquals(1, $newTag->getUsageCount());
    }

    #[Test]
    public function itDoesNotAddDuplicateTags(): void
    {
        $tag = $this->createTag(1, 'ExistingTag');
        $tag->setUsageCount(5);

        $article = $this->createMock(Article::class);
        $tags = new ArrayCollection([$tag]);
        $article->method('getTags')->willReturn($tags);

        $this->tagRepository->method('findOrCreateByName')
            ->willReturn($tag);

        // addTag should not be called since tag already exists
        $article->expects($this->never())
            ->method('addTag');

        $this->service->addTagsToArticle($article, ['ExistingTag'], 'ro');

        // Usage count should not be incremented
        $this->assertEquals(5, $tag->getUsageCount());
    }

    // ======================
    // removeTagsFromArticle Tests
    // ======================

    #[Test]
    public function itRemovesTagsByIdFromArticle(): void
    {
        $tag = $this->createTag(1, 'TestTag');
        $tag->setUsageCount(10);

        $article = $this->createMock(Article::class);
        $tags = new ArrayCollection([$tag]);
        $article->method('getTags')->willReturn($tags);

        $this->tagRepository->method('find')
            ->with(1)
            ->willReturn($tag);

        // Expect removeTag to be called
        $article->expects($this->once())
            ->method('removeTag')
            ->with($tag);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->removeTagsFromArticle($article, [1], 'ro');

        // Usage count should be decremented
        $this->assertEquals(9, $tag->getUsageCount());
    }

    #[Test]
    public function itRemovesTagsByNameFromArticle(): void
    {
        $tag = $this->createTag(1, 'TestTag');
        $tag->setUsageCount(5);

        $article = $this->createMock(Article::class);
        $tags = new ArrayCollection([$tag]);
        $article->method('getTags')->willReturn($tags);

        $this->tagRepository->method('findByNameSearch')
            ->with('TestTag', 'ro', 1)
            ->willReturn([$tag]);

        $article->expects($this->once())
            ->method('removeTag')
            ->with($tag);

        $this->service->removeTagsFromArticle($article, ['TestTag'], 'ro');

        $this->assertEquals(4, $tag->getUsageCount());
    }

    // ======================
    // getPopularTags Tests
    // ======================

    #[Test]
    public function itReturnsPopularTags(): void
    {
        $tag1 = $this->createTag(1, 'Popular1');
        $tag1->setUsageCount(100);

        $tag2 = $this->createTag(2, 'Popular2');
        $tag2->setUsageCount(50);

        $this->tagRepository->method('findPopularTags')
            ->with(20, 'ro')
            ->willReturn([$tag1, $tag2]);

        $result = $this->service->getPopularTags('ro', 20);

        $this->assertCount(2, $result);
        $this->assertEquals('Popular1', $result[0]->getName());
        $this->assertEquals('Popular2', $result[1]->getName());
    }

    // ======================
    // searchTags Tests
    // ======================

    #[Test]
    public function itSearchesTagsByQuery(): void
    {
        $tag = $this->createTag(1, 'Politics');

        $this->tagRepository->method('findByNameSearch')
            ->with('pol', 'ro', 10)
            ->willReturn([$tag]);

        $result = $this->service->searchTags('pol', 'ro', 10);

        $this->assertCount(1, $result);
        $this->assertEquals('Politics', $result[0]->getName());
    }

    // ======================
    // recalculateUsageCounts Tests
    // ======================

    #[Test]
    public function itRecalculatesUsageCountsCorrectly(): void
    {
        $tag1 = $this->createTag(1, 'Tag1');
        $tag1->setUsageCount(999); // Incorrect count

        $tag2 = $this->createTag(2, 'Tag2');
        $tag2->setUsageCount(999); // Incorrect count

        // Tag1 has 3 articles, Tag2 has 0
        $articles1 = new ArrayCollection([
            $this->createMock(Article::class),
            $this->createMock(Article::class),
            $this->createMock(Article::class),
        ]);
        $tag1->method('getArticles')->willReturn($articles1);

        $articles2 = new ArrayCollection();
        $tag2->method('getArticles')->willReturn($articles2);

        $this->tagRepository->method('findAll')
            ->willReturn([$tag1, $tag2]);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $updated = $this->service->recalculateUsageCounts();

        $this->assertEquals(2, $updated);
        $this->assertEquals(3, $tag1->getUsageCount());
        $this->assertEquals(0, $tag2->getUsageCount());
    }

    // ======================
    // getOrCreateTag Tests
    // ======================

    #[Test]
    public function itCreatesNewTagWhenNotFound(): void
    {
        $this->tagRepository->method('findOrCreateByName')
            ->with('NewTag', 'ro')
            ->willReturn($this->createTag(1, 'NewTag'));

        $result = $this->service->getOrCreateTag('NewTag', 'ro');

        $this->assertInstanceOf(Tag::class, $result);
        $this->assertEquals('NewTag', $result->getName());
    }

    // ======================
    // getTagStatistics Tests
    // ======================

    #[Test]
    public function itReturnsTagStatistics(): void
    {
        $tags = [
            $this->createTag(1, 'Tag1'),
            $this->createTag(2, 'Tag2'),
            $this->createTag(3, 'Tag3'),
        ];

        $tags[0]->setUsageCount(10);
        $tags[1]->setUsageCount(5);
        $tags[2]->setUsageCount(0);

        $this->tagRepository->method('findAll')
            ->willReturn($tags);

        $stats = $this->service->getTagStatistics();

        $this->assertEquals(3, $stats['totalTags']);
        $this->assertEquals(15, $stats['totalUsages']);
        $this->assertEquals(5, $stats['averageUsage']);
        $this->assertEquals(2, $stats['tagsInUse']);
        $this->assertEquals(1, $stats['unusedTags']);
        $this->assertInstanceOf(Tag::class, $stats['mostUsedTag']);
        $this->assertEquals('Tag1', $stats['mostUsedTag']->getName());
    }

    #[Test]
    public function itHandlesEmptyTagStatistics(): void
    {
        $this->tagRepository->method('findAll')
            ->willReturn([]);

        $stats = $this->service->getTagStatistics();

        $this->assertEquals(0, $stats['totalTags']);
        $this->assertEquals(0, $stats['totalUsages']);
        $this->assertEquals(0, $stats['averageUsage']);
        $this->assertEquals(0, $stats['tagsInUse']);
        $this->assertEquals(0, $stats['unusedTags']);
        $this->assertNull($stats['mostUsedTag']);
    }

    // ======================
    // mergeTags Tests
    // ======================

    #[Test]
    public function itMergesTagsSuccessfully(): void
    {
        $sourceTag = $this->createTag(1, 'SourceTag');
        $targetTag = $this->createTag(2, 'TargetTag');

        $article1 = $this->createMock(Article::class);
        $article2 = $this->createMock(Article::class);

        $sourceArticles = new ArrayCollection([$article1, $article2]);
        $sourceTag->method('getArticles')->willReturn($sourceArticles);

        $targetArticles = new ArrayCollection();
        $targetTag->method('getArticles')->willReturn($targetArticles);

        // Setup article tags collections
        $article1Tags = new ArrayCollection();
        $article2Tags = new ArrayCollection();

        $article1->method('getTags')->willReturn($article1Tags);
        $article2->method('getTags')->willReturn($article2Tags);

        // Expect addTag to be called for both articles
        $article1->expects($this->once())
            ->method('addTag')
            ->with($targetTag);

        $article2->expects($this->once())
            ->method('addTag')
            ->with($targetTag);

        // Expect removeTag to be called for both articles
        $article1->expects($this->once())
            ->method('removeTag')
            ->with($sourceTag);

        $article2->expects($this->once())
            ->method('removeTag')
            ->with($sourceTag);

        // Expect source tag to be removed
        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($sourceTag);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->mergeTags($sourceTag, $targetTag);
    }

    // ======================
    // Helper Methods
    // ======================

    private function createTag(int $id, string $name): Tag
    {
        $tag = $this->getMockBuilder(Tag::class)
            ->onlyMethods(['getId', 'getArticles'])
            ->getMock();

        $tag->method('getId')->willReturn($id);
        $tag->setName($name);
        $tag->setSlug(strtolower($name));

        return $tag;
    }
}
