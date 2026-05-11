<?php

declare(strict_types=1);

namespace App\Tests\Unit\State\Article;

use ApiPlatform\Metadata\Post;
use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Tag;
use App\Enum\ArticleStatus;
use App\Event\ArticlePublishedEvent;
use App\Service\Article\CollectionSyncService;
use App\State\Article\ArticleCreateProcessor;
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
class ArticleCreateProcessorTest extends TestCase
{
    private ArticleCreateProcessor $processor;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private CollectionSyncService $collectionSync;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->collectionSync = new CollectionSyncService($this->entityManager);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->processor = new ArticleCreateProcessor(
            $this->entityManager,
            $this->requestStack,
            $this->collectionSync,
            $this->eventDispatcher,
            $this->createStub(HttpClientInterface::class),
            $this->createStub(LoggerInterface::class),
            '',
            '',
        );
    }

    #[Test]
    public function itCreatesNewArticleInDefaultLocale(): void
    {
        $this->setupRequest('ro');

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setStatus(ArticleStatus::NEW);

        $this->entityManager->expects($this->once())->method('persist')->with($article);
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

    #[Test]
    public function itExtractsLocaleFromAcceptLanguageHeader(): void
    {
        $this->setupRequest('en');

        $article = new Article();
        $article->setTitle('English Article');
        $article->setStatus(ArticleStatus::NEW);

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

        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $result = $this->processor->process($article, $operation);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itReturnsNullForNonArticleDataOnPost(): void
    {
        $this->setupRequest('ro');

        $operation = new Post();
        $result = $this->processor->process('not-an-article', $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itHandlesLocaleWithComma(): void
    {
        $this->setupRequest('en,ro;q=0.9');

        $article = new Article();
        $article->setTitle('English Article');
        $article->setStatus(ArticleStatus::NEW);

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
    public function itParsesLocaleWithComma(): void
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => 'en,ro;q=0.9']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('Comma Locale');
        $article->setStatus(ArticleStatus::NEW);

        $translationRepo = $this->createMock(TranslationRepository::class);
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

    #[Test]
    public function itParsesLocaleWithDash(): void
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => 'en-US']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('Dash Locale');
        $article->setStatus(ArticleStatus::NEW);

        $translationRepo = $this->createMock(TranslationRepository::class);
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

    #[Test]
    public function itReturnsNullForNonArticleDataOnPostOperation(): void
    {
        $this->setupRequest('ro');

        $operation = new Post();
        $result = $this->processor->process('not-an-article', $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itHandlesManagedTagsOnCreate(): void
    {
        $this->setupRequest('ro');

        $tag = $this->createStub(Tag::class);
        $tag->method('getId')->willReturn(1);

        $managedTag = $this->createMock(Tag::class);
        $managedTag->method('getId')->willReturn(1);
        $managedTag->method('getUsageCount')->willReturn(0);
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

    private function setupRequest(string $locale): void
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => $locale]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
    }
}
