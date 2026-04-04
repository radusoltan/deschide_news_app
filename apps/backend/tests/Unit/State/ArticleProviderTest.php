<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\State\ArticleProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Gedmo\Translatable\TranslatableListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for ArticleProvider.
 *
 * Tests locale extraction, single/collection retrieval, filtering,
 * and Gedmo Translatable hint application.
 */
class ArticleProviderTest extends TestCase
{
    private ArticleProvider $provider;

    private EntityManagerInterface $entityManager;

    private RequestStack $requestStack;

    private EntityRepository $repository;

    private QueryBuilder $queryBuilder;

    private Query $query;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);

        $this->entityManager
            ->method('getRepository')
            ->with(Article::class)
            ->willReturn($this->repository);

        $this->provider = new ArticleProvider(
            $this->entityManager,
            $this->requestStack
        );
    }

    // ======================
    // Locale Extraction Tests
    // ======================

    #[Test]
    public function itExtractsLocaleFromAcceptLanguageHeader(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupSingleItemQuery();

        // The query should have locale hint set to 'ro'
        $this->query
            ->expects($this->exactly(2))
            ->method('setHint')
            ->willReturnCallback(function ($hint, $value) {
                if ($hint === TranslatableListener::HINT_TRANSLATABLE_LOCALE) {
                    $this->assertEquals('ro', $value);
                }
                return $this->query;
            });

        $this->query->method('getOneOrNullResult')->willReturn(new Article());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    #[DataProvider('localeHeaderProvider')]
    public function itHandlesVariousLocaleHeaderFormats(string $headerValue, string $expectedLocale): void
    {
        $request = $this->createRequestWithLocale($headerValue);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupSingleItemQuery();

        $this->query
            ->expects($this->exactly(2))
            ->method('setHint')
            ->willReturnCallback(function ($hint, $value) use ($expectedLocale) {
                if ($hint === TranslatableListener::HINT_TRANSLATABLE_LOCALE) {
                    $this->assertEquals($expectedLocale, $value);
                }
                return $this->query;
            });

        $this->query->method('getOneOrNullResult')->willReturn(new Article());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    public static function localeHeaderProvider(): array
    {
        return [
            'simple romanian' => ['ro', 'ro'],
            'simple english' => ['en', 'en'],
            'simple russian' => ['ru', 'ru'],
            'en with region' => ['en-US', 'en'],
            'en with GB region' => ['en-GB', 'en'],
            'ro with region' => ['ro-RO', 'ro'],
            'with quality values' => ['en,ro;q=0.9,ru;q=0.8', 'en'],
            'complex with region and quality' => ['en-US,en;q=0.9', 'en'],
        ];
    }

    #[Test]
    public function itUsesDefaultLocaleWhenNoRequestPresent(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->setupSingleItemQuery();

        $this->query
            ->expects($this->exactly(2))
            ->method('setHint')
            ->willReturnCallback(function ($hint, $value) {
                if ($hint === TranslatableListener::HINT_TRANSLATABLE_LOCALE) {
                    $this->assertEquals('ro', $value); // Default locale
                }
                return $this->query;
            });

        $this->query->method('getOneOrNullResult')->willReturn(new Article());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ======================
    // Single Item Retrieval Tests
    // ======================

    #[Test]
    public function itRetrievesSingleArticleById(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('Test Article');

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn($article);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(Article::class, $result);
        $this->assertEquals('Test Article', $result->getTitle());
    }

    #[Test]
    public function itReturnsNullWhenArticleNotFound(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itAppliesGedmoTranslatableHintForSingleItem(): void
    {
        $request = $this->createRequestWithLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupSingleItemQuery();

        // Verify both hints are set
        $hintsSet = [];
        $this->query
            ->expects($this->exactly(2))
            ->method('setHint')
            ->willReturnCallback(function ($hint, $value) use (&$hintsSet) {
                $hintsSet[$hint] = $value;
                return $this->query;
            });

        $this->query->method('getOneOrNullResult')->willReturn(new Article());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);

        $this->assertArrayHasKey(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $hintsSet);
        $this->assertArrayHasKey(TranslatableListener::HINT_INNER_JOIN, $hintsSet);
        $this->assertEquals('en', $hintsSet[TranslatableListener::HINT_TRANSLATABLE_LOCALE]);
        $this->assertEquals(false, $hintsSet[TranslatableListener::HINT_INNER_JOIN]);
    }

    #[Test]
    public function itEagerLoadsRelatedEntitiesForSingleItem(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Verify query builder joins related entities
        // Joins: category, authors, articleImages, image, tags = 5
        $this->queryBuilder
            ->expects($this->exactly(5))
            ->method('leftJoin')
            ->willReturnSelf();

        // addSelect: c, au, ai, img, t = 5
        $this->queryBuilder
            ->expects($this->exactly(5))
            ->method('addSelect')
            ->willReturnSelf();

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn(new Article());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itExcludesArchivedArticlesInSingleItemQuery(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('a.status != :archived_status')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn(new Article());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ======================
    // Collection Retrieval Tests
    // ======================

    #[Test]
    public function itRetrievesArticleCollection(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertInstanceOf(\Doctrine\ORM\Tools\Pagination\Paginator::class, $result);
    }

    #[Test]
    public function itAppliesPaginationParameters(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $request->query = new InputBag(['page' => '2', 'itemsPerPage' => '10']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('setFirstResult')
            ->with(10) // (page 2 - 1) * 10
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(10)
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itEnforcesPaginationLimits(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $request->query = new InputBag(['page' => '0', 'itemsPerPage' => '200']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('setFirstResult')
            ->with(0) // page 0 becomes page 1, so (1-1)*100 = 0
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(100) // Max is 100
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    // ======================
    // Filtering Tests
    // ======================

    #[Test]
    public function itFiltersByCategoryId(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $request->query = new InputBag(['categoryId' => '5']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersByStatus(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $request->query = new InputBag(['status' => 'published']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersByIsFeatured(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $request->query = new InputBag(['isFeatured' => 'true']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesCustomOrderBy(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $request->query = new InputBag(['order' => ['title' => 'ASC']]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('addOrderBy')
            ->with('a.title', 'ASC')
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itUsesDefaultOrderingWhenNoOrderSpecified(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('addOrderBy')
            ->with('a.publishedAt', 'DESC')
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itValidatesOrderDirection(): void
    {
        $request = $this->createRequestWithLocale('ro');
        // Invalid direction values (not ASC or DESC) should be ignored, so falls through to else block
        $request->query = new InputBag(['order' => ['title' => 'invalid']]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // The foreach enters but doesn't call addOrderBy due to invalid direction
        // But because the orderBy array is not empty, the else block is not executed
        // So addOrderBy is never called at all
        $this->queryBuilder
            ->expects($this->never())
            ->method('addOrderBy');

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesGedmoTranslatableHintForCollection(): void
    {
        $request = $this->createRequestWithLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupCollectionQuery();

        $hintsSet = [];
        $this->query
            ->expects($this->exactly(2))
            ->method('setHint')
            ->willReturnCallback(function ($hint, $value) use (&$hintsSet) {
                $hintsSet[$hint] = $value;
                return $this->query;
            });

        $operation = new GetCollection();
        $this->provider->provide($operation);

        $this->assertEquals('en', $hintsSet[TranslatableListener::HINT_TRANSLATABLE_LOCALE]);
        $this->assertEquals(false, $hintsSet[TranslatableListener::HINT_INNER_JOIN]);
    }

    #[Test]
    public function itEagerLoadsRelatedEntitiesForCollection(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Verify all necessary joins for collection
        $this->queryBuilder
            ->expects($this->exactly(5))
            ->method('leftJoin')
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    // ======================
    // Helper Methods
    // ======================

    private function createRequestWithLocale(string $locale): Request
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => $locale]);
        $request->query = new InputBag();

        return $request;
    }

    private function setupSingleItemQuery(): void
    {
        $this->repository
            ->method('createQueryBuilder')
            ->with('a')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('leftJoin')->willReturnSelf();
        $this->queryBuilder->method('addSelect')->willReturnSelf();
        $this->queryBuilder->method('where')->willReturnSelf();
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('orderBy')->willReturnSelf();

        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        $this->query->method('setHint')->willReturnSelf();
    }

    private function setupCollectionQuery(): void
    {
        $this->repository
            ->method('createQueryBuilder')
            ->with('a')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('leftJoin')->willReturnSelf();
        $this->queryBuilder->method('addSelect')->willReturnSelf();
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('addOrderBy')->willReturnSelf();
        $this->queryBuilder->method('setFirstResult')->willReturnSelf();
        $this->queryBuilder->method('setMaxResults')->willReturnSelf();

        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        $this->query->method('setHint')->willReturnSelf();

        // Support DoctrinePaginator iteration (cloneQuery + getIterator)
        $this->query->method('getParameters')->willReturn(new ArrayCollection());
        $this->query->method('getHints')->willReturn([]);
        $this->query->method('isCacheable')->willReturn(false);
        $this->query->method('setCacheable')->willReturnSelf();
        $this->query->method('getHydrationMode')->willReturn(Query::HYDRATE_OBJECT);
        $this->query->method('getFirstResult')->willReturn(0);
        $this->query->method('getMaxResults')->willReturn(null);
        $this->query->method('setFirstResult')->willReturnSelf();
        $this->query->method('setMaxResults')->willReturnSelf();
        $this->query->method('getResult')->willReturn([]);
    }
}
