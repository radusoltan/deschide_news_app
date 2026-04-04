<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Tag;
use App\State\TagProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Translatable\TranslatableListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class TagProviderTest extends TestCase
{
    private TagProvider $provider;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private EntityRepository $repository;
    private QueryBuilder $queryBuilder;
    private Query $query;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->repository = $this->createStub(EntityRepository::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);

        $this->entityManager->method('getRepository')
            ->with(Tag::class)
            ->willReturn($this->repository);

        $this->provider = new TagProvider(
            $this->entityManager,
            $this->requestStack
        );
    }

    // ========================
    // Single Item Tests
    // ========================

    #[Test]
    public function itRetrievesSingleTagById(): void
    {
        $this->setupRequest('ro');
        $this->setupQueryBuilder();

        $tag = new Tag();
        $this->query->method('getOneOrNullResult')->willReturn($tag);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(Tag::class, $result);
    }

    #[Test]
    public function itReturnsNullWhenTagNotFound(): void
    {
        $this->setupRequest('ro');
        $this->setupQueryBuilder();

        $this->query->method('getOneOrNullResult')->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itAppliesTranslatableHintForSingleTag(): void
    {
        $this->setupRequest('en');
        $this->setupQueryBuilder();

        $hintsSet = [];
        $this->query->method('setHint')
            ->willReturnCallback(function ($hint, $value) use (&$hintsSet) {
                $hintsSet[$hint] = $value;
                return $this->query;
            });

        $this->query->method('getOneOrNullResult')->willReturn(new Tag());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);

        $this->assertArrayHasKey(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $hintsSet);
        $this->assertEquals('en', $hintsSet[TranslatableListener::HINT_TRANSLATABLE_LOCALE]);
    }

    #[Test]
    public function itEnablesResultCacheForSingleTag(): void
    {
        $this->setupRequest('ro');
        $this->setupQueryBuilder();

        $this->query->expects($this->once())
            ->method('enableResultCache')
            ->with(300, 'tag_1_ro');

        $this->query->method('getOneOrNullResult')->willReturn(new Tag());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ========================
    // Collection Tests
    // ========================

    #[Test]
    public function itRetrievesCollectionWithDefaultOrdering(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag();

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('t.usageCount', 'DESC')
            ->willReturnSelf();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersCollectionByName(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['name' => 'test']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('LOWER(t.name) LIKE LOWER(:name)')
            ->willReturnSelf();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersCollectionBySlug(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['slug' => 'test-slug']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersByMinUsage(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['minUsage' => '5']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesPaginationToCollection(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['page' => '2', 'itemsPerPage' => '10']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->once())
            ->method('setFirstResult')
            ->with(10)
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->with(10)
            ->willReturnSelf();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    // ========================
    // Locale Tests
    // ========================

    #[Test]
    public function itHandlesComplexLocaleHeader(): void
    {
        $this->setupRequest('en-US,en;q=0.9');
        $this->setupQueryBuilder();

        $hintsSet = [];
        $this->query->method('setHint')
            ->willReturnCallback(function ($hint, $value) use (&$hintsSet) {
                $hintsSet[$hint] = $value;
                return $this->query;
            });

        $this->query->method('getOneOrNullResult')->willReturn(new Tag());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);

        $this->assertEquals('en', $hintsSet[TranslatableListener::HINT_TRANSLATABLE_LOCALE]);
    }

    // ========================
    // Helper Methods
    // ========================

    private function setupRequest(string $locale): Request
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => $locale]);
        $request->query = new InputBag();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        return $request;
    }

    private function setupQueryBuilder(): void
    {
        $this->repository->method('createQueryBuilder')->willReturn($this->queryBuilder);

        $this->queryBuilder->method('where')->willReturnSelf();
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('orderBy')->willReturnSelf();
        $this->queryBuilder->method('addOrderBy')->willReturnSelf();
        $this->queryBuilder->method('setFirstResult')->willReturnSelf();
        $this->queryBuilder->method('setMaxResults')->willReturnSelf();

        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        $this->query->method('setHint')->willReturnSelf();
        $this->query->method('enableResultCache')->willReturnSelf();

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
