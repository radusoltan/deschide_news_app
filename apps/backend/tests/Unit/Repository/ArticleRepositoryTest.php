<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\Article;
use App\Entity\Tag;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ArticleRepository.
 *
 * Tests early-return logic that does not require DB queries.
 * See tests/Integration/Repository/ArticleRepositoryTest.php for full DB integration tests.
 */
class ArticleRepositoryTest extends TestCase
{
    private ArticleRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(Article::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new ArticleRepository($registry);
    }

    // =============================================
    // findSimilarByTags – empty tags early return
    // =============================================

    #[Test]
    public function findSimilarByTagsReturnsEmptyArrayWhenArticleHasNoTags(): void
    {
        $article = new Article();

        $result = $this->repository->findSimilarByTags($article, 5, 'ro');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function findSimilarByTagsReturnsEmptyArrayWhenArticleHasEmptyTagsCollection(): void
    {
        $article = new Article();
        // Article's tags collection is empty by default (ArrayCollection)

        $result = $this->repository->findSimilarByTags($article, 10, 'en');

        $this->assertSame([], $result);
    }

    #[Test]
    public function findSimilarByTagsReturnsEmptyArrayForDifferentLocales(): void
    {
        $article = new Article();

        $resultRo = $this->repository->findSimilarByTags($article, 5, 'ro');
        $resultEn = $this->repository->findSimilarByTags($article, 5, 'en');
        $resultRu = $this->repository->findSimilarByTags($article, 5, 'ru');

        $this->assertEmpty($resultRo);
        $this->assertEmpty($resultEn);
        $this->assertEmpty($resultRu);
    }
}
