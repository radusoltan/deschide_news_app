<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\LiveText;
use App\State\LiveTextProvider;
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
class LiveTextProviderTest extends TestCase
{
    private LiveTextProvider $provider;
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
            ->with(LiveText::class)
            ->willReturn($this->repository);

        $this->provider = new LiveTextProvider(
            $this->entityManager,
            $this->requestStack
        );
    }

    #[Test]
    public function itRetrievesSingleLiveTextById(): void
    {
        $this->setupRequest('ro');
        $this->setupQueryBuilder();

        $liveText = new LiveText();
        $this->query->method('getOneOrNullResult')->willReturn($liveText);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(LiveText::class, $result);
    }

    #[Test]
    public function itReturnsNullWhenLiveTextNotFound(): void
    {
        $this->setupRequest('ro');
        $this->setupQueryBuilder();

        $this->query->method('getOneOrNullResult')->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itAppliesTranslatableHint(): void
    {
        $this->setupRequest('en');
        $this->setupQueryBuilder();

        $this->query->expects($this->once())
            ->method('setHint')
            ->with(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'en')
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new LiveText());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itRetrievesCollection(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag();

        $this->setupQueryBuilder();
        $this->query->method('getResult')->willReturn([new LiveText(), new LiveText()]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function itFiltersCollectionByStatus(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['status' => 'live']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersCollectionByActiveStatus(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['isActive' => 'true']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersCollectionByTitle(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['title' => 'match']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
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

    // ========================
    // Locale parsing
    // ========================

    #[Test]
    public function itParsesLocaleFromAcceptLanguageWithDash(): void
    {
        $request = $this->setupRequest('en-US');
        $request->query = new InputBag();
        $this->setupQueryBuilder();

        $this->query->expects($this->once())
            ->method('setHint')
            ->with(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'en')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itParsesLocaleFromAcceptLanguageWithComma(): void
    {
        $request = $this->setupRequest('ru,en;q=0.9');
        $request->query = new InputBag();
        $this->setupQueryBuilder();

        $this->query->expects($this->once())
            ->method('setHint')
            ->with(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'ru')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    // ========================
    // Collection filters
    // ========================

    #[Test]
    public function itFiltersByCategoryIdFromNestedParam(): void
    {
        $request = $this->setupRequest('ro');
        // Simulate category[id]=5
        $request->query = new InputBag(['category' => ['id' => '5']]);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itSkipsCategoryFilterWhenNotProvided(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag([]);

        $this->setupQueryBuilder();
        $this->query->method('getResult')->willReturn([new LiveText()]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertCount(1, $result);
    }

    #[Test]
    public function itFiltersCollectionBySlug(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['slug' => 'my-live-text']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersCollectionByInactiveStatus(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['isActive' => 'false']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    // ========================
    // Ordering
    // ========================

    #[Test]
    public function itAppliesCustomOrderingFromQueryParams(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['order' => ['startTime' => 'ASC']]);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('addOrderBy')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesOrderingByEndTime(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['order' => ['endTime' => 'DESC']]);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('addOrderBy')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesOrderingByCreatedAt(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['order' => ['createdAt' => 'ASC']]);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('addOrderBy')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesOrderingByTitle(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['order' => ['title' => 'ASC']]);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('addOrderBy')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itDefaultsInvalidDirectionToDESC(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['order' => ['startTime' => 'INVALID']]);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('addOrderBy')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itUsesDefaultOrderingWhenNoOrderParam(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag();

        $this->setupQueryBuilder();
        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        // Verify no error and result is returned (default ordering is applied)
        $this->assertIsArray($result);
    }

    // ========================
    // Multiple filters combined
    // ========================

    #[Test]
    public function itAppliesMultipleFiltersTogether(): void
    {
        $request = $this->setupRequest('en');
        $request->query = new InputBag([
            'status' => 'live',
            'title' => 'football',
            'slug' => 'football-match',
            'order' => ['startTime' => 'DESC'],
        ]);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
    }

    #[Test]
    public function itReturnsEmptyCollectionWhenNoResults(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['status' => 'ended']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
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

        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        $this->query->method('setHint')->willReturnSelf();
    }
}
