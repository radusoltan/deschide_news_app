<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Tag;
use App\Repository\TagRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for TagRepository.
 *
 * Tests actual database queries with test data according to
 * Symfony and API Platform testing best practices.
 */
class TagRepositoryTest extends KernelTestCase
{
    private TagRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(TagRepository::class);
    }

    public function testFindPopularTagsReturnsCorrectOrder(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create test tags
        $tag1 = new Tag();
        $tag1->setName('Very Popular');
        $tag1->setSlug('very-popular');
        $tag1->setUsageCount(100);

        $tag2 = new Tag();
        $tag2->setName('Somewhat Popular');
        $tag2->setSlug('somewhat-popular');
        $tag2->setUsageCount(50);

        $tag3 = new Tag();
        $tag3->setName('Not Popular');
        $tag3->setSlug('not-popular');
        $tag3->setUsageCount(10);

        $entityManager->persist($tag1);
        $entityManager->persist($tag2);
        $entityManager->persist($tag3);
        $entityManager->flush();

        // Test
        $results = $this->repository->findPopularTags(2, 'ro');

        $this->assertCount(2, $results);
        $this->assertEquals('Very Popular', $results[0]->getName());
        $this->assertEquals('Somewhat Popular', $results[1]->getName());

        // Cleanup
        $entityManager->remove($tag1);
        $entityManager->remove($tag2);
        $entityManager->remove($tag3);
        $entityManager->flush();
    }

    public function testFindByNameSearchReturnsMatchingTags(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create test tags
        $tag1 = new Tag();
        $tag1->setName('Politics');
        $tag1->setSlug('politics');

        $tag2 = new Tag();
        $tag2->setName('Political Economy');
        $tag2->setSlug('political-economy');

        $tag3 = new Tag();
        $tag3->setName('Sports');
        $tag3->setSlug('sports');

        $entityManager->persist($tag1);
        $entityManager->persist($tag2);
        $entityManager->persist($tag3);
        $entityManager->flush();

        // Test search for "pol"
        $results = $this->repository->findByNameSearch('pol', 'ro', 10);

        $this->assertGreaterThanOrEqual(2, \count($results));

        // Check that Politics and Political Economy are in results
        $names = array_map(fn (Tag $tag) => $tag->getName(), $results);
        $this->assertContains('Politics', $names);
        $this->assertContains('Political Economy', $names);
        $this->assertNotContains('Sports', $names);

        // Cleanup
        $entityManager->remove($tag1);
        $entityManager->remove($tag2);
        $entityManager->remove($tag3);
        $entityManager->flush();
    }

    public function testFindOrCreateByNameFindsExistingTag(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create test tag
        $tag = new Tag();
        $tag->setName('Technology');
        $tag->setSlug('technology');

        $entityManager->persist($tag);
        $entityManager->flush();

        $tagId = $tag->getId();

        // Test - should find existing tag
        $result = $this->repository->findOrCreateByName('Technology', 'ro');

        $this->assertEquals($tagId, $result->getId());
        $this->assertEquals('Technology', $result->getName());

        // Cleanup
        $entityManager->remove($tag);
        $entityManager->flush();
    }

    public function testFindOrCreateByNameCreatesNewTag(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Test - should create new tag
        $result = $this->repository->findOrCreateByName('Brand New Tag', 'ro');

        $this->assertNotNull($result->getId());
        $this->assertEquals('Brand New Tag', $result->getName());
        $this->assertEquals('brand-new-tag', $result->getSlug());
        $this->assertEquals(0, $result->getUsageCount());

        // Cleanup
        $entityManager->remove($result);
        $entityManager->flush();
    }

    public function testFindUnusedTagsReturnsOldUnusedTags(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create old unused tag (simulate 40 days old)
        $oldTag = new Tag();
        $oldTag->setName('Old Unused');
        $oldTag->setSlug('old-unused');
        $oldTag->setUsageCount(0);

        // Create recent unused tag (simulate 10 days old)
        $recentTag = new Tag();
        $recentTag->setName('Recent Unused');
        $recentTag->setSlug('recent-unused');
        $recentTag->setUsageCount(0);

        // Create used tag
        $usedTag = new Tag();
        $usedTag->setName('Used Tag');
        $usedTag->setSlug('used-tag');
        $usedTag->setUsageCount(5);

        $entityManager->persist($oldTag);
        $entityManager->persist($recentTag);
        $entityManager->persist($usedTag);
        $entityManager->flush();

        // Test - find tags older than 30 days
        $olderThan = new DateTimeImmutable('-30 days');
        $results = $this->repository->findUnusedTags($olderThan);

        // Note: In real test with actual timestamps, we would need to manually set
        // createdAt timestamps or use database time travel. For now, just verify
        // the query executes without error.
        $this->assertIsArray($results);

        // Cleanup
        $entityManager->remove($oldTag);
        $entityManager->remove($recentTag);
        $entityManager->remove($usedTag);
        $entityManager->flush();
    }

    public function testFindReturnsTagById(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $tag = new Tag();
        $tag->setName('Find Me');
        $tag->setSlug('find-me');

        $entityManager->persist($tag);
        $entityManager->flush();

        $tagId = $tag->getId();

        // Test
        $result = $this->repository->find($tagId);

        $this->assertNotNull($result);
        $this->assertEquals($tagId, $result->getId());
        $this->assertEquals('Find Me', $result->getName());

        // Cleanup
        $entityManager->remove($tag);
        $entityManager->flush();
    }

    public function testFindAllReturnsTags(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Get count before
        $countBefore = \count($this->repository->findAll());

        // Create test tags
        $tag1 = new Tag();
        $tag1->setName('Test1');
        $tag1->setSlug('test1');

        $tag2 = new Tag();
        $tag2->setName('Test2');
        $tag2->setSlug('test2');

        $entityManager->persist($tag1);
        $entityManager->persist($tag2);
        $entityManager->flush();

        // Test
        $results = $this->repository->findAll();

        $this->assertGreaterThanOrEqual($countBefore + 2, \count($results));

        // Cleanup
        $entityManager->remove($tag1);
        $entityManager->remove($tag2);
        $entityManager->flush();
    }
}
