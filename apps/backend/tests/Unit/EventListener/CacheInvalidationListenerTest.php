<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Image;
use App\EventListener\CacheInvalidationListener;
use App\Service\PerformanceService;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CacheInvalidationListenerTest extends TestCase
{
    private PerformanceService $performanceService;
    private CacheInvalidationListener $listener;

    protected function setUp(): void
    {
        $this->performanceService = $this->createMock(PerformanceService::class);
        $this->listener = new CacheInvalidationListener($this->performanceService);
    }

    public function testPostPersistInvalidatesArticleCache(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(42);

        $em = $this->createStub(EntityManagerInterface::class);
        $args = new PostPersistEventArgs($article, $em);

        $this->performanceService->expects($this->once())
            ->method('invalidateArticle')
            ->with(42);

        $this->listener->postPersist($args);
    }

    public function testPostPersistInvalidatesCategoryCache(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(7);

        $em = $this->createStub(EntityManagerInterface::class);
        $args = new PostPersistEventArgs($category, $em);

        $this->performanceService->expects($this->once())
            ->method('invalidateCategory')
            ->with(7);

        $this->listener->postPersist($args);
    }

    public function testPostUpdateInvalidatesArticleCache(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(10);

        $em = $this->createStub(EntityManagerInterface::class);
        $args = new PostUpdateEventArgs($article, $em);

        $this->performanceService->expects($this->once())
            ->method('invalidateArticle')
            ->with(10);

        $this->listener->postUpdate($args);
    }

    public function testPostUpdateInvalidatesCategoryCache(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(3);

        $em = $this->createStub(EntityManagerInterface::class);
        $args = new PostUpdateEventArgs($category, $em);

        $this->performanceService->expects($this->once())
            ->method('invalidateCategory')
            ->with(3);

        $this->listener->postUpdate($args);
    }

    public function testPreRemoveInvalidatesArticleCache(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(99);

        $em = $this->createStub(EntityManagerInterface::class);
        $args = new PreRemoveEventArgs($article, $em);

        $this->performanceService->expects($this->once())
            ->method('invalidateArticle')
            ->with(99);

        $this->listener->preRemove($args);
    }

    public function testPreRemoveInvalidatesCategoryCache(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(5);

        $em = $this->createStub(EntityManagerInterface::class);
        $args = new PreRemoveEventArgs($category, $em);

        $this->performanceService->expects($this->once())
            ->method('invalidateCategory')
            ->with(5);

        $this->listener->preRemove($args);
    }

    public function testIgnoresNonArticleNonCategoryEntities(): void
    {
        $image = $this->createStub(Image::class);

        $em = $this->createStub(EntityManagerInterface::class);
        $args = new PostPersistEventArgs($image, $em);

        $this->performanceService->expects($this->never())
            ->method('invalidateArticle');
        $this->performanceService->expects($this->never())
            ->method('invalidateCategory');

        $this->listener->postPersist($args);
    }
}
