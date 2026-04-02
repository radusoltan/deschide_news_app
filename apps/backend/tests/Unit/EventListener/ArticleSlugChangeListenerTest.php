<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Article;
use App\Entity\Category;
use App\EventListener\ArticleSlugChangeListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ArticleSlugChangeListenerTest extends TestCase
{
    public function testPreUpdateIgnoresNonArticleEntities(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $listener = new ArticleSlugChangeListener($em, $logger);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn(new \stdClass());
        $args->expects($this->never())->method('hasChangedField');

        $listener->preUpdate($args);
    }

    public function testPreUpdateIgnoresWhenSlugNotChanged(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $listener = new ArticleSlugChangeListener($em, $logger);

        $article = $this->createStub(Article::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('slug')->willReturn(false);
        $args->expects($this->never())->method('getOldValue');

        $listener->preUpdate($args);
    }

    public function testPreUpdateIgnoresWhenOldSlugEmpty(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $listener = new ArticleSlugChangeListener($em, $logger);

        $article = $this->createStub(Article::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('slug')->willReturn(true);
        $args->method('getOldValue')->with('slug')->willReturn('');
        $args->method('getNewValue')->with('slug')->willReturn('new-slug');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $listener = new ArticleSlugChangeListener($em, $logger);
        $listener->preUpdate($args);
    }

    public function testPreUpdateIgnoresWhenSlugsIdentical(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $article = $this->createStub(Article::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('slug')->willReturn(true);
        $args->method('getOldValue')->with('slug')->willReturn('same-slug');
        $args->method('getNewValue')->with('slug')->willReturn('same-slug');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $listener = new ArticleSlugChangeListener($em, $logger);
        $listener->preUpdate($args);
    }

    public function testPreUpdateSchedulesRedirectWhenSlugChanges(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(42);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('slug')->willReturn(true);
        $args->method('getOldValue')->with('slug')->willReturn('old-slug');
        $args->method('getNewValue')->with('slug')->willReturn('new-slug');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with($this->stringContains('slug change detected'));

        $listener = new ArticleSlugChangeListener($em, $logger);
        $listener->preUpdate($args);
    }

    public function testPostFlushDoesNothingWhenNoPendingRedirects(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $listener = new ArticleSlugChangeListener($em, $logger);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->expects($this->never())->method('persist');

        $args = $this->createMock(PostFlushEventArgs::class);
        $args->method('getObjectManager')->willReturn($innerEm);

        $listener->postFlush($args);
    }

    public function testPostFlushCreatesRedirectsForAllLocales(): void
    {
        // Build category stub with slug
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(3);
        $category->method('getSlug')->willReturn('politica');

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(42);
        $article->method('getCategory')->willReturn($category);
        $article->method('getSlug')->willReturn('old-slug');

        // Set up query chain
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getOneOrNullResult')->willReturn(null); // no translated entity found → use fallback slug

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $urlRedirectRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $urlRedirectRepo->method('findOneBy')->willReturn(null);

        $categoryRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $categoryRepo->method('createQueryBuilder')->willReturn($qb);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturnCallback(function (string $class) use ($urlRedirectRepo, $categoryRepo) {
            if (str_contains($class, 'UrlRedirect')) {
                return $urlRedirectRepo;
            }

            return $categoryRepo;
        });

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(true);
        $innerEm->method('getUnitOfWork')->willReturn($uow);
        // 3 locales × 1 redirect each = 3 persist calls
        $innerEm->expects($this->exactly(3))->method('persist');
        $innerEm->expects($this->once())->method('flush');

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createStub(LoggerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new ArticleSlugChangeListener($em, $logger);

        // Schedule a redirect via preUpdate
        $preUpdateArgs = $this->createMock(PreUpdateEventArgs::class);
        $preUpdateArgs->method('getObject')->willReturn($article);
        $preUpdateArgs->method('hasChangedField')->with('slug')->willReturn(true);
        $preUpdateArgs->method('getOldValue')->with('slug')->willReturn('old-slug');
        $preUpdateArgs->method('getNewValue')->with('slug')->willReturn('new-slug');
        $listener->preUpdate($preUpdateArgs);

        $listener->postFlush($postFlushArgs);
    }

    public function testPostFlushSkipsArticleWithNoCategory(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(99);
        $article->method('getCategory')->willReturn(null); // no category

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->expects($this->never())->method('persist');

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(false);
        $innerEm->method('getUnitOfWork')->willReturn($uow);

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createStub(LoggerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new ArticleSlugChangeListener($em, $logger);

        // Schedule a redirect
        $preUpdateArgs = $this->createMock(PreUpdateEventArgs::class);
        $preUpdateArgs->method('getObject')->willReturn($article);
        $preUpdateArgs->method('hasChangedField')->with('slug')->willReturn(true);
        $preUpdateArgs->method('getOldValue')->with('slug')->willReturn('old-slug');
        $preUpdateArgs->method('getNewValue')->with('slug')->willReturn('new-slug');
        $listener->preUpdate($preUpdateArgs);

        $listener->postFlush($postFlushArgs);
    }

    public function testPostFlushUpdatesExistingRedirectInsteadOfCreatingNew(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(3);
        $category->method('getSlug')->willReturn('politica');

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(42);
        $article->method('getCategory')->willReturn($category);
        $article->method('getSlug')->willReturn('old-slug');

        // Query chain for getTranslatedSlug
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

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturnCallback(function (string $class) use ($urlRedirectRepo, $categoryRepo) {
            if (str_contains($class, 'UrlRedirect')) {
                return $urlRedirectRepo;
            }
            return $categoryRepo;
        });

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(false);
        $innerEm->method('getUnitOfWork')->willReturn($uow);

        // Should NOT persist new redirects since existing are being updated
        $innerEm->expects($this->never())->method('persist');

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createStub(LoggerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new ArticleSlugChangeListener($em, $logger);

        $preUpdateArgs = $this->createMock(PreUpdateEventArgs::class);
        $preUpdateArgs->method('getObject')->willReturn($article);
        $preUpdateArgs->method('hasChangedField')->with('slug')->willReturn(true);
        $preUpdateArgs->method('getOldValue')->with('slug')->willReturn('old-slug');
        $preUpdateArgs->method('getNewValue')->with('slug')->willReturn('new-slug');
        $listener->preUpdate($preUpdateArgs);

        $listener->postFlush($postFlushArgs);
    }

    public function testPostFlushHandlesExceptionInCreateRedirect(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(3);

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(42);
        $article->method('getCategory')->willReturn($category);

        // getTranslatedSlug throws exception
        $categoryRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $categoryRepo->method('createQueryBuilder')
            ->willThrowException(new \Exception('DB error'));

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturn($categoryRepo);

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(false);
        $innerEm->method('getUnitOfWork')->willReturn($uow);

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('error');

        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new ArticleSlugChangeListener($em, $logger);

        $preUpdateArgs = $this->createMock(PreUpdateEventArgs::class);
        $preUpdateArgs->method('getObject')->willReturn($article);
        $preUpdateArgs->method('hasChangedField')->with('slug')->willReturn(true);
        $preUpdateArgs->method('getOldValue')->with('slug')->willReturn('old-slug');
        $preUpdateArgs->method('getNewValue')->with('slug')->willReturn('new-slug');
        $listener->preUpdate($preUpdateArgs);

        // Should not throw, exception is caught
        $listener->postFlush($postFlushArgs);
    }

    public function testPostFlushHandlesTranslatedEntityWithSlug(): void
    {
        // Test getTranslatedSlug when translated entity is found with getSlug
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(3);
        $category->method('getSlug')->willReturn('politica');

        $translatedCategory = $this->createMock(Category::class);
        $translatedCategory->method('getSlug')->willReturn('politics');

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(42);
        $article->method('getCategory')->willReturn($category);

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('setHint')->willReturnSelf();
        // Return a translated entity with getSlug
        $query->method('getOneOrNullResult')->willReturn($translatedCategory);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $urlRedirectRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $urlRedirectRepo->method('findOneBy')->willReturn(null);

        $categoryRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $categoryRepo->method('createQueryBuilder')->willReturn($qb);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturnCallback(function (string $class) use ($urlRedirectRepo, $categoryRepo) {
            if (str_contains($class, 'UrlRedirect')) {
                return $urlRedirectRepo;
            }
            return $categoryRepo;
        });

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(true);
        $innerEm->method('getUnitOfWork')->willReturn($uow);
        $innerEm->expects($this->exactly(3))->method('persist');

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createStub(LoggerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new ArticleSlugChangeListener($em, $logger);

        $preUpdateArgs = $this->createMock(PreUpdateEventArgs::class);
        $preUpdateArgs->method('getObject')->willReturn($article);
        $preUpdateArgs->method('hasChangedField')->with('slug')->willReturn(true);
        $preUpdateArgs->method('getOldValue')->with('slug')->willReturn('old-slug');
        $preUpdateArgs->method('getNewValue')->with('slug')->willReturn('new-slug');
        $listener->preUpdate($preUpdateArgs);

        $listener->postFlush($postFlushArgs);
    }

    public function testPreUpdateIgnoresWhenNewSlugEmpty(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $article = $this->createStub(Article::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('slug')->willReturn(true);
        $args->method('getOldValue')->with('slug')->willReturn('old-slug');
        $args->method('getNewValue')->with('slug')->willReturn('');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $listener = new ArticleSlugChangeListener($em, $logger);
        $listener->preUpdate($args);
    }

    public function testPostFlushClearsPendingRedirectsAfterProcessing(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(1);
        $category->method('getSlug')->willReturn('sport');

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(7);
        $article->method('getCategory')->willReturn($category);
        $article->method('getSlug')->willReturn('article-slug');

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getOneOrNullResult')->willReturn(null);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $urlRedirectRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $urlRedirectRepo->method('findOneBy')->willReturn(null);

        $categoryRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $categoryRepo->method('createQueryBuilder')->willReturn($qb);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturnCallback(function (string $class) use ($urlRedirectRepo, $categoryRepo) {
            return str_contains($class, 'UrlRedirect') ? $urlRedirectRepo : $categoryRepo;
        });
        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(false);
        $innerEm->method('getUnitOfWork')->willReturn($uow);
        // persist() returns void — no willReturn needed

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createStub(LoggerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new ArticleSlugChangeListener($em, $logger);

        $preUpdateArgs = $this->createMock(PreUpdateEventArgs::class);
        $preUpdateArgs->method('getObject')->willReturn($article);
        $preUpdateArgs->method('hasChangedField')->with('slug')->willReturn(true);
        $preUpdateArgs->method('getOldValue')->with('slug')->willReturn('old-slug');
        $preUpdateArgs->method('getNewValue')->with('slug')->willReturn('new-slug');
        $listener->preUpdate($preUpdateArgs);

        $listener->postFlush($postFlushArgs);

        // Second postFlush should do nothing
        $innerEm2 = $this->createMock(EntityManagerInterface::class);
        $innerEm2->expects($this->never())->method('persist');
        $postFlushArgs2 = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs2->method('getObjectManager')->willReturn($innerEm2);

        $listener->postFlush($postFlushArgs2);
    }
}
