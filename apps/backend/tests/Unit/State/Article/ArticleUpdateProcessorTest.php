<?php

declare(strict_types=1);

namespace App\Tests\Unit\State\Article;

use ApiPlatform\Metadata\Put;
use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Tag;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use App\Event\ArticlePublishedEvent;
use App\Event\ArticleUpdatedEvent;
use App\Service\Article\ArticleCacheInvalidator;
use App\Service\Article\CollectionSyncService;
use App\State\Article\ArticleUpdateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ArticleUpdateProcessorTest extends TestCase
{
    private ArticleUpdateProcessor $processor;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private CollectionSyncService $collectionSync;
    private ArticleCacheInvalidator $cacheInvalidator;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->collectionSync = new CollectionSyncService($this->entityManager);
        $this->cacheInvalidator = $this->createMock(ArticleCacheInvalidator::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->processor = new ArticleUpdateProcessor(
            $this->entityManager,
            $this->requestStack,
            $this->collectionSync,
            $this->cacheInvalidator,
            $this->eventDispatcher,
            $this->createStub(HttpClientInterface::class),
            $this->createStub(LoggerInterface::class),
            '',
            '',
        );
    }

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
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());

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
        $data->method('getTopics')->willReturn(new ArrayCollection());

        $this->cacheInvalidator->expects($this->once())->method('invalidate')->with(10);

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
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());

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
        $data->method('getTopics')->willReturn(new ArrayCollection());

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
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());

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
        $data->method('getTopics')->willReturn(new ArrayCollection());

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ArticleUpdatedEvent::class));

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

    #[Test]
    public function itSyncsTagsOnUpdateAddingNewTags(): void
    {
        $this->setupRequest('ro');

        $existingTag = $this->createMock(Tag::class);
        $existingTag->method('getId')->willReturn(1);

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
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());

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
        $data->method('getTopics')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itSyncsTagsOnUpdateRemovingOldTags(): void
    {
        $this->setupRequest('ro');

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
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());
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
        $data->method('getTopics')->willReturn(new ArrayCollection());

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

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
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());
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
        $data->method('getTopics')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Article::class, $result);
    }

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
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());

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
        $data->method('getTopics')->willReturn(new ArrayCollection());

        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

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
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());
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
        $data->method('getTopics')->willReturn(new ArrayCollection());

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
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());
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
        $data->method('getTopics')->willReturn(new ArrayCollection());

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

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
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());
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
        $data->method('getTopics')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itUpdatesArticleWithNonDefaultLocaleUsingTranslationRepo(): void
    {
        $this->setupRequest('en');

        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(20);
        $existingArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $existingArticle->method('getAuthors')->willReturn(new ArrayCollection());
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection());
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());
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
        $data->method('getTopics')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 20]);

        $this->assertInstanceOf(Article::class, $result);
    }

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
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());

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
        $data->method('getTopics')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 15]);

        $this->assertInstanceOf(Article::class, $result);
    }

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
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());
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
        $data->method('getTopics')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 11]);

        $this->assertInstanceOf(Article::class, $result);
    }

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
        $existingArticle->method('getRelatedArticles')->willReturn(new ArrayCollection([$relatedArticle1, $relatedArticle2]));
        $existingArticle->method('getTags')->willReturn(new ArrayCollection());
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());
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
        $data->method('getTopics')->willReturn(new ArrayCollection());

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Article::class, $result);
    }

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
        $existingArticle->method('getTopics')->willReturn(new ArrayCollection());

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
        $data->method('getTopics')->willReturn(new ArrayCollection());

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

    #[Test]
    public function itReturnsNullForNonArticleData(): void
    {
        $this->setupRequest('ro');

        $operation = new Put();
        $result = $this->processor->process('not-an-article', $operation, ['id' => 10]);

        $this->assertNull($result);
    }

    private function setupRequest(string $locale): void
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => $locale]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
    }
}
