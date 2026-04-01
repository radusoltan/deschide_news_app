<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\ImportantArticlesList;
use App\Repository\ImportantArticlesListRepository;
use App\State\ImportantArticlesListProvider;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Translatable\TranslatableListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportantArticlesListProviderTest extends TestCase
{
    private ImportantArticlesListProvider $provider;
    private ImportantArticlesListRepository $repository;
    private RequestStack $requestStack;
    private QueryBuilder $queryBuilder;
    private Query $query;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ImportantArticlesListRepository::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);

        $this->provider = new ImportantArticlesListProvider(
            $this->repository,
            $this->requestStack
        );
    }

    #[Test]
    public function itRetrievesSingleItemWithTranslatableHint(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getPreferredLanguage')->with(['ro', 'en', 'ru'])->willReturn('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupQueryBuilder();

        $item = new ImportantArticlesList();
        $this->query->method('getOneOrNullResult')->willReturn($item);

        $this->query->expects($this->once())
            ->method('setHint')
            ->with(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'en')
            ->willReturnSelf();

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(ImportantArticlesList::class, $result);
    }

    #[Test]
    public function itReturnsNullWhenItemNotFound(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getPreferredLanguage')->willReturn('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupQueryBuilder();
        $this->query->method('getOneOrNullResult')->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itRetrievesCollectionOrderedByPosition(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getPreferredLanguage')->willReturn('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupQueryBuilder();

        $item1 = new ImportantArticlesList();
        $item2 = new ImportantArticlesList();
        $this->query->method('getResult')->willReturn([$item1, $item2]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function itUsesDefaultLocaleWhenNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->setupQueryBuilder();

        $this->query->expects($this->once())
            ->method('setHint')
            ->with(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'ro')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itEagerLoadsRelatedEntities(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getPreferredLanguage')->willReturn('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Verify joins for eager loading
        $this->queryBuilder->expects($this->exactly(5))->method('leftJoin')->willReturnSelf();
        $this->queryBuilder->expects($this->exactly(5))->method('addSelect')->willReturnSelf();

        $this->setupQueryBuilder();
        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    // ========================
    // Helper Methods
    // ========================

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

        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        $this->query->method('setHint')->willReturnSelf();
    }
}
