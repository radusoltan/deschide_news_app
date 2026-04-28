<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Regression tests for the ArticleProvider COLLECTION endpoint locale filter.
 *
 * Hotfix v1.4.1 / ADR-027 — see docs/adr/ADR-027-article-provider-locale-gate-scope.md
 *
 * Sibling to ArticleProviderLocaleTest which covers the SINGLE-item gate
 * (GET /api/articles/{id} returns 404 on locale mismatch).
 *
 * This file covers the COLLECTION endpoint (GET /api/articles), where
 * filtering is done at the SQL level via:
 *
 *     ARRAY_CONTAINS(a.publishedLocales, :currentLocale) = true
 *
 * Gap identified during T59.5 validation: only the single-item path was
 * covered by functional tests. The collection filter relies on the
 * `ARRAY_CONTAINS` DQL function and is a regression risk if the provider
 * is ever refactored.
 *
 * Strategy: each test creates fixture articles with controlled
 * publishedLocales values. We do NOT rely on exact baseline counts in the
 * test DB — we assert presence/absence of the SPECIFIC fixture IDs in the
 * `member` collection. DAMA Doctrine Test Bundle rolls back the
 * transaction between tests, so fixtures don't leak.
 */
class ArticleProviderCollectionLocaleFilterTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->em = $container->get('doctrine')->getManager();
    }

    /**
     * GET /api/articles with Accept-Language: en MUST return only articles
     * whose publishedLocales contains 'en'. The RO-only fixture article
     * MUST be excluded; the trilingual fixture MUST be included.
     */
    public function testCollectionWithEnglishAcceptLanguageExcludesArticlesNotPublishedInEnglish(): void
    {
        $marker = $this->uniqueMarker();
        $roOnlyArticle = $this->createArticle(['ro'], ArticleStatus::PUBLISHED, $marker);
        $trilingualArticle = $this->createArticle(['ro', 'en', 'ru'], ArticleStatus::PUBLISHED, $marker);

        // Filter by unique marker so we don't depend on baseline article counts.
        $members = $this->fetchCollection('en', $marker);
        $memberIds = $this->extractMemberIds($members);

        $this->assertContains(
            $trilingualArticle->getId(),
            $memberIds,
            sprintf(
                'Trilingual article #%d (publishedLocales=[ro,en,ru]) MUST appear in '
                . 'GET /api/articles?Accept-Language=en collection. Got member IDs: %s',
                $trilingualArticle->getId(),
                json_encode($memberIds)
            )
        );

        $this->assertNotContains(
            $roOnlyArticle->getId(),
            $memberIds,
            sprintf(
                'RO-only article #%d (publishedLocales=[ro]) MUST be filtered OUT of '
                . 'GET /api/articles?Accept-Language=en collection (ADR-027). Got: %s',
                $roOnlyArticle->getId(),
                json_encode($memberIds)
            )
        );

        // Defensive: every returned member MUST be published in 'en'
        // (we cannot assert on every member here without a follow-up DB query
        // because hydra:member entries are partial; instead we trust the SQL
        // filter and verify our two fixture cases cover the behaviour).
    }

    /**
     * GET /api/articles with Accept-Language: ru MUST return only articles
     * whose publishedLocales contains 'ru'.
     */
    public function testCollectionWithRussianAcceptLanguageExcludesArticlesNotPublishedInRussian(): void
    {
        $marker = $this->uniqueMarker();
        $roOnlyArticle = $this->createArticle(['ro'], ArticleStatus::PUBLISHED, $marker);
        $trilingualArticle = $this->createArticle(['ro', 'en', 'ru'], ArticleStatus::PUBLISHED, $marker);

        $members = $this->fetchCollection('ru', $marker);
        $memberIds = $this->extractMemberIds($members);

        $this->assertContains(
            $trilingualArticle->getId(),
            $memberIds,
            sprintf(
                'Trilingual article #%d (publishedLocales=[ro,en,ru]) MUST appear in '
                . 'GET /api/articles?Accept-Language=ru collection. Got: %s',
                $trilingualArticle->getId(),
                json_encode($memberIds)
            )
        );

        $this->assertNotContains(
            $roOnlyArticle->getId(),
            $memberIds,
            sprintf(
                'RO-only article #%d MUST be filtered OUT of '
                . 'GET /api/articles?Accept-Language=ru collection (ADR-027). Got: %s',
                $roOnlyArticle->getId(),
                json_encode($memberIds)
            )
        );
    }

    /**
     * GET /api/articles with Accept-Language: ro returns articles where
     * publishedLocales contains 'ro'. Both the RO-only and the trilingual
     * fixture MUST be present — neither should be filtered out.
     */
    public function testCollectionWithRomanianAcceptLanguageReturnsAllPublishedRomanianArticles(): void
    {
        $marker = $this->uniqueMarker();
        $roOnlyArticle = $this->createArticle(['ro'], ArticleStatus::PUBLISHED, $marker);
        $trilingualArticle = $this->createArticle(['ro', 'en', 'ru'], ArticleStatus::PUBLISHED, $marker);

        $members = $this->fetchCollection('ro', $marker);
        $memberIds = $this->extractMemberIds($members);

        $this->assertContains(
            $roOnlyArticle->getId(),
            $memberIds,
            sprintf(
                'RO-only article #%d MUST appear in GET /api/articles?Accept-Language=ro. Got: %s',
                $roOnlyArticle->getId(),
                json_encode($memberIds)
            )
        );

        $this->assertContains(
            $trilingualArticle->getId(),
            $memberIds,
            sprintf(
                'Trilingual article #%d MUST appear in GET /api/articles?Accept-Language=ro. Got: %s',
                $trilingualArticle->getId(),
                json_encode($memberIds)
            )
        );
    }

    /**
     * Negative-control: an article published with publishedLocales=['en']
     * (no 'ro') MUST be filtered out for Accept-Language: ro. This guards
     * against a regression where the gate is bypassed if the article has
     * any publishedLocales at all.
     */
    public function testCollectionFiltersOutArticlePublishedOnlyInForeignLocale(): void
    {
        $marker = $this->uniqueMarker();
        $enOnlyArticle = $this->createArticle(['en'], ArticleStatus::PUBLISHED, $marker);

        $members = $this->fetchCollection('ro', $marker);
        $memberIds = $this->extractMemberIds($members);

        $this->assertNotContains(
            $enOnlyArticle->getId(),
            $memberIds,
            sprintf(
                'EN-only article #%d (publishedLocales=[en]) MUST NOT appear in '
                . 'GET /api/articles?Accept-Language=ro collection. Got: %s',
                $enOnlyArticle->getId(),
                json_encode($memberIds)
            )
        );
    }

    // ======================
    // Helpers
    // ======================

    /**
     * Issue GET /api/articles filtered by `?title={marker}` with the given
     * Accept-Language, and return the `member` array from the hydra/JSON-LD
     * response. Filtering by a unique marker isolates fixtures from the
     * baseline test DB content (which contains thousands of unrelated
     * RO-only published articles).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchCollection(string $acceptLanguage, string $titleMarker): array
    {
        $url = '/api/articles?itemsPerPage=100&title=' . urlencode($titleMarker);

        $this->client->request(
            'GET',
            $url,
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_ACCEPT_LANGUAGE' => $acceptLanguage,
            ]
        );

        $this->assertResponseStatusCodeSame(
            Response::HTTP_OK,
            'GET /api/articles must return 200. Body: '
            . substr((string) $this->client->getResponse()->getContent(), 0, 500)
        );

        $payload = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        // API Platform 3.x uses bare keys (no `hydra:` prefix) on JSON-LD output.
        // Fallback to legacy `hydra:member` for safety.
        $members = $payload['member'] ?? $payload['hydra:member'] ?? null;

        $this->assertIsArray(
            $members,
            'Collection response must contain a `member` array. Payload keys: '
            . implode(',', array_keys((array) $payload))
        );

        return $members;
    }

    /**
     * Extract numeric IDs from the member array. API Platform exposes the
     * IRI as `@id` (e.g. "/api/articles/123") and may also expose `id`.
     *
     * @param array<int, array<string, mixed>> $members
     * @return int[]
     */
    private function extractMemberIds(array $members): array
    {
        $ids = [];
        foreach ($members as $member) {
            if (isset($member['id']) && is_numeric($member['id'])) {
                $ids[] = (int) $member['id'];
                continue;
            }
            if (isset($member['@id']) && \is_string($member['@id'])) {
                if (preg_match('#/api/articles/(\d+)#', $member['@id'], $matches) === 1) {
                    $ids[] = (int) $matches[1];
                }
            }
        }
        return $ids;
    }

    /**
     * Create an Article directly in the DB. Cleanup is handled by the
     * DAMA Doctrine Test Bundle transaction rollback.
     *
     * @param string[] $publishedLocales
     */
    private function createArticle(
        array $publishedLocales,
        ArticleStatus $status,
        string $titleMarker
    ): Article {
        $article = new Article();
        $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
        // Title carries the unique marker so the SearchFilter (LIKE) in the
        // provider can isolate fixtures from baseline DB content.
        $article->setTitle("{$titleMarker} fixture {$uniq}");
        $article->setLead('Regression fixture for ADR-027 collection filter');
        $article->setContent('<p>Body</p>');
        $article->setStatus($status);
        $article->setPublishedLocales($publishedLocales);

        if ($status === ArticleStatus::PUBLISHED) {
            $article->setPublishedAt(new \DateTimeImmutable());
        }

        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }

    /**
     * Generate a unique-per-test title marker. The format is intentionally
     * uncommon so the partial-match `?title=...` filter (LIKE %marker%)
     * yields zero collisions with the baseline test DB content.
     */
    private function uniqueMarker(): string
    {
        return 'adr027-marker-' . bin2hex(random_bytes(6));
    }
}
