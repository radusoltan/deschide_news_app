<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Article;
use App\Entity\Category;
use App\EventListener\CategorySlugChangeListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CategorySlugChangeListenerTest extends TestCase
{
    public function testPreUpdateIgnoresNonCategoryEntities(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $listener = new CategorySlugChangeListener($em, $logger);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn(new \stdClass());
        $args->expects($this->never())->method('hasChangedField');

        $listener->preUpdate($args);
    }

    public function testPreUpdateIgnoresWhenSlugNotChanged(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $listener = new CategorySlugChangeListener($em, $logger);

        $category = $this->createStub(Category::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($category);
        $args->method('hasChangedField')->with('slug')->willReturn(false);
        $args->expects($this->never())->method('getOldValue');

        $listener->preUpdate($args);
    }

    public function testPreUpdateIgnoresWhenSlugsEmpty(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $category = $this->createStub(Category::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($category);
        $args->method('hasChangedField')->with('slug')->willReturn(true);
        $args->method('getOldValue')->with('slug')->willReturn('');
        $args->method('getNewValue')->with('slug')->willReturn('new-slug');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $listener = new CategorySlugChangeListener($em, $logger);
        $listener->preUpdate($args);
    }

    public function testPreUpdateIgnoresWhenSlugsIdentical(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $category = $this->createStub(Category::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($category);
        $args->method('hasChangedField')->with('slug')->willReturn(true);
        $args->method('getOldValue')->with('slug')->willReturn('same-slug');
        $args->method('getNewValue')->with('slug')->willReturn('same-slug');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $listener = new CategorySlugChangeListener($em, $logger);
        $listener->preUpdate($args);
    }

    public function testPreUpdateSchedulesRedirectWhenSlugChanges(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(7);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($category);
        $args->method('hasChangedField')->with('slug')->willReturn(true);
        $args->method('getOldValue')->with('slug')->willReturn('politica');
        $args->method('getNewValue')->with('slug')->willReturn('politica-interna');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with($this->stringContains('slug change detected'));

        $listener = new CategorySlugChangeListener($em, $logger);
        $listener->preUpdate($args);
    }

    public function testPostFlushDoesNothingWhenNoPendingRedirects(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $listener = new CategorySlugChangeListener($em, $logger);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->expects($this->never())->method('persist');
        $innerEm->expects($this->never())->method('getRepository');

        $args = $this->createMock(PostFlushEventArgs::class);
        $args->method('getObjectManager')->willReturn($innerEm);

        $listener->postFlush($args);
    }

    public function testPostFlushCreatesRedirectsForAllArticlesAndLocales(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(1);

        // Two articles in category
        $article1 = $this->createMock(Article::class);
        $article1->method('getId')->willReturn(10);
        $article1->method('getSlug')->willReturn('articol-unu');

        $article2 = $this->createMock(Article::class);
        $article2->method('getId')->willReturn(11);
        $article2->method('getSlug')->willReturn('articol-doi');

        // Query chain: getOneOrNullResult returns null (use fallback slug)
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getOneOrNullResult')->willReturn(null);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        // UrlRedirect repo
        $urlRedirectRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $urlRedirectRepo->method('findOneBy')->willReturn(null);

        // Article repo (for findBy category)
        $articleRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $articleRepo->method('findBy')->with(['category' => $category])->willReturn([$article1, $article2]);
        $articleRepo->method('createQueryBuilder')->willReturn($qb);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturnCallback(function (string $class) use ($urlRedirectRepo, $articleRepo) {
            if (str_contains($class, 'UrlRedirect')) {
                return $urlRedirectRepo;
            }

            return $articleRepo;
        });

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(true);
        $innerEm->method('getUnitOfWork')->willReturn($uow);
        // 2 articles × 3 locales = 6 persist calls
        $innerEm->expects($this->exactly(6))->method('persist');
        $innerEm->expects($this->once())->method('flush');

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createStub(LoggerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new CategorySlugChangeListener($em, $logger);

        // Schedule redirect via preUpdate
        $preUpdateArgs = $this->createMock(PreUpdateEventArgs::class);
        $preUpdateArgs->method('getObject')->willReturn($category);
        $preUpdateArgs->method('hasChangedField')->with('slug')->willReturn(true);
        $preUpdateArgs->method('getOldValue')->with('slug')->willReturn('politica');
        $preUpdateArgs->method('getNewValue')->with('slug')->willReturn('politica-interna');
        $listener->preUpdate($preUpdateArgs);

        $listener->postFlush($postFlushArgs);
    }

    public function testPostFlushHandlesNoArticlesInCategory(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(5);

        // Empty articles list
        $articleRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $articleRepo->method('findBy')->willReturn([]);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturnCallback(function (string $class) use ($articleRepo) {
            return $articleRepo;
        });
        $innerEm->expects($this->never())->method('persist');

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(false);
        $innerEm->method('getUnitOfWork')->willReturn($uow);

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createStub(LoggerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new CategorySlugChangeListener($em, $logger);

        $preUpdateArgs = $this->createMock(PreUpdateEventArgs::class);
        $preUpdateArgs->method('getObject')->willReturn($category);
        $preUpdateArgs->method('hasChangedField')->with('slug')->willReturn(true);
        $preUpdateArgs->method('getOldValue')->with('slug')->willReturn('sport');
        $preUpdateArgs->method('getNewValue')->with('slug')->willReturn('sport-ro');
        $listener->preUpdate($preUpdateArgs);

        $listener->postFlush($postFlushArgs);
    }

    public function testPostFlushUpdatesExistingRedirectsInsteadOfCreatingNew(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(1);

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(10);
        $article->method('getSlug')->willReturn('articol-test');

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getOneOrNullResult')->willReturn(null);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $existingRedirect = $this->createMock(\App\Entity\UrlRedirect::class);
        $existingRedirect->expects($this->atLeastOnce())->method('setNewUrl');

        $urlRedirectRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $urlRedirectRepo->method('findOneBy')->willReturn($existingRedirect);

        $articleRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $articleRepo->method('findBy')->willReturn([$article]);
        $articleRepo->method('createQueryBuilder')->willReturn($qb);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturnCallback(function (string $class) use ($urlRedirectRepo, $articleRepo) {
            if (str_contains($class, 'UrlRedirect')) {
                return $urlRedirectRepo;
            }
            return $articleRepo;
        });

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(false);
        $innerEm->method('getUnitOfWork')->willReturn($uow);

        $innerEm->expects($this->never())->method('persist');

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createStub(LoggerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new CategorySlugChangeListener($em, $logger);

        $preUpdateArgs = $this->createMock(PreUpdateEventArgs::class);
        $preUpdateArgs->method('getObject')->willReturn($category);
        $preUpdateArgs->method('hasChangedField')->with('slug')->willReturn(true);
        $preUpdateArgs->method('getOldValue')->with('slug')->willReturn('old-cat');
        $preUpdateArgs->method('getNewValue')->with('slug')->willReturn('new-cat');
        $listener->preUpdate($preUpdateArgs);

        $listener->postFlush($postFlushArgs);
    }

    public function testPostFlushHandlesExceptionInCreateRedirect(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(1);

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(10);

        $articleRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $articleRepo->method('findBy')->willReturn([$article]);
        $articleRepo->method('createQueryBuilder')
            ->willThrowException(new \Exception('DB error'));

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturn($articleRepo);

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(false);
        $innerEm->method('getUnitOfWork')->willReturn($uow);

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('error');

        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new CategorySlugChangeListener($em, $logger);

        $preUpdateArgs = $this->createMock(PreUpdateEventArgs::class);
        $preUpdateArgs->method('getObject')->willReturn($category);
        $preUpdateArgs->method('hasChangedField')->with('slug')->willReturn(true);
        $preUpdateArgs->method('getOldValue')->with('slug')->willReturn('old');
        $preUpdateArgs->method('getNewValue')->with('slug')->willReturn('new');
        $listener->preUpdate($preUpdateArgs);

        $listener->postFlush($postFlushArgs);
    }

    public function testPreUpdateIgnoresWhenNewSlugEmpty(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $category = $this->createStub(Category::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($category);
        $args->method('hasChangedField')->with('slug')->willReturn(true);
        $args->method('getOldValue')->with('slug')->willReturn('politica');
        $args->method('getNewValue')->with('slug')->willReturn('');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $listener = new CategorySlugChangeListener($em, $logger);
        $listener->preUpdate($args);
    }

    public function testPostFlushWithTranslatedEntityReturnsSlug(): void
    {
        $translatedArticle = $this->createMock(Article::class);
        $translatedArticle->method('getSlug')->willReturn('translated-slug');

        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(1);

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(10);
        $article->method('getSlug')->willReturn('articol');

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getOneOrNullResult')->willReturn($translatedArticle);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $urlRedirectRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $urlRedirectRepo->method('findOneBy')->willReturn(null);

        $articleRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $articleRepo->method('findBy')->willReturn([$article]);
        $articleRepo->method('createQueryBuilder')->willReturn($qb);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturnCallback(function (string $class) use ($urlRedirectRepo, $articleRepo) {
            if (str_contains($class, 'UrlRedirect')) {
                return $urlRedirectRepo;
            }
            return $articleRepo;
        });

        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(true);
        $innerEm->method('getUnitOfWork')->willReturn($uow);
        $innerEm->expects($this->exactly(3))->method('persist');

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createStub(LoggerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new CategorySlugChangeListener($em, $logger);

        $preUpdateArgs = $this->createMock(PreUpdateEventArgs::class);
        $preUpdateArgs->method('getObject')->willReturn($category);
        $preUpdateArgs->method('hasChangedField')->with('slug')->willReturn(true);
        $preUpdateArgs->method('getOldValue')->with('slug')->willReturn('sport');
        $preUpdateArgs->method('getNewValue')->with('slug')->willReturn('sport-fotbal');
        $listener->preUpdate($preUpdateArgs);

        $listener->postFlush($postFlushArgs);
    }

    public function testPostFlushClearsPendingRedirectsAfterProcessing(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(2);

        $articleRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $articleRepo->method('findBy')->willReturn([]);

        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getRepository')->willReturn($articleRepo);
        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $uow->method('hasPendingInsertions')->willReturn(false);
        $innerEm->method('getUnitOfWork')->willReturn($uow);

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getObjectManager')->willReturn($innerEm);

        $logger = $this->createStub(LoggerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $listener = new CategorySlugChangeListener($em, $logger);

        // Schedule a redirect
        $preUpdateArgs = $this->createMock(PreUpdateEventArgs::class);
        $preUpdateArgs->method('getObject')->willReturn($category);
        $preUpdateArgs->method('hasChangedField')->with('slug')->willReturn(true);
        $preUpdateArgs->method('getOldValue')->with('slug')->willReturn('cultura');
        $preUpdateArgs->method('getNewValue')->with('slug')->willReturn('cultura-arta');
        $listener->preUpdate($preUpdateArgs);

        $listener->postFlush($postFlushArgs);

        // Second call should not touch any repo since pending was cleared
        $innerEm2 = $this->createMock(EntityManagerInterface::class);
        $innerEm2->expects($this->never())->method('getRepository');
        $postFlushArgs2 = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs2->method('getObjectManager')->willReturn($innerEm2);

        $listener->postFlush($postFlushArgs2);
    }
}
