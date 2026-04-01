<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\LiveTextReaction;
use App\State\LiveTextReactionProvider;
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
class LiveTextReactionProviderTest extends TestCase
{
    private LiveTextReactionProvider $provider;
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
            ->with(LiveTextReaction::class)
            ->willReturn($this->repository);

        $this->provider = new LiveTextReactionProvider(
            $this->entityManager,
            $this->requestStack
        );
    }

    #[Test]
    public function itRetrievesSingleReactionById(): void
    {
        $reaction = $this->createStub(LiveTextReaction::class);
        $this->repository->method('find')->with(1)->willReturn($reaction);

        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(LiveTextReaction::class, $result);
    }

    #[Test]
    public function itReturnsNullWhenReactionNotFound(): void
    {
        $this->repository->method('find')->with(999)->willReturn(null);
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itRetrievesCollectionOrderedByCreatedAtDesc(): void
    {
        $this->setupRequest();
        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('r.createdAt', 'DESC')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
    }

    #[Test]
    public function itFiltersCollectionByPostId(): void
    {
        $request = $this->setupRequest();
        $request->query = new InputBag(['liveTextPost' => '5']);

        $this->setupQueryBuilder();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersCollectionByReactionType(): void
    {
        $request = $this->setupRequest();
        $request->query = new InputBag(['reactionType' => 'like']);

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
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('orderBy')->willReturnSelf();

        $this->queryBuilder->method('getQuery')->willReturn($this->query);
    }
}
