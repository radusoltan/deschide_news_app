<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Category;
use App\State\CategoryProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Translatable\TranslatableListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for CategoryProvider.
 *
 * Tests locale handling, single/collection retrieval,
 * and filtering capabilities.
 */
class CategoryProviderTest extends TestCase
{
    private CategoryProvider $provider;

    private EntityManagerInterface $entityManager;

    private RequestStack $requestStack;

    private TranslatableListener $translatableListener;

    private EntityRepository $repository;

    private QueryBuilder $queryBuilder;

    private Query $query;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->translatableListener = $this->createMock(TranslatableListener::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);

        $this->entityManager
            ->method('getRepository')
            ->with(Category::class)
            ->willReturn($this->repository);

        $this->provider = new CategoryProvider(
            $this->entityManager,
            $this->requestStack,
            $this->translatableListener
        );
    }

    // ======================
    // Locale Handling Tests
    // ======================

    #[Test]
    public function itUsesRequestLocaleForTranslations(): void
    {
        $request = $this->createRequest('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupSingleItemQuery();

        $this->query
            ->expects($this->once())
            ->method('setHint')
            ->with(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                'en'
            )
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Category());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    #[DataProvider('localeProvider')]
    public function itHandlesVariousLocales(string $locale): void
    {
        $request = $this->createRequest($locale);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupSingleItemQuery();

        $this->query
            ->expects($this->once())
            ->method('setHint')
            ->with(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            )
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Category());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    public static function localeProvider(): array
    {
        return [
            'romanian' => ['ro'],
            'english' => ['en'],
            'russian' => ['ru'],
        ];
    }

    #[Test]
    public function itUsesDefaultLocaleWhenNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->setupSingleItemQuery();

        $this->query
            ->expects($this->once())
            ->method('setHint')
            ->with(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                'ro' // Default locale
            )
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Category());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ======================
    // Single Item Retrieval Tests
    // ======================

    #[Test]
    public function itRetrievesSingleCategoryById(): void
    {
        $request = $this->createRequest('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $category = new Category();
        $category->setTitle('Test Category');

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn($category);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(Category::class, $result);
        $this->assertEquals('Test Category', $result->getTitle());
    }

    #[Test]
    public function itReturnsNullWhenCategoryNotFound(): void
    {
        $request = $this->createRequest('ro');
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
        $request = $this->createRequest('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupSingleItemQuery();

        $this->query
            ->expects($this->once())
            ->method('setHint')
            ->with(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                'en'
            )
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Category());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itBuildsCorrectQueryForSingleItem(): void
    {
        $request = $this->createRequest('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('where')
            ->with('c.id = :id')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('id', 1)
            ->willReturnSelf();

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn(new Category());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ======================
    // Collection Retrieval Tests
    // ======================

    #[Test]
    public function itRetrievesCategoryCollection(): void
    {
        $request = $this->createRequest('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $categories = [
            (new Category())->setTitle('Category 1'),
            (new Category())->setTitle('Category 2'),
        ];

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn($categories);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(Category::class, $result);
    }

    #[Test]
    public function itAppliesDefaultOrderingForCollection(): void
    {
        $request = $this->createRequest('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('c.title', 'ASC')
            ->willReturnSelf();

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesGedmoTranslatableHintForCollection(): void
    {
        $request = $this->createRequest('ru');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupCollectionQuery();

        $this->query
            ->expects($this->once())
            ->method('setHint')
            ->with(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                'ru'
            )
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    // ======================
    // Filtering Tests
    // ======================

    #[Test]
    public function itFiltersByOnFrontPage(): void
    {
        $request = $this->createRequest('ro');
        $request->query = new InputBag(['onFrontPage' => 'true']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('c.onFrontPage = :onFrontPage')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('onFrontPage', true)
            ->willReturnSelf();

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itHandlesOnFrontPageFalseValue(): void
    {
        $request = $this->createRequest('ro');
        $request->query = new InputBag(['onFrontPage' => 'false']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('c.onFrontPage = :onFrontPage')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('onFrontPage', false)
            ->willReturnSelf();

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersByStatus(): void
    {
        $request = $this->createRequest('ro');
        $request->query = new InputBag(['status' => 'active']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('c.status = :status')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('status', 'active')
            ->willReturnSelf();

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesMultipleFiltersSimultaneously(): void
    {
        $request = $this->createRequest('ro');
        $request->query = new InputBag([
            'onFrontPage' => 'true',
            'status' => 'active',
        ]);
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
        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itDoesNotApplyFiltersWhenNotProvided(): void
    {
        $request = $this->createRequest('ro');
        $request->query = new InputBag(); // Empty query params
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // andWhere and setParameter should NOT be called for filters
        $this->queryBuilder
            ->expects($this->never())
            ->method('andWhere');

        $this->queryBuilder
            ->expects($this->never())
            ->method('setParameter');

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    // ======================
    // Edge Cases Tests
    // ======================

    #[Test]
    public function itHandlesEmptyCollectionResult(): void
    {
        $request = $this->createRequest('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function itWorksWhenRequestStackHasNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
    }

    // ======================
    // Helper Methods
    // ======================

    private function createRequest(string $locale): Request
    {
        $request = new Request();
        $request->setLocale($locale);
        $request->query = new InputBag();

        return $request;
    }

    private function setupSingleItemQuery(): void
    {
        $this->repository
            ->method('createQueryBuilder')
            ->with('c')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('where')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();

        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        $this->query->method('setHint')->willReturnSelf();
    }

    private function setupCollectionQuery(): void
    {
        $this->repository
            ->method('createQueryBuilder')
            ->with('c')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('orderBy')->willReturnSelf();

        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        $this->query->method('setHint')->willReturnSelf();
    }
}
