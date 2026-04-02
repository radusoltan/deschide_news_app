<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\ThumbnailProfile;
use App\State\ThumbnailProfileProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Translatable\TranslatableListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ThumbnailProfileProviderTest extends TestCase
{
    private ThumbnailProfileProvider $provider;
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
        $this->queryBuilder = $this->createStub(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);

        $this->entityManager->method('getRepository')
            ->with(ThumbnailProfile::class)
            ->willReturn($this->repository);

        $this->provider = new ThumbnailProfileProvider(
            $this->entityManager,
            $this->requestStack
        );
    }

    #[Test]
    public function itRetrievesSingleProfileById(): void
    {
        $this->setupRequest('ro');
        $this->setupQueryBuilder();

        $profile = new ThumbnailProfile();
        $this->query->method('getOneOrNullResult')->willReturn($profile);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(ThumbnailProfile::class, $result);
    }

    #[Test]
    public function itReturnsNullWhenProfileNotFound(): void
    {
        $this->setupRequest('ro');
        $this->setupQueryBuilder();

        $this->query->method('getOneOrNullResult')->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itAppliesTranslatableHintForSingleProfile(): void
    {
        $this->setupRequest('en');
        $this->setupQueryBuilder();

        $this->query->expects($this->once())
            ->method('setHint')
            ->with(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'en')
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new ThumbnailProfile());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itRetrievesCollectionOrderedByCategoryAndWidth(): void
    {
        $this->setupRequest('ro');
        $this->setupQueryBuilder();

        $profile1 = new ThumbnailProfile();
        $profile2 = new ThumbnailProfile();
        $this->query->method('getResult')->willReturn([$profile1, $profile2]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function itHandlesLocaleWithRegion(): void
    {
        $this->setupRequest('en-GB');
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
    // Helper Methods
    // ========================

    private function setupRequest(string $locale): void
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => $locale]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
    }

    private function setupQueryBuilder(): void
    {
        $this->repository->method('createQueryBuilder')->willReturn($this->queryBuilder);

        $this->queryBuilder->method('where')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('orderBy')->willReturnSelf();
        $this->queryBuilder->method('addOrderBy')->willReturnSelf();

        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        $this->query->method('setHint')->willReturnSelf();
    }
}
