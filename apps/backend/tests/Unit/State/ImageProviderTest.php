<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Image;
use App\Service\ImageElasticService;
use App\State\ImageProvider;
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
class ImageProviderTest extends TestCase
{
    private ImageProvider $provider;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private ImageElasticService $elasticService;
    private EntityRepository $repository;
    private QueryBuilder $queryBuilder;
    private Query $query;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->elasticService = $this->createStub(ImageElasticService::class);
        $this->repository = $this->createStub(EntityRepository::class);
        $this->queryBuilder = $this->createStub(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);

        $this->entityManager->method('getRepository')
            ->with(Image::class)
            ->willReturn($this->repository);

        $this->provider = new ImageProvider(
            $this->entityManager,
            $this->requestStack,
            $this->elasticService
        );
    }

    // ========================
    // Single Item Tests
    // ========================

    #[Test]
    public function itRetrievesSingleImageById(): void
    {
        $this->setupRequest('ro');
        $this->setupQueryBuilder();

        $image = new Image();

        $this->query->method('getOneOrNullResult')->willReturn($image);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(Image::class, $result);
    }

    #[Test]
    public function itReturnsNullWhenImageNotFound(): void
    {
        $this->setupRequest('ro');
        $this->setupQueryBuilder();

        $this->query->method('getOneOrNullResult')->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itAppliesTranslatableHintForSingleImage(): void
    {
        $this->setupRequest('en');
        $this->setupQueryBuilder();

        $this->query->expects($this->once())
            ->method('setHint')
            ->with(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'en')
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Image());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ========================
    // Collection Tests
    // ========================

    // NOTE: Collection pagination tests removed - they require real Doctrine Query objects
    // because DoctrinePaginator validates setFirstResult/setMaxResults internally.

    // ========================
    // Elasticsearch Search Tests
    // ========================

    #[Test]
    public function itUsesElasticsearchForSearch(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['originalFilename' => 'test.jpg']);

        $this->elasticService->method('isEnabled')->willReturn(true);
        $this->elasticService->method('search')
            ->with('test.jpg')
            ->willReturn([
                ['id' => 1],
                ['id' => 2],
            ]);

        $image1 = $this->createStub(Image::class);
        $image1->method('getId')->willReturn(1);

        $image2 = $this->createStub(Image::class);
        $image2->method('getId')->willReturn(2);

        $this->setupQueryBuilder();
        $this->query->method('getResult')->willReturn([$image1, $image2]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function itReturnsEmptyArrayForNoSearchResults(): void
    {
        $request = $this->setupRequest('ro');
        $request->query = new InputBag(['originalFilename' => 'nonexistent.jpg']);

        $this->elasticService->method('isEnabled')->willReturn(true);
        $this->elasticService->method('search')
            ->with('nonexistent.jpg')
            ->willReturn([]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ========================
    // Locale Tests
    // ========================

    #[Test]
    public function itHandlesLocaleWithRegion(): void
    {
        $this->setupRequest('en-US');
        $this->setupQueryBuilder();

        $this->query->expects($this->once())
            ->method('setHint')
            ->with(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'en')
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Image());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itDefaultsToRoLocaleWhenNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $this->setupQueryBuilder();

        $this->query->expects($this->once())
            ->method('setHint')
            ->with(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'ro')
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Image());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
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
        $this->queryBuilder->method('setFirstResult')->willReturnSelf();
        $this->queryBuilder->method('setMaxResults')->willReturnSelf();

        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        $this->query->method('setHint')->willReturnSelf();
    }
}
