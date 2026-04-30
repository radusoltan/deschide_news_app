<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\State\ArticleProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Gedmo\Translatable\TranslatableListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Unit tests for ArticleProvider.
 *
 * Tests locale extraction, single/collection retrieval, filtering,
 * and Gedmo Translatable hint application.
 */
class ArticleProviderTest extends TestCase
{
    private ArticleProvider $provider;

    private EntityManagerInterface $entityManager;

    private RequestStack $requestStack;

    private Security $security;

    private EntityRepository $repository;

    private QueryBuilder $queryBuilder;

    private Query $query;

    private bool $editorGranted = false;

    private ?UserInterface $mockedUser = null;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->security = $this->createMock(Security::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);

        $this->entityManager
            ->method('getRepository')
            ->with(Article::class)
            ->willReturn($this->repository);

        // Defaults model an anonymous request: not granted ROLE_EDITOR, no user.
        // Tests asserting admin bypass flip $this->editorGranted = true; tests
        // for an authenticated non-editor user also set $this->mockedUser so
        // a future regression that swaps isGranted() for getUser() !== null
        // would be caught (the provider currently consults only isGranted, but
        // mocking getUser keeps the negative test path honest as defence-in-depth).
        $this->editorGranted = false;
        $this->mockedUser = null;
        $this->security
            ->method('isGranted')
            ->willReturnCallback(fn (string $attribute): bool => $attribute === 'ROLE_EDITOR' && $this->editorGranted);
        $this->security
            ->method('getUser')
            ->willReturnCallback(fn () => $this->mockedUser);

        $this->provider = new ArticleProvider(
            $this->entityManager,
            $this->requestStack,
            $this->security
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

        // The query should have locale hint set to 'ro'
        $this->query
            ->expects($this->exactly(2))
            ->method('setHint')
            ->willReturnCallback(function ($hint, $value) {
                if ($hint === TranslatableListener::HINT_TRANSLATABLE_LOCALE) {
                    $this->assertEquals('ro', $value);
                }
                return $this->query;
            });

        $this->query->method('getOneOrNullResult')->willReturn(new Article());

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
            ->expects($this->exactly(2))
            ->method('setHint')
            ->willReturnCallback(function ($hint, $value) use ($expectedLocale) {
                if ($hint === TranslatableListener::HINT_TRANSLATABLE_LOCALE) {
                    $this->assertEquals($expectedLocale, $value);
                }
                return $this->query;
            });

        $this->query->method('getOneOrNullResult')->willReturn(new Article());

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
            ->expects($this->exactly(2))
            ->method('setHint')
            ->willReturnCallback(function ($hint, $value) {
                if ($hint === TranslatableListener::HINT_TRANSLATABLE_LOCALE) {
                    $this->assertEquals('ro', $value); // Default locale
                }
                return $this->query;
            });

        $this->query->method('getOneOrNullResult')->willReturn(new Article());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ======================
    // Single Item Retrieval Tests
    // ======================

    #[Test]
    public function itRetrievesSingleArticleById(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('Test Article');

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn($article);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(Article::class, $result);
        $this->assertEquals('Test Article', $result->getTitle());
    }

    #[Test]
    public function itReturnsNullWhenArticleNotFound(): void
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
    public function itAppliesGedmoTranslatableHintForSingleItem(): void
    {
        $request = $this->createRequestWithLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupSingleItemQuery();

        // Verify both hints are set
        $hintsSet = [];
        $this->query
            ->expects($this->exactly(2))
            ->method('setHint')
            ->willReturnCallback(function ($hint, $value) use (&$hintsSet) {
                $hintsSet[$hint] = $value;
                return $this->query;
            });

        $this->query->method('getOneOrNullResult')->willReturn(new Article());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);

        $this->assertArrayHasKey(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $hintsSet);
        $this->assertArrayHasKey(TranslatableListener::HINT_INNER_JOIN, $hintsSet);
        $this->assertEquals('en', $hintsSet[TranslatableListener::HINT_TRANSLATABLE_LOCALE]);
        $this->assertEquals(false, $hintsSet[TranslatableListener::HINT_INNER_JOIN]);
    }

    #[Test]
    public function itEagerLoadsRelatedEntitiesForSingleItem(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Verify query builder joins related entities
        // Joins: category, authors, articleImages, image, tags = 5
        $this->queryBuilder
            ->expects($this->exactly(5))
            ->method('leftJoin')
            ->willReturnSelf();

        // addSelect: c, au, ai, img, t = 5
        $this->queryBuilder
            ->expects($this->exactly(5))
            ->method('addSelect')
            ->willReturnSelf();

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn(new Article());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    #[Test]
    public function itExcludesArchivedArticlesInSingleItemQuery(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('a.status != :archived_status')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn(new Article());

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 1]);
    }

    // ======================
    // Per-Locale Publishing Gate Scope Tests (ADR-027 / hotfix v1.4.1)
    // ======================

    #[Test]
    public function itAppliesLocaleGateOnPublicGetWhenArticleNotPublishedInLocale(): void
    {
        // Scenario: Accept-Language: en, article published only in ro
        // Public GET /api/articles/{id} => must return null (→ 404).
        $request = $this->createRequestWithLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('RO-only article');
        $article->setPublishedLocales(['ro']);

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn($article);

        $operation = (new Get())->withClass(Article::class);
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertNull($result, 'Public GET with non-published locale must return null');
    }

    #[Test]
    public function itAllowsPublicGetWhenArticleIsPublishedInRequestedLocale(): void
    {
        // Scenario: Accept-Language: ro, article published in ro
        // Public GET /api/articles/{id} => must return the article.
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('RO article');
        $article->setPublishedLocales(['ro']);

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn($article);

        $operation = (new Get())->withClass(Article::class);
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itBypassesLocaleGateForIriDenormalizationContext(): void
    {
        // Scenario: POST /api/article_images with "article": "/api/articles/{id}"
        // and Accept-Language: en, where article is only published in ro.
        // API Platform's AbstractItemNormalizer sets $context['fetch_data'] = true
        // when it calls IriConverter::getResourceFromIri(), which in turn calls
        // this provider. The gate MUST NOT apply in that case — otherwise the
        // IriConverter throws ItemNotFoundException and the write fails.
        $request = $this->createRequestWithLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('RO-only article');
        $article->setPublishedLocales(['ro']);

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn($article);

        $operation = (new Get())->withClass(Article::class);
        $result = $this->provider->provide($operation, ['id' => 1], ['fetch_data' => true]);

        $this->assertInstanceOf(Article::class, $result, 'IRI lookup must bypass per-locale gate');
    }

    #[Test]
    public function itBypassesLocaleGateForDraftArticleInIriDenormalization(): void
    {
        // Scenario: draft article (publishedLocales = []) referenced by IRI in
        // a write payload. Admin must be able to attach images even before the
        // article is published in any locale.
        $request = $this->createRequestWithLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('Draft article');
        $article->setPublishedLocales([]);

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn($article);

        $operation = (new Get())->withClass(Article::class);
        $result = $this->provider->provide($operation, ['id' => 1], ['fetch_data' => true]);

        $this->assertInstanceOf(Article::class, $result, 'Draft article must resolve via IRI in write path');
    }

    // ======================
    // Per-Locale Publishing Gate — Editor Bypass (T60.6 / hotfix v1.4.5)
    // ======================
    //
    // The discriminator was extended to bypass the gate for authenticated
    // editors, so the manual translation workflow can reach articles in
    // locales that aren't yet in publishedLocales. The provider consults
    // Security::isGranted('ROLE_EDITOR'), which delegates to RoleHierarchyVoter
    // — so a holder of ROLE_ADMIN passes the check via security.yaml's
    // role_hierarchy without us listing both roles literally.
    //
    // Tests below assert behaviour at the Provider level (path-agnostic) so
    // they survive the future admin-surface split tracked under ADR-027
    // Open Questions #2.

    #[Test]
    public function itAllowsEditorToReadArticleInLocaleNotInPublishedLocales(): void
    {
        // Primary regression: editor with Accept-Language: en reads an
        // article published only in ro. Pre-T60.6 this returned null
        // (→ 404 in HTTP land) and broke the manual translation tab switch.
        $this->editorGranted = true;
        $this->mockedUser = $this->createMock(UserInterface::class);

        $request = $this->createRequestWithLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('RO-only article');
        $article->setPublishedLocales(['ro']);

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn($article);

        $operation = (new Get())->withClass(Article::class);
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(
            Article::class,
            $result,
            'Editor must read an article in a locale not in publishedLocales'
        );
    }

    #[Test]
    public function itAllowsEditorToReadArticleInPublishedLocale(): void
    {
        // Sanity check: bypass must not regress the happy path for editors.
        $this->editorGranted = true;
        $this->mockedUser = $this->createMock(UserInterface::class);

        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('RO article');
        $article->setPublishedLocales(['ro']);

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn($article);

        $operation = (new Get())->withClass(Article::class);
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itAppliesLocaleGateForAuthenticatedNonEditorUser(): void
    {
        // Defence-in-depth: an authenticated user who is NOT granted
        // ROLE_EDITOR (e.g. a plain reader) must still hit the gate. The
        // mockedUser is intentionally non-null so a future regression that
        // swaps the discriminator for `getUser() !== null` would still fail
        // this assertion (gate would be bypassed; result would no longer be
        // null). Bypass is editor-grant-only, not "any-authenticated".
        $this->editorGranted = false;
        $this->mockedUser = $this->createMock(UserInterface::class);

        $request = $this->createRequestWithLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('RO-only article');
        $article->setPublishedLocales(['ro']);

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn($article);

        $operation = (new Get())->withClass(Article::class);
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertNull(
            $result,
            'Non-editor authenticated users must still be subject to the per-locale gate'
        );
    }

    #[Test]
    public function itDoesNotBypassGateForAnonymousUser(): void
    {
        // Meta-regression: catches a typo or sign-flip in the discriminator
        // (e.g. !$isAdminContext → $isAdminContext, or isGranted call removed).
        // Anonymous request: editorGranted=false, mockedUser=null (defaults).
        // Article published only in 'ro'; request locale 'en' → must return null.

        $request = $this->createRequestWithLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $article = new Article();
        $article->setTitle('RO-only article');
        $article->setPublishedLocales(['ro']);

        $this->setupSingleItemQuery();
        $this->query->method('getOneOrNullResult')->willReturn($article);

        $operation = (new Get())->withClass(Article::class);
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertNull(
            $result,
            'Anonymous user must never bypass the per-locale publishing gate'
        );
    }

    // ======================
    // Collection Retrieval Tests
    // ======================

    #[Test]
    public function itRetrievesArticleCollection(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertInstanceOf(\Doctrine\ORM\Tools\Pagination\Paginator::class, $result);
    }

    #[Test]
    public function itAppliesPaginationParameters(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $request->query = new InputBag(['page' => '2', 'itemsPerPage' => '10']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('setFirstResult')
            ->with(10) // (page 2 - 1) * 10
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(10)
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itEnforcesPaginationLimits(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $request->query = new InputBag(['page' => '0', 'itemsPerPage' => '200']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('setFirstResult')
            ->with(0) // page 0 becomes page 1, so (1-1)*100 = 0
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(100) // Max is 100
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    // ======================
    // Filtering Tests
    // ======================

    #[Test]
    public function itFiltersByCategoryId(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $request->query = new InputBag(['categoryId' => '5']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->exactly(3))
            ->method('andWhere')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->exactly(3))
            ->method('setParameter')
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersByStatus(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $request->query = new InputBag(['status' => 'published']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->exactly(3))
            ->method('andWhere')
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itFiltersByIsFeatured(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $request->query = new InputBag(['isFeatured' => 'true']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->exactly(3))
            ->method('andWhere')
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesCustomOrderBy(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $request->query = new InputBag(['order' => ['title' => 'ASC']]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('addOrderBy')
            ->with('a.title', 'ASC')
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itUsesDefaultOrderingWhenNoOrderSpecified(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->queryBuilder
            ->expects($this->once())
            ->method('addOrderBy')
            ->with('a.publishedAt', 'DESC')
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itValidatesOrderDirection(): void
    {
        $request = $this->createRequestWithLocale('ro');
        // Invalid direction values (not ASC or DESC) should be ignored, so falls through to else block
        $request->query = new InputBag(['order' => ['title' => 'invalid']]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // The foreach enters but doesn't call addOrderBy due to invalid direction
        // But because the orderBy array is not empty, the else block is not executed
        // So addOrderBy is never called at all
        $this->queryBuilder
            ->expects($this->never())
            ->method('addOrderBy');

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    #[Test]
    public function itAppliesGedmoTranslatableHintForCollection(): void
    {
        $request = $this->createRequestWithLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->setupCollectionQuery();

        $hintsSet = [];
        $this->query
            ->expects($this->exactly(2))
            ->method('setHint')
            ->willReturnCallback(function ($hint, $value) use (&$hintsSet) {
                $hintsSet[$hint] = $value;
                return $this->query;
            });

        $operation = new GetCollection();
        $this->provider->provide($operation);

        $this->assertEquals('en', $hintsSet[TranslatableListener::HINT_TRANSLATABLE_LOCALE]);
        $this->assertEquals(false, $hintsSet[TranslatableListener::HINT_INNER_JOIN]);
    }

    #[Test]
    public function itEagerLoadsRelatedEntitiesForCollection(): void
    {
        $request = $this->createRequestWithLocale('ro');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Verify all necessary joins for collection
        $this->queryBuilder
            ->expects($this->exactly(5))
            ->method('leftJoin')
            ->willReturnSelf();

        $this->setupCollectionQuery();

        $operation = new GetCollection();
        $this->provider->provide($operation);
    }

    // ======================
    // Helper Methods
    // ======================

    private function createRequestWithLocale(string $locale): Request
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => $locale]);
        $request->query = new InputBag();

        return $request;
    }

    private function setupSingleItemQuery(): void
    {
        $this->repository
            ->method('createQueryBuilder')
            ->with('a')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('leftJoin')->willReturnSelf();
        $this->queryBuilder->method('addSelect')->willReturnSelf();
        $this->queryBuilder->method('where')->willReturnSelf();
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('orderBy')->willReturnSelf();

        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        $this->query->method('setHint')->willReturnSelf();
    }

    private function setupCollectionQuery(): void
    {
        $this->repository
            ->method('createQueryBuilder')
            ->with('a')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('leftJoin')->willReturnSelf();
        $this->queryBuilder->method('addSelect')->willReturnSelf();
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('addOrderBy')->willReturnSelf();
        $this->queryBuilder->method('setFirstResult')->willReturnSelf();
        $this->queryBuilder->method('setMaxResults')->willReturnSelf();

        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        $this->query->method('setHint')->willReturnSelf();

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
