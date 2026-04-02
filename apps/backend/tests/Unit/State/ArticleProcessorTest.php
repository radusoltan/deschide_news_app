<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Tag;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use App\Event\ArticlePublishedEvent;
use App\Event\ArticleUpdatedEvent;
use App\Message\CheckOrphanedTagsMessage;
use App\Service\PerformanceService;
use App\State\ArticleProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ArticleProcessorTest extends TestCase
{
    private ArticleProcessor $processor;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private MessageBusInterface $messageBus;
    private PerformanceService $performanceService;
    private CacheItemPoolInterface $cachePool;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->performanceService = $this->createMock(PerformanceService::class);
        $this->cachePool = $this->createStub(CacheItemPoolInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->processor = new ArticleProcessor(
            $this->entityManager,
            $this->requestStack,
            $this->messageBus,
            $this->performanceService,
            $this->cachePool,
            $this->eventDispatcher,
        );
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesArticleAndFlushes(): void
    {
        $this->setupRequest('ro');

        $article = $this->createArticleMock(10);
        $article->method('getTags')->willReturn(new ArrayCollection());

        $this->entityManager->expects($this->once())->method('remove')->with($article);
        $this->entityManager->expects($this->once())->method('flush');

        $this->performanceService->expects($this->once())->method('invalidateArticle')->with(10);

        $operation = new Delete();
        $result = $this->processor->process($article, $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itDecrementsTagUsageCountOnDelete(): void
    {
        $this->setupRequest('ro');

        $tag1 = $this->createMock(Tag::class);
        $tag1->method('getId')->willReturn(1);
        $tag1->method('getUsageCount')->willReturn(5);
        $tag1->expects($this->once())->method('setUsageCount')->with(4);

        $tag2 = $this->createMock(Tag::class);
        $tag2->method('getId')->willReturn(2);
        $tag2->method('getUsageCount')->willReturn(1);
        $tag2->expects($this->once())->method('setUsageCount')->with(0);

        $article = $this->createArticleMock(10);
        $article->method('getTags')->willReturn(new ArrayCollection([$tag1, $tag2]));

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CheckOrphanedTagsMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $operation = new Delete();
        $this->processor->process($article, $operation);
    }

    #[Test]
    public function itReturnsNullForDeleteOfNonArticleData(): void
    {
        $this->setupRequest('ro');

        $operation = new Delete();
        $result = $this->processor->process('not-an-article', $operation);

        $this->assertNull($result);
    }

    // ========================
    // CREATE Operation Tests
    // ========================

    #[Test]
    public function itCreatesNewArticleInDefaultLocale(): void
    {
        $this->setupRequest('ro');

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setStatus(ArticleStatus::NEW);

        $this->entityManager->expects($this->once())->method('persist')->with($article);
        // flush is called twice: once for persist, once for tag usage count
        $this->entityManager->expects($this->exactly(2))->method('flush');

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
        $this->assertEquals('Test Article', $result->getTitle());
    }

    #[Test]
    public function itSetsDefaultLocaleToRoForNewArticles(): void
    {
        $this->setupRequest('en');

        $article = new Article();
        $article->setTitle('English Title');
        $article->setStatus(ArticleStatus::NEW);

        // For non-default locale, addTranslation is called which needs translation repo
        $translationRepo = $this->createMock(TranslationRepository::class);
        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($translationRepo) {
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itDispatchesArticlePublishedEventForNewPublishedArticle(): void
    {
        $this->setupRequest('ro');

        $article = new Article();
        $article->setTitle('Published Article');
        $article->setStatus(ArticleStatus::PUBLISHED);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ArticlePublishedEvent::class));

        $operation = new Post();
        $this->processor->process($article, $operation);
    }

    #[Test]
    public function itDoesNotDispatchEventForDraftArticle(): void
    {
        $this->setupRequest('ro');

        $article = new Article();
        $article->setTitle('Draft Article');
        $article->setStatus(ArticleStatus::NEW);

        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $operation = new Post();
        $this->processor->process($article, $operation);
    }

    #[Test]
    public function itHandlesManagedCategoryOnCreate(): void
    {
        $this->setupRequest('ro');

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(5);

        $managedCategory = $this->createStub(Category::class);
        $managedCategory->method('getId')->willReturn(5);

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->with(5)->willReturn($managedCategory);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($categoryRepo) {
                if ($class === Category::class) {
                    return $categoryRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $article = new Article();
        $article->setTitle('Article with Category');
        $article->setStatus(ArticleStatus::NEW);
        $article->setCategory($category);

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
    }

    // ========================
    // UPDATE Operation Tests
    // ========================

    #[Test]
    public function itUpdatesExistingArticle(): void
    {
        $this->setupRequest('ro');

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(10);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());

        $existingArticle->expects($this->once())->method('setTitle')->with('Updated Title');
        $existingArticle->expects($this->once())->method('setStatus')->with(ArticleStatus::PUBLISHED);

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(10)->willReturn($existingArticle);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('Updated Title');
        $data->method('getLead')->willReturn(null);
        $data->method('getContent')->willReturn(null);
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection());
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection());

        $this->performanceService->expects($this->once())->method('invalidateArticle')->with(10);

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenUpdatingNonExistentArticle(): void
    {
        $this->setupRequest('ro');

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')->willReturn($articleRepo);

        $data = $this->createStub(Article::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Article not found');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 999]);
    }

    #[Test]
    public function itDispatchesArticlePublishedEventOnStatusChange(): void
    {
        $this->setupRequest('ro');

        $currentStatus = ArticleStatus::NEW;

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(10);
        $existingArticle->method('getStatus')->willReturnCallback(function () use (&$currentStatus) {
            return $currentStatus;
        });
        $existingArticle->method('setStatus')->willReturnCallback(function (ArticleStatus $status) use (&$currentStatus, &$existingArticle) {
            $currentStatus = $status;
            return $existingArticle;
        });
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(10)->willReturn($existingArticle);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getLead')->willReturn(null);
        $data->method('getContent')->willReturn(null);
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection());
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection());

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ArticlePublishedEvent::class));

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

    #[Test]
    public function itDispatchesArticleUpdatedEventWhenAlreadyPublished(): void
    {
        $this->setupRequest('ro');

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(10);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(10)->willReturn($existingArticle);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getLead')->willReturn(null);
        $data->method('getContent')->willReturn(null);
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection());
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection());

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ArticleUpdatedEvent::class));

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

    // ========================
    // Locale Handling Tests
    // ========================

    #[Test]
    public function itExtractsLocaleFromAcceptLanguageHeader(): void
    {
        $this->setupRequest('en');

        $article = new Article();
        $article->setTitle('English Article');
        $article->setStatus(ArticleStatus::NEW);

        // For non-default locale, addTranslation is called
        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->expects($this->once())
            ->method('translate')
            ->with($article, 'title', 'en', 'English Article');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($translationRepo) {
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post();
        $this->processor->process($article, $operation);
    }

    #[Test]
    public function itHandlesLocaleWithRegion(): void
    {
        $this->setupRequest('en-US');

        $article = new Article();
        $article->setTitle('English Article');
        $article->setStatus(ArticleStatus::NEW);

        $translationRepo = $this->createMock(TranslationRepository::class);
        // Should extract 'en' from 'en-US'
        $translationRepo->expects($this->once())
            ->method('translate')
            ->with($article, 'title', 'en', 'English Article');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($translationRepo) {
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post();
        $this->processor->process($article, $operation);
    }

    #[Test]
    public function itUsesDefaultLocaleWhenNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $article = new Article();
        $article->setTitle('Default Locale Article');
        $article->setStatus(ArticleStatus::NEW);

        // Default locale is 'ro', so no translation call should happen
        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
    }

    // ========================
    // Non-Article Data Tests
    // ========================

    #[Test]
    public function itReturnsNullForNonArticleDataOnPost(): void
    {
        $this->setupRequest('ro');

        $operation = new Post();
        $result = $this->processor->process('not-an-article', $operation);

        $this->assertNull($result);
    }

    // ========================
    // UPDATE with Tag Sync Tests
    // ========================

    #[Test]
    public function itSyncsTagsOnUpdateAddingNewTags(): void
    {
        $this->setupRequest('ro');

        // Existing article has tag1 (id=1)
        $existingTag = $this->createMock(Tag::class);
        $existingTag->method('getId')->willReturn(1);

        // Incoming data has tag1 (id=1) + tag2 (id=2)
        $newTag = $this->createMock(Tag::class);
        $newTag->method('getId')->willReturn(2);
        $newTag->method('getUsageCount')->willReturn(0);
        $newTag->expects($this->once())->method('setUsageCount')->with(1);

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(10);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection([$existingTag]));

        $managedNewTag = $newTag;

        $incomingTag1 = $this->createStub(Tag::class);
        $incomingTag1->method('getId')->willReturn(1);
        $incomingTag2 = $this->createStub(Tag::class);
        $incomingTag2->method('getId')->willReturn(2);

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(10)->willReturn($existingArticle);

        $tagRepo = $this->createStub(EntityRepository::class);
        $tagRepo->method('find')->willReturnMap([
            [2, $managedNewTag],
        ]);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo, $tagRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                if ($class === Tag::class) {
                    return $tagRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getLead')->willReturn(null);
        $data->method('getContent')->willReturn(null);
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection());
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection([$incomingTag1, $incomingTag2]));

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itSyncsTagsOnUpdateRemovingOldTags(): void
    {
        $this->setupRequest('ro');

        // Existing article has tag1 (id=1) and tag2 (id=2)
        $tag1 = $this->createMock(Tag::class);
        $tag1->method('getId')->willReturn(1);

        $tag2 = $this->createMock(Tag::class);
        $tag2->method('getId')->willReturn(2);
        $tag2->method('getUsageCount')->willReturn(5);
        $tag2->expects($this->once())->method('setUsageCount')->with(4);

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(10);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection([$tag1, $tag2]));
        // removeTag should be called for tag2 (which is not in incoming)
        $existingArticle->expects($this->once())->method('removeTag')->with($tag2);

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(10)->willReturn($existingArticle);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        // Incoming data has only tag1 (id=1), tag2 removed
        $incomingTag1 = $this->createStub(Tag::class);
        $incomingTag1->method('getId')->willReturn(1);

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getLead')->willReturn(null);
        $data->method('getContent')->willReturn(null);
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection());
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection([$incomingTag1]));

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

    // ========================
    // UPDATE with Non-Default Locale (Translation Path)
    // ========================

    #[Test]
    public function itSavesTranslationsWhenUpdatingWithNonDefaultLocale(): void
    {
        $this->setupRequest('en');

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(10);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());
        $existingArticle->method('getTitle')->willReturn('English Title');
        $existingArticle->method('getLead')->willReturn('English Lead');
        $existingArticle->method('getContent')->willReturn('English Content');

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(10)->willReturn($existingArticle);

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->expects($this->exactly(3))
            ->method('translate');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo, $translationRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('English Title');
        $data->method('getLead')->willReturn('English Lead');
        $data->method('getContent')->willReturn('English Content');
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection());
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Article::class, $result);
    }

    // ========================
    // Locale with Comma Format
    // ========================

    #[Test]
    public function itHandlesLocaleWithComma(): void
    {
        $this->setupRequest('en,ro;q=0.9');

        $article = new Article();
        $article->setTitle('English Article');
        $article->setStatus(ArticleStatus::NEW);

        $translationRepo = $this->createMock(TranslationRepository::class);
        // Should extract 'en' from 'en,ro;q=0.9'
        $translationRepo->expects($this->once())
            ->method('translate')
            ->with($article, 'title', 'en', 'English Article');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($translationRepo) {
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post();
        $this->processor->process($article, $operation);
    }

    // ========================
    // DELETE Does Not Dispatch When No Tags
    // ========================

    #[Test]
    public function itDoesNotDispatchOrphanedTagsMessageWhenNoTags(): void
    {
        $this->setupRequest('ro');

        $article = $this->createArticleMock(10);
        $article->method('getTags')->willReturn(new ArrayCollection());

        $this->messageBus->expects($this->never())->method('dispatch');

        $operation = new Delete();
        $this->processor->process($article, $operation);
    }

    // ========================
    // UPDATE Does Not Dispatch Event For Non-Published Status
    // ========================

    #[Test]
    public function itDoesNotDispatchEventWhenUpdatingDraftToSubmitted(): void
    {
        $this->setupRequest('ro');

        $currentStatus = ArticleStatus::NEW;

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(10);
        $existingArticle->method('getStatus')->willReturnCallback(function () use (&$currentStatus) {
            return $currentStatus;
        });
        $existingArticle->method('setStatus')->willReturnCallback(function (ArticleStatus $status) use (&$currentStatus, &$existingArticle) {
            $currentStatus = $status;
            return $existingArticle;
        });
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(10)->willReturn($existingArticle);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getLead')->willReturn(null);
        $data->method('getContent')->willReturn(null);
        $data->method('getStatus')->willReturn(ArticleStatus::SUBMITTED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection());
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection());

        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

    // ========================
    // CREATE with Tags and Authors
    // ========================

    #[Test]
    public function itHandlesManagedTagsOnCreate(): void
    {
        $this->setupRequest('ro');

        $tag = $this->createStub(Tag::class);
        $tag->method('getId')->willReturn(1);

        $managedTag = $this->createMock(Tag::class);
        $managedTag->method('getId')->willReturn(1);
        $managedTag->method('getUsageCount')->willReturn(0);
        // usage count increment happens after persist+flush
        $managedTag->expects($this->once())->method('setUsageCount')->with(1);

        $tagRepo = $this->createStub(EntityRepository::class);
        $tagRepo->method('find')->with(1)->willReturn($managedTag);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($tagRepo) {
                if ($class === Tag::class) {
                    return $tagRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $article = new Article();
        $article->setTitle('Article with Tag');
        $article->setStatus(ArticleStatus::NEW);
        $article->addTag($tag);

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itHandlesManagedAuthorsOnCreate(): void
    {
        $this->setupRequest('ro');

        $author = $this->createStub(Author::class);
        $author->method('getId')->willReturn(1);

        $managedAuthor = $this->createStub(Author::class);
        $managedAuthor->method('getId')->willReturn(1);

        $authorRepo = $this->createStub(EntityRepository::class);
        $authorRepo->method('find')->with(1)->willReturn($managedAuthor);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($authorRepo) {
                if ($class === Author::class) {
                    return $authorRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $article = new Article();
        $article->setTitle('Article with Author');
        $article->setStatus(ArticleStatus::NEW);
        $article->addAuthor($author);

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
    }

    // ========================
    // UPDATE with Category
    // ========================

    #[Test]
    public function itUpdatesCategoryOnExistingArticle(): void
    {
        $this->setupRequest('ro');

        $newCategory = $this->createStub(Category::class);
        $newCategory->method('getId')->willReturn(5);

        $managedCategory = $this->createStub(Category::class);
        $managedCategory->method('getId')->willReturn(5);

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(10);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());
        $existingArticle->expects($this->once())->method('setCategory')->with($managedCategory);

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(10)->willReturn($existingArticle);

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->with(5)->willReturn($managedCategory);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo, $categoryRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                if ($class === Category::class) {
                    return $categoryRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getLead')->willReturn(null);
        $data->method('getContent')->willReturn(null);
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn($newCategory);
        $data->method('getAuthors')->willReturn(new ArrayCollection());
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Article::class, $result);
    }

    // ========================
    // Locale Parsing Tests
    // ========================

    #[Test]
    public function itParsesLocaleWithComma(): void
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => 'en,ro;q=0.9']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('Comma Locale');
        $article->setStatus(ArticleStatus::NEW);

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itParsesLocaleWithDash(): void
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => 'en-US']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('Dash Locale');
        $article->setStatus(ArticleStatus::NEW);

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itReturnsNullForNonArticleDataOnPostOperation(): void
    {
        $this->setupRequest('ro');

        $operation = new Post();
        $result = $this->processor->process('not-an-article', $operation);

        $this->assertNull($result);
    }

    // ========================
    // UPDATE with Non-Default Locale
    // ========================

    #[Test]
    public function itUpdatesArticleWithNonDefaultLocaleUsingTranslationRepo(): void
    {
        $this->setupRequest('en');

        // existingArticle must return title/lead/content so translate() gets called
        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(20);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());
        $existingArticle->method('getTitle')->willReturn('English Title');
        $existingArticle->method('getLead')->willReturn('English Lead');
        $existingArticle->method('getContent')->willReturn('English Content');

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(20)->willReturn($existingArticle);

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->expects($this->exactly(3))->method('translate');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo, $translationRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('English Title');
        $data->method('getLead')->willReturn('English Lead');
        $data->method('getContent')->willReturn('English Content');
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection());
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 20]);

        $this->assertInstanceOf(Article::class, $result);
    }

    // ========================
    // UPDATE with Lead and Content
    // ========================

    #[Test]
    public function itUpdatesLeadAndContentOnArticle(): void
    {
        $this->setupRequest('ro');

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(15);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());

        $existingArticle->expects($this->once())->method('setLead')->with('New lead');
        $existingArticle->expects($this->once())->method('setContent')->with('New content');

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(15)->willReturn($existingArticle);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getLead')->willReturn('New lead');
        $data->method('getContent')->willReturn('New content');
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection());
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 15]);

        $this->assertInstanceOf(Article::class, $result);
    }

    // ========================
    // UPDATE with publishAt
    // ========================

    #[Test]
    public function itUpdatesPublishAtOnArticle(): void
    {
        $this->setupRequest('ro');

        $publishAt = new \DateTimeImmutable('+1 day');

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(11);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());
        $existingArticle->expects($this->once())->method('setPublishAt')->with($publishAt);

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(11)->willReturn($existingArticle);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getLead')->willReturn(null);
        $data->method('getContent')->willReturn(null);
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn($publishAt);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection());
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 11]);

        $this->assertInstanceOf(Article::class, $result);
    }

    // ========================
    // DELETE without tags
    // ========================

    #[Test]
    public function itDeletesArticleWithoutTagsDoesNotDispatchOrphanMessage(): void
    {
        $this->setupRequest('ro');

        $article = $this->createArticleMock(10);
        $article->method('getTags')->willReturn(new ArrayCollection());

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->entityManager->expects($this->once())->method('remove');

        $operation = new Delete();
        $result = $this->processor->process($article, $operation);

        $this->assertNull($result);
    }

    // ========================
    // Null request fallback
    // ========================

    #[Test]
    public function itHandlesNullRequestGracefully(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $article = new Article();
        $article->setTitle('No Request');
        $article->setStatus(ArticleStatus::NEW);

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
    }

    // ========================
    // UPDATE with Author Sync
    // ========================

    #[Test]
    public function itSyncsAuthorsOnUpdateAddingNewAuthors(): void
    {
        $this->setupRequest('ro');

        $existingAuthor = $this->createStub(Author::class);
        $existingAuthor->method('getId')->willReturn(1);

        $newAuthor = $this->createStub(Author::class);
        $newAuthor->method('getId')->willReturn(2);

        $managedNewAuthor = $this->createStub(Author::class);
        $managedNewAuthor->method('getId')->willReturn(2);

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(10);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection([$existingAuthor]));
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());
        $existingArticle->expects($this->once())->method('addAuthor')->with($managedNewAuthor);

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(10)->willReturn($existingArticle);

        $authorRepo = $this->createStub(EntityRepository::class);
        $authorRepo->method('find')->willReturnMap([
            [2, $managedNewAuthor],
        ]);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo, $authorRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                if ($class === Author::class) {
                    return $authorRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $incomingAuthor1 = $this->createStub(Author::class);
        $incomingAuthor1->method('getId')->willReturn(1);
        $incomingAuthor2 = $this->createStub(Author::class);
        $incomingAuthor2->method('getId')->willReturn(2);

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getLead')->willReturn(null);
        $data->method('getContent')->willReturn(null);
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection([$incomingAuthor1, $incomingAuthor2]));
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itSyncsAuthorsOnUpdateRemovingOldAuthors(): void
    {
        $this->setupRequest('ro');

        $author1 = $this->createStub(Author::class);
        $author1->method('getId')->willReturn(1);

        $author2 = $this->createStub(Author::class);
        $author2->method('getId')->willReturn(2);

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(10);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection([$author1, $author2]));
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());
        // Should remove author2
        $existingArticle->expects($this->once())->method('removeAuthor')->with($author2);

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(10)->willReturn($existingArticle);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $incomingAuthor1 = $this->createStub(Author::class);
        $incomingAuthor1->method('getId')->willReturn(1);

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getLead')->willReturn(null);
        $data->method('getContent')->willReturn(null);
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection([$incomingAuthor1]));
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection());

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

    // ========================
    // CREATE with null ID on Category/Author/Tag
    // ========================

    #[Test]
    public function itHandlesCategoryWithNoIdOnCreate(): void
    {
        $this->setupRequest('ro');

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(null);

        $article = new Article();
        $article->setTitle('Article with no-ID Category');
        $article->setStatus(ArticleStatus::NEW);
        $article->setCategory($category);

        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
        // Category should be null since getManagedCategory returns null for no-ID category
        $this->assertNull($result->getCategory());
    }

    #[Test]
    public function itHandlesAuthorWithNoIdOnCreate(): void
    {
        $this->setupRequest('ro');

        $author = $this->createStub(Author::class);
        $author->method('getId')->willReturn(null);

        $article = new Article();
        $article->setTitle('Article with no-ID Author');
        $article->setStatus(ArticleStatus::NEW);
        $article->addAuthor($author);

        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itHandlesTagWithNoIdOnCreate(): void
    {
        $this->setupRequest('ro');

        $tag = $this->createStub(Tag::class);
        $tag->method('getId')->willReturn(null);

        $article = new Article();
        $article->setTitle('Article with no-ID Tag');
        $article->setStatus(ArticleStatus::NEW);
        $article->addTag($tag);

        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
    }

    // ========================
    // UPDATE with Related Articles Sync
    // ========================

    #[Test]
    public function itSyncsRelatedArticlesOnUpdate(): void
    {
        $this->setupRequest('ro');

        $relatedArticle1 = $this->createStub(Article::class);
        $relatedArticle1->method('getId')->willReturn(100);

        $relatedArticle2 = $this->createStub(Article::class);
        $relatedArticle2->method('getId')->willReturn(200);

        $newRelatedArticle = $this->createStub(Article::class);
        $newRelatedArticle->method('getId')->willReturn(300);

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(10);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        // existing related: [100, 200]; incoming: [100, 300] => remove 200, add 300
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection([$relatedArticle1, $relatedArticle2]));
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());
        $existingArticle->expects($this->once())->method('removeRelatedArticle')->with($relatedArticle2);
        $existingArticle->expects($this->once())->method('addRelatedArticle')->with($newRelatedArticle);

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(10)->willReturn($existingArticle);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getLead')->willReturn(null);
        $data->method('getContent')->willReturn(null);
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(null);
        $data->method('isFeatured')->willReturn(false);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection());
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection([$relatedArticle1, $newRelatedArticle]));
        $data->method('getTags')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Article::class, $result);
    }

    // ========================
    // CREATE with non-default locale and lead/content
    // ========================

    #[Test]
    public function itSavesAllTranslatableFieldsForNonDefaultLocaleOnCreate(): void
    {
        $this->setupRequest('ru');

        $article = new Article();
        $article->setTitle('Russian Title');
        $article->setLead('Russian Lead');
        $article->setContent('Russian Content');
        $article->setStatus(ArticleStatus::NEW);

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->expects($this->exactly(3))
            ->method('translate');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($translationRepo) {
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
    }

    // ========================
    // UPDATE with badge and isFeatured
    // ========================

    #[Test]
    public function itUpdatesBadgeAndIsFeaturedOnArticle(): void
    {
        $this->setupRequest('ro');

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(10);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());

        $existingArticle->expects($this->once())->method('setBadge')->with(ArticleBadge::BREAKING);
        $existingArticle->expects($this->once())->method('setIsFeatured')->with(true);

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->with(10)->willReturn($existingArticle);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($articleRepo) {
                if ($class === Article::class) {
                    return $articleRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Article::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getLead')->willReturn(null);
        $data->method('getContent')->willReturn(null);
        $data->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $data->method('getBadge')->willReturn(ArticleBadge::BREAKING);
        $data->method('isFeatured')->willReturn(true);
        $data->method('getPublishAt')->willReturn(null);
        $data->method('getCategory')->willReturn(null);
        $data->method('getAuthors')->willReturn(new ArrayCollection());
        $data->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $data->method('getTags')->willReturn(new ArrayCollection());

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

    // ========================
    // DELETE with null article ID
    // ========================

    #[Test]
    public function itHandlesDeleteArticleWithNullId(): void
    {
        $this->setupRequest('ro');

        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(null);
        $article->method('getTags')->willReturn(new ArrayCollection());

        $this->entityManager->expects($this->once())->method('remove');

        // invalidateArticle should NOT be called when ID is null
        $this->performanceService->expects($this->never())->method('invalidateArticle');

        $operation = new Delete();
        $result = $this->processor->process($article, $operation);

        $this->assertNull($result);
    }

    // ========================
    // Helper Methods
    // ========================

    private function setupRequest(string $locale): void
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => $locale]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
    }

    private function createArticleMock(int $id): Article&\PHPUnit\Framework\MockObject\Stub
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn($id);
        return $article;
    }
}
