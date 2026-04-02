<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Article;
use App\State\ArchivedArticleProvider;
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
class ArchivedArticleProviderTest extends TestCase
{
    private ArchivedArticleProvider $provider;
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
            ->with(Article::class)
            ->willReturn($this->repository);

        $this->provider = new ArchivedArticleProvider(
            $this->entityManager,
            $this->requestStack
        );
    }

    // ========================
    // Single Item Tests
    // ========================

    #[Test]
    public function itRetrievesSingleArchivedArticleById(): void
    {
        $this->setupRequest('ro');
        $this->setupQueryBuilder();

        $article = new Article();
        $this->query->method('getOneOrNullResult')->willReturn($article);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itReturnsNullWhenArchivedArticleNotFound(): void
    {
        $this->setupRequest('ro');
        $this->setupQueryBuilder();

        $this->query->method('getOneOrNullResult')->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itAppliesTranslatableHintForSingleItem(): void
    {
        $this->setupRequest('en');
        $this->setupQueryBuilder();

        $hintsSet = [];
        $this->query->method('setHint')
            ->willReturnCallback(function ($hint, $value) use (&$hintsSet) {
                $hintsSet[$hint] = $value;
                return $this->query;
            });

        $this->query->method('getOneOrNullResult')->willReturn(new Article());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);

        $this->assertEquals('en', $hintsSet[TranslatableListener::HINT_TRANSLATABLE_LOCALE]);
    }

    #[Test]
    public function itEnablesResultCacheForSingleArchivedArticle(): void
    {
        $this->setupRequest('ro');
        $this->setupQueryBuilder();

        $this->query->expects($this->once())
            ->method('enableResultCache')
            ->with(7200, 'archived_article_1_ro');

        $this->query->method('getOneOrNullResult')->willReturn(new Article());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itEagerLoadsRelatedEntitiesForSingleItem(): void
    {
        $this->setupRequest('ro');

        // Verify 5 joins: category, authors, articleImages, image, tags
        $this->queryBuilder->expects($this->exactly(5))->method('leftJoin')->willReturnSelf();
        $this->queryBuilder->expects($this->exactly(5))->method('addSelect')->willReturnSelf();

        $this->setupQueryBuilder();
        $this->query->method('getOneOrNullResult')->willReturn(new Article());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ========================
    // Collection Tests
    // ========================

    #[Test]
    public function itRetrievesArchivedArticleCollection(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag();

        $this->setupQueryBuilder();

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        // Returns a DoctrinePaginator
        $this->assertNotNull($result);
    }

    #[Test]
    public function itFiltersCollectionByCategoryId(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['category' => '5']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersCollectionByArchiveReason(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['archiveReason' => 'outdated']);

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

    #[Test]
    public function itUsesDefaultOrderingForCollection(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag();

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->once())
            ->method('addOrderBy')
            ->with('a.archivedAt', 'DESC')
            ->willReturnSelf();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    // ========================
    // Locale Tests
    // ========================

    #[Test]
    public function itHandlesLocaleWithRegion(): void
    {
        $this->setupRequest('en-US');
        $this->setupQueryBuilder();

        $hintsSet = [];
        $this->query->method('setHint')
            ->willReturnCallback(function ($hint, $value) use (&$hintsSet) {
                $hintsSet[$hint] = $value;
                return $this->query;
            });

        $this->query->method('getOneOrNullResult')->willReturn(new Article());

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

        $this->queryBuilder->method('leftJoin')->willReturnSelf();
        $this->queryBuilder->method('addSelect')->willReturnSelf();
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
    }
}
