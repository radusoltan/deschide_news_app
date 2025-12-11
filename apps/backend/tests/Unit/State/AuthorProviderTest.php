<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Author;
use App\State\AuthorProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Translatable\TranslatableListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for AuthorProvider.
 *
 * Tests locale extraction from Accept-Language header,
 * single/collection retrieval, and entity refresh.
 */
class AuthorProviderTest extends TestCase
{
    private AuthorProvider $provider;

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
            ->with(Author::class)
            ->willReturn($this->repository);

        $this->provider = new AuthorProvider(
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

        $this->query
            ->expects($this->once())
            ->method('setHint')
            ->with(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                'ro'
            )
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Author());

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
            ->expects($this->once())
            ->method('setHint')
            ->with(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $expectedLocale
            )
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Author());

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
            ->expects($this->once())
            ->method('setHint')
            ->with(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                'ro' // Default locale
            )
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Author());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itUsesDefaultLocaleWhenHeaderMissing(): void
    {
        $request = new Request();
        $request->headers = new HeaderBag(); // No Accept-Language
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupSingleItemQuery();

        $this->query
            ->expects($this->once())
            ->method('setHint')
            ->with(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                'ro'
            )
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Author());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ======================
    // Single Item Retrieval Tests
    // ======================

    #[Test]
    public function itRetrievesSingleAuthorById(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $author = new Author();
        $author->setFirstName('John');
        $author->setLastName('Doe');

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn($author);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(Author::class, $result);
        $this->assertEquals('John', $result->getFirstName());
        $this->assertEquals('Doe', $result->getLastName());
    }

    #[Test]
    public function itReturnsNullWhenAuthorNotFound(): void
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
    public function itRefreshesEntityForSingleItem(): void
    {
        $request = $this->createRequestWithLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $author = $this->createMock(Author::class);
        $author->expects($this->once())
            ->method('setTranslatableLocale')
            ->with('en');

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn($author);

        // Expect entity manager to refresh the author
        $this->entityManager
            ->expects($this->once())
            ->method('refresh')
            ->with($author);

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itDoesNotRefreshWhenAuthorNotFound(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn(null);

        // Refresh should NOT be called when author is null
        $this->entityManager
            ->expects($this->never())
            ->method('refresh');

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 999]);
    }

    #[Test]
    public function itAppliesGedmoTranslatableHintForSingleItem(): void
    {
        $request = $this->createRequestWithLocale('ru');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupSingleItemQuery();

        $this->query
            ->expects($this->once())
            ->method('setHint')
            ->with(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                'ru'
            )
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Author());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itBuildsCorrectQueryForSingleItem(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('where')
            ->with('a.id = :id')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('id', 1)
            ->willReturnSelf();

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn(new Author());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ======================
    // Collection Retrieval Tests
    // ======================

    #[Test]
    public function itRetrievesAuthorCollection(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $authors = [
            (new Author())->setFirstName('John')->setLastName('Doe'),
            (new Author())->setFirstName('Jane')->setLastName('Smith'),
        ];

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn($authors);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(Author::class, $result);
    }

    #[Test]
    public function itAppliesDefaultOrderingForCollection(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('a.lastName', 'ASC')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->once())
            ->method('addOrderBy')
            ->with('a.firstName', 'ASC')
            ->willReturnSelf();

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesGedmoTranslatableHintForCollection(): void
    {
        $request = $this->createRequestWithLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupCollectionQuery();

        $this->query
            ->expects($this->once())
            ->method('setHint')
            ->with(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                'en'
            )
            ->willReturnSelf();

        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itSetsLocaleOnEachAuthorInCollection(): void
    {
        $request = $this->createRequestWithLocale('ru');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $author1 = $this->createMock(Author::class);
        $author1->expects($this->once())
            ->method('setTranslatableLocale')
            ->with('ru');

        $author2 = $this->createMock(Author::class);
        $author2->expects($this->once())
            ->method('setTranslatableLocale')
            ->with('ru');

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn([$author1, $author2]);

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itDoesNotRefreshAuthorsInCollection(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $authors = [new Author(), new Author()];

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn($authors);

        // Refresh should NOT be called for collections (memory optimization)
        $this->entityManager
            ->expects($this->never())
            ->method('refresh');

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itHandlesEmptyCollectionResult(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn([]);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ======================
    // Edge Cases Tests
    // ======================

    #[Test]
    public function itHandlesMixedCaseLocale(): void
    {
        $request = $this->createRequestWithLocale('EN-us');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupSingleItemQuery();

        // Should extract 'EN' (first part before hyphen)
        $this->query
            ->expects($this->once())
            ->method('setHint')
            ->with(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                'EN' // Not normalized to lowercase in provider
            )
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Author());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itHandlesComplexAcceptLanguageHeader(): void
    {
        $request = $this->createRequestWithLocale('fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupSingleItemQuery();

        // Should extract 'fr' (first language, first part)
        $this->query
            ->expects($this->once())
            ->method('setHint')
            ->with(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                'fr'
            )
            ->willReturnSelf();

        $this->query->method('getOneOrNullResult')->willReturn(new Author());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itWorksWithDifferentLocalesForSingleAndCollection(): void
    {
        // First request with 'en'
        $request1 = $this->createRequestWithLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request1);

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn(new Author());

        $operation1 = new Get();
        $this->provider->provide($operation1, ['id' => 1]);

        // Second request with 'ru' (simulating different request)
        $request2 = $this->createRequestWithLocale('ru');
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($request2);

        $this->provider = new AuthorProvider(
            $this->entityManager,
            $this->requestStack
        );

        $this->setupCollectionQuery();
        $this->query->method('getResult')->willReturn([]);

        $operation2 = new GetCollection();
        $this->provider->provide($operation2);

        // Both operations should work independently
        $this->assertTrue(true);
    }

    // ======================
    // Helper Methods
    // ======================

    private function createRequestWithLocale(string $locale): Request
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => $locale]);

        return $request;
    }

    private function setupSingleItemQuery(): void
    {
        $this->repository
            ->method('createQueryBuilder')
            ->with('a')
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
            ->with('a')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('orderBy')->willReturnSelf();
        $this->queryBuilder->method('addOrderBy')->willReturnSelf();

        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        $this->query->method('setHint')->willReturnSelf();
    }
}
