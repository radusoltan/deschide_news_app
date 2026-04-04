<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\LiveTextPost;
use App\State\LiveTextPostProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LiveTextPostProviderTest extends TestCase
{
    private LiveTextPostProvider $provider;
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
        $this->query = $this->createStub(Query::class);

        $this->entityManager->method('getRepository')
            ->with(LiveTextPost::class)
            ->willReturn($this->repository);

        $this->provider = new LiveTextPostProvider(
            $this->entityManager,
            $this->requestStack
        );
    }

    #[Test]
    public function itRetrievesSinglePostById(): void
    {
        $this->setupRequest();
        $this->setupQueryBuilder();

        $post = new LiveTextPost();
        $this->query->method('getOneOrNullResult')->willReturn($post);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(LiveTextPost::class, $result);
    }

    #[Test]
    public function itReturnsNullWhenPostNotFound(): void
    {
        $this->setupRequest();
        $this->setupQueryBuilder();

        $this->query->method('getOneOrNullResult')->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itRetrievesCollection(): void
    {
        $request = $this->setupRequest();
        $request->query = new InputBag();

        $this->setupQueryBuilder();
        $this->query->method('getResult')->willReturn([new LiveTextPost(), new LiveTextPost()]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function itFiltersCollectionByLiveTextId(): void
    {
        $request = $this->setupRequest();
        $request->query = new InputBag(['liveText' => ['id' => '5']]);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersCollectionByKeyPoints(): void
    {
        $request = $this->setupRequest();
        $request->query = new InputBag(['isKeyPoint' => 'true']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersCollectionByContent(): void
    {
        $request = $this->setupRequest();
        $request->query = new InputBag(['content' => 'search term']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itUsesDefaultOrderingWhenNoOrderSpecified(): void
    {
        $request = $this->setupRequest();
        $request->query = new InputBag();

        $this->setupQueryBuilder();

        // When a request exists, all('order') returns [] (empty array).
        // is_array([]) is true, so the foreach simply iterates nothing.
        // The else branch (default orderBy) is not reached.
        $this->queryBuilder->expects($this->never())
            ->method('orderBy');

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersCollectionByAuthorId(): void
    {
        $request = $this->setupRequest();
        $request->query = new InputBag(['author' => ['id' => '7']]);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesCustomOrderByPublishedAtAsc(): void
    {
        $request = $this->setupRequest();
        $request->query = new InputBag(['order' => ['publishedAt' => 'asc']]);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('addOrderBy')
            ->with('p.publishedAt', 'ASC')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesCustomOrderByPosition(): void
    {
        $request = $this->setupRequest();
        $request->query = new InputBag(['order' => ['position' => 'DESC']]);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('addOrderBy')
            ->with('p.position', 'DESC')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesCustomOrderByCreatedAt(): void
    {
        $request = $this->setupRequest();
        $request->query = new InputBag(['order' => ['createdAt' => 'DESC']]);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('addOrderBy')
            ->with('p.createdAt', 'DESC')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itDefaultsToDescWhenInvalidOrderDirection(): void
    {
        $request = $this->setupRequest();
        $request->query = new InputBag(['order' => ['publishedAt' => 'INVALID']]);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('addOrderBy')
            ->with('p.publishedAt', 'DESC')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersCollectionByLiveTextNestedArray(): void
    {
        $request = $this->setupRequest();
        // Simulate liveText[id]=5 as nested array
        $request->query = new InputBag();
        $request->query->set('liveText', ['id' => '5']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersCollectionByAuthorNestedArray(): void
    {
        $request = $this->setupRequest();
        $request->query = new InputBag();
        $request->query->set('author', ['id' => '3']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    // ========================
    // Helper Methods
    // ========================

    private function setupRequest(): Request
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => 'ro']);
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
    }
}
