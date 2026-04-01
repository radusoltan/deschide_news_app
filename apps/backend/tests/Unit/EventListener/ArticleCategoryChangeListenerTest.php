<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Article;
use App\Entity\Category;
use App\EventListener\ArticleCategoryChangeListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ArticleCategoryChangeListenerTest extends TestCase
{
    public function testPreUpdateIgnoresNonArticleEntities(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $listener = new ArticleCategoryChangeListener($em, $logger);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn(new \stdClass());
        $args->expects($this->never())->method('hasChangedField');

        $listener->preUpdate($args);
    }

    public function testPreUpdateIgnoresWhenCategoryNotChanged(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $listener = new ArticleCategoryChangeListener($em, $logger);

        $article = $this->createStub(Article::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('category')->willReturn(false);
        $args->expects($this->never())->method('getOldValue');

        $listener->preUpdate($args);
    }

    public function testPreUpdateIgnoresWhenOldCategoryIsNull(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $listener = new ArticleCategoryChangeListener($em, $logger);

        $article = $this->createStub(Article::class);
        $newCategory = $this->createMock(Category::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('category')->willReturn(true);
        $args->method('getOldValue')->with('category')->willReturn(null);
        $args->method('getNewValue')->with('category')->willReturn($newCategory);

        // Should not log (no redirect scheduled)
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $listener = new ArticleCategoryChangeListener($em, $logger);
        $listener->preUpdate($args);
    }

    public function testPreUpdateIgnoresWhenSameCategory(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $oldCategory = $this->createMock(Category::class);
        $oldCategory->method('getId')->willReturn(5);
        $newCategory = $this->createMock(Category::class);
        $newCategory->method('getId')->willReturn(5); // same ID

        $article = $this->createStub(Article::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('category')->willReturn(true);
        $args->method('getOldValue')->with('category')->willReturn($oldCategory);
        $args->method('getNewValue')->with('category')->willReturn($newCategory);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $listener = new ArticleCategoryChangeListener($em, $logger);
        $listener->preUpdate($args);
    }

    public function testPreUpdateSchedulesRedirectWhenCategoryChanges(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $oldCategory = $this->createMock(Category::class);
        $oldCategory->method('getId')->willReturn(1);
        $newCategory = $this->createMock(Category::class);
        $newCategory->method('getId')->willReturn(2);

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(10);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('category')->willReturn(true);
        $args->method('getOldValue')->with('category')->willReturn($oldCategory);
        $args->method('getNewValue')->with('category')->willReturn($newCategory);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with($this->stringContains('category change detected'));

        $listener = new ArticleCategoryChangeListener($em, $logger);
        $listener->preUpdate($args);
    }

    public function testPostFlushDoesNothingWhenNoPendingRedirects(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $listener = new ArticleCategoryChangeListener($em, $logger);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->expects($this->never())->method('persist');

        $args = $this->createMock(PostFlushEventArgs::class);
        $args->method('getObjectManager')->willReturn($innerEm);

        $listener->postFlush($args);
    }

    public function testPreUpdateIgnoresWhenNewCategoryIsNull(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $oldCategory = $this->createMock(Category::class);

        $article = $this->createStub(Article::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('category')->willReturn(true);
        $args->method('getOldValue')->with('category')->willReturn($oldCategory);
        $args->method('getNewValue')->with('category')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $listener = new ArticleCategoryChangeListener($em, $logger);
        $listener->preUpdate($args);
    }

    public function testPostFlushWithExistingRedirectUpdatesIt(): void
    {
        $oldCategory = $this->createMock(Category::class);
        $oldCategory->method('getId')->willReturn(1);
        $oldCategory->method('getSlug')->willReturn('politica');

        $newCategory = $this->createMock(Category::class);
        $newCategory->method('getId')->willReturn(2);
        $newCategory->method('getSlug')->willReturn('economie');

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(10);
        $article->method('getSlug')->willReturn('articol-test');

        // Mock the query builder chain for getTranslatedSlug
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getOneOrNullResult')->willReturn(null);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        // Existing redirect found
        $existingRedirect = $this->createMock(\App\Entity\UrlRedirect::class);
        $existingRedirect->expects($this->atLeastOnce())->method('setNewUrl');

        $urlRedirectRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $urlRedirectRepo->method('findOneBy')->willReturn($existingRedirect);

        $categoryRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $categoryRepo->method('createQueryBuilder')->willReturn($qb);

        $articleRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $articleRepo->method('createQueryBuilder')->willReturn($qb);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturnCallback(function (string $class) use ($urlRedirectRepo, $categoryRepo, $articleRepo) {
            if (str_contains($class, 'UrlRedirect')) {
                return $urlRedirectRepo;
            }
            if (str_contains($class, 'Category')) {
                return $categoryRepo;
            }
            return $articleRepo;
        });
        // persist should NOT be called because we update existing
        $innerEm->expects($this->never())->method('persist');

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(false);
        $innerEm->method('getUnitOfWork')->willReturn($uow);

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createStub(LoggerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new ArticleCategoryChangeListener($em, $logger);

        // Schedule redirect
        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('category')->willReturn(true);
        $args->method('getOldValue')->with('category')->willReturn($oldCategory);
        $args->method('getNewValue')->with('category')->willReturn($newCategory);
        $listener->preUpdate($args);

        $listener->postFlush($postFlushArgs);
    }

    public function testPostFlushHandlesExceptionDuringRedirectCreation(): void
    {
        $oldCategory = $this->createMock(Category::class);
        $oldCategory->method('getId')->willReturn(1);
        $oldCategory->method('getSlug')->willReturn('politica');

        $newCategory = $this->createMock(Category::class);
        $newCategory->method('getId')->willReturn(2);
        $newCategory->method('getSlug')->willReturn('economie');

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(10);

        // Repository throws exception when querying translated slug
        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('createQueryBuilder')->willThrowException(new \RuntimeException('DB error'));
        $repo->method('findOneBy')->willReturn(null);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturn($repo);

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(false);
        $innerEm->method('getUnitOfWork')->willReturn($uow);

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createMock(LoggerInterface::class);
        // error() should be called for each locale that fails (3 locales)
        $logger->expects($this->exactly(3))
            ->method('error')
            ->with($this->stringContains('Failed to create redirect'), $this->anything());

        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new ArticleCategoryChangeListener($em, $logger);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('category')->willReturn(true);
        $args->method('getOldValue')->with('category')->willReturn($oldCategory);
        $args->method('getNewValue')->with('category')->willReturn($newCategory);
        $listener->preUpdate($args);

        // Should not throw
        $listener->postFlush($postFlushArgs);
    }

    public function testPostFlushFlushesWhenPendingInsertions(): void
    {
        $oldCategory = $this->createMock(Category::class);
        $oldCategory->method('getId')->willReturn(1);
        $oldCategory->method('getSlug')->willReturn('politica');

        $newCategory = $this->createMock(Category::class);
        $newCategory->method('getId')->willReturn(2);
        $newCategory->method('getSlug')->willReturn('economie');

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(10);
        $article->method('getSlug')->willReturn('test');

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getOneOrNullResult')->willReturn(null);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $urlRedirectRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $urlRedirectRepo->method('findOneBy')->willReturn(null);

        $entityRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $entityRepo->method('createQueryBuilder')->willReturn($qb);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturnCallback(function (string $class) use ($urlRedirectRepo, $entityRepo) {
            if (str_contains($class, 'UrlRedirect')) {
                return $urlRedirectRepo;
            }
            return $entityRepo;
        });

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(true); // Has pending insertions
        $innerEm->method('getUnitOfWork')->willReturn($uow);
        $innerEm->expects($this->once())->method('flush'); // Should flush

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createStub(LoggerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new ArticleCategoryChangeListener($em, $logger);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('category')->willReturn(true);
        $args->method('getOldValue')->with('category')->willReturn($oldCategory);
        $args->method('getNewValue')->with('category')->willReturn($newCategory);
        $listener->preUpdate($args);

        $listener->postFlush($postFlushArgs);
    }

    public function testPostFlushClearsPendingRedirectsAfterProcessing(): void
    {
        // Set up old category with ID 1
        $oldCategory = $this->createMock(Category::class);
        $oldCategory->method('getId')->willReturn(1);
        $oldCategory->method('getSlug')->willReturn('politica');

        // Set up new category with ID 2
        $newCategory = $this->createMock(Category::class);
        $newCategory->method('getId')->willReturn(2);
        $newCategory->method('getSlug')->willReturn('economie');

        // Set up article
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(10);
        $article->method('getSlug')->willReturn('articol-test');

        // Set up a mock QueryBuilder chain that returns null (no existing redirect)
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getOneOrNullResult')->willReturn(null);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        // Stub the UrlRedirect repository to return null (no existing redirect)
        $urlRedirectRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $urlRedirectRepo->method('findOneBy')->willReturn(null);

        // Stub entity repos for category and article translations
        $categoryRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $categoryRepo->method('createQueryBuilder')->willReturn($qb);

        $articleRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $articleRepo->method('createQueryBuilder')->willReturn($qb);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturnCallback(function (string $class) use ($urlRedirectRepo, $categoryRepo, $articleRepo) {
            if (str_contains($class, 'UrlRedirect')) {
                return $urlRedirectRepo;
            }
            if (str_contains($class, 'Category')) {
                return $categoryRepo;
            }

            return $articleRepo;
        });

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(false);
        $innerEm->method('getUnitOfWork')->willReturn($uow);
        // persist() returns void — no willReturn needed

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createStub(LoggerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new ArticleCategoryChangeListener($em, $logger);

        // Schedule a redirect via preUpdate
        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('category')->willReturn(true);
        $args->method('getOldValue')->with('category')->willReturn($oldCategory);
        $args->method('getNewValue')->with('category')->willReturn($newCategory);
        $listener->preUpdate($args);

        // Now flush — should process 3 locales
        $listener->postFlush($postFlushArgs);

        // A second postFlush should do nothing since pendingRedirects was cleared
        $innerEm2 = $this->createMock(EntityManagerInterface::class);
        $innerEm2->expects($this->never())->method('persist');
        $postFlushArgs2 = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs2->method('getObjectManager')->willReturn($innerEm2);

        $listener->postFlush($postFlushArgs2);
    }
}
