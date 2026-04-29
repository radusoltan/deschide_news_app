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
 * Functional tests for SlugController::getArticleBySlug against a real DB
 * (Doctrine + Gedmo Translatable join through ext_translations).
 *
 * Sibling to the existing tests/Unit/Controller/Api/SlugControllerTest.php,
 * which mocks the QueryBuilder chain. Mocked unit tests cannot validate the
 * actual Gedmo Translatable join — which is the whole point of the
 * multilingual lookup. These functional tests close that gap (T59.5 / B4).
 *
 * Approach: each test creates a fixture Article (RO base slug) and inserts
 * matching rows into the `ext_translations` table for EN and RU. DAMA
 * Doctrine Test Bundle rolls the transaction back at the end of the test.
 *
 * KNOWN IMPLEMENTATION BUG (T59.5 / B4 — caught by these tests):
 * SlugController::getArticleBySlug() declares two leftJoin aliases named
 * `t`: one for `a.tags` (line 67–68), and one for the Gedmo Translation
 * entity (line 79–84). On any non-RO locale, Doctrine raises
 * `QueryException: "t" is already defined`, the controller returns 500,
 * and lookups by EN/RU slug never resolve. The mocked unit tests cannot
 * catch this because `createQueryBuilderChainReturning` mocks `leftJoin`
 * to return self irrespective of alias collisions.
 *
 * The two tests asserting 200 on EN/RU (`...ForEnglishLocale...`,
 * `...ForRussianLocale...`) WILL FAIL until the alias collision is
 * resolved (rename the tags alias from `t` to e.g. `tg` or rename the
 * translation alias). They are kept as failing regression tests so the
 * orchestrator can route the fix to Radu.
 */
class SlugControllerLocaleTest extends WebTestCase
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
     * GET /api/articles/by-slug/{ro_slug}?locale=ro returns the published
     * trilingual article for the default locale.
     */
    public function testGetArticleBySlugReturnsArticleForDefaultRomanianLocale(): void
    {
        $marker = $this->uniqueMarker();
        $roSlug = "adr027-by-slug-ro-{$marker}";
        $enSlug = "adr027-by-slug-en-{$marker}";
        $ruSlug = "adr027-by-slug-ru-{$marker}";

        $article = $this->createTrilingualArticle($roSlug, $enSlug, $ruSlug);

        $this->client->request('GET', "/api/articles/by-slug/{$roSlug}?locale=ro", [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(
            Response::HTTP_OK,
            'GET by RO slug with locale=ro must return 200. Body: '
            . substr((string) $this->client->getResponse()->getContent(), 0, 500)
        );

        $data = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            $article->getId(),
            $data['id'] ?? null,
            'Response must point at the fixture article id'
        );
    }

    /**
     * GET /api/articles/by-slug/{en_slug}?locale=en resolves through the
     * ext_translations table (Gedmo Translatable join) — this is the path
     * the mocked unit test cannot exercise.
     */
    public function testGetArticleBySlugReturnsArticleForEnglishLocaleViaTranslationsTable(): void
    {
        $marker = $this->uniqueMarker();
        $roSlug = "adr027-by-slug-ro-{$marker}";
        $enSlug = "adr027-by-slug-en-{$marker}";
        $ruSlug = "adr027-by-slug-ru-{$marker}";

        $article = $this->createTrilingualArticle($roSlug, $enSlug, $ruSlug);

        $this->client->request('GET', "/api/articles/by-slug/{$enSlug}?locale=en", [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(
            Response::HTTP_OK,
            'GET by EN slug with locale=en must return 200 (Gedmo translations join). Body: '
            . substr((string) $this->client->getResponse()->getContent(), 0, 500)
        );

        $data = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            $article->getId(),
            $data['id'] ?? null,
            'Response must point at the fixture article id'
        );

        // Title is loaded via Gedmo Translatable; on EN locale it should be
        // the EN translation we inserted into ext_translations.
        $this->assertSame(
            "Trilingual fixture EN {$marker}",
            $data['title'] ?? null,
            'Title MUST come from ext_translations for the EN locale (Gedmo Translatable)'
        );
    }

    /**
     * GET /api/articles/by-slug/{ru_slug}?locale=ru resolves through the
     * ext_translations table on the RU locale.
     */
    public function testGetArticleBySlugReturnsArticleForRussianLocaleViaTranslationsTable(): void
    {
        $marker = $this->uniqueMarker();
        $roSlug = "adr027-by-slug-ro-{$marker}";
        $enSlug = "adr027-by-slug-en-{$marker}";
        $ruSlug = "adr027-by-slug-ru-{$marker}";

        $article = $this->createTrilingualArticle($roSlug, $enSlug, $ruSlug);

        $this->client->request('GET', "/api/articles/by-slug/{$ruSlug}?locale=ru", [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(
            Response::HTTP_OK,
            'GET by RU slug with locale=ru must return 200 (Gedmo translations join). Body: '
            . substr((string) $this->client->getResponse()->getContent(), 0, 500)
        );

        $data = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            $article->getId(),
            $data['id'] ?? null,
            'Response must point at the fixture article id'
        );

        $this->assertSame(
            "Trilingual fixture RU {$marker}",
            $data['title'] ?? null,
            'Title MUST come from ext_translations for the RU locale (Gedmo Translatable)'
        );
    }

    /**
     * GET /api/articles/by-slug/{nonexistent}?locale=ro returns 404.
     */
    public function testGetArticleBySlugReturns404ForNonexistentSlug(): void
    {
        $bogus = 'this-slug-definitely-does-not-exist-' . bin2hex(random_bytes(6));

        $this->client->request('GET', "/api/articles/by-slug/{$bogus}?locale=ro", [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $data = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('hydra:Error', $data['@type'] ?? null);
        $this->assertStringContainsString($bogus, $data['hydra:description'] ?? '');
        $this->assertSame(404, $data['status'] ?? null);
    }

    /**
     * Cross-locale guard: looking up the EN slug with locale=ro must NOT
     * resolve. This protects against a regression where the translations
     * join is bypassed and the controller falls back to base-table slug
     * comparison across locales.
     */
    public function testGetArticleByEnglishSlugWithRomanianLocaleReturns404(): void
    {
        $marker = $this->uniqueMarker();
        $roSlug = "adr027-by-slug-ro-{$marker}";
        $enSlug = "adr027-by-slug-en-{$marker}";
        $ruSlug = "adr027-by-slug-ru-{$marker}";

        $this->createTrilingualArticle($roSlug, $enSlug, $ruSlug);

        $this->client->request('GET', "/api/articles/by-slug/{$enSlug}?locale=ro", [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(
            Response::HTTP_NOT_FOUND,
            'EN slug with locale=ro MUST NOT resolve (locale isolation). Body: '
            . substr((string) $this->client->getResponse()->getContent(), 0, 300)
        );
    }

    // ======================
    // Helpers
    // ======================

    /**
     * Create a published Article with publishedLocales=[ro,en,ru]. The RO
     * title and slug live on the base table; EN and RU title/slug rows are
     * inserted directly into `ext_translations` to mirror what
     * Gedmo Translatable produces in real life.
     */
    private function createTrilingualArticle(string $roSlug, string $enSlug, string $ruSlug): Article
    {
        $marker = $this->extractMarkerFromSlug($roSlug);

        $article = new Article();
        $article->setTitle("Trilingual fixture RO {$marker}");
        $article->setSlug($roSlug);
        $article->setLead('Regression fixture for ADR-027 by-slug functional test');
        $article->setContent('<p>Trilingual body</p>');
        $article->setStatus(ArticleStatus::PUBLISHED);
        $article->setPublishedLocales(['ro', 'en', 'ru']);
        $article->setPublishedAt(new \DateTimeImmutable());

        $this->em->persist($article);
        $this->em->flush();

        $articleId = (string) $article->getId();

        // Insert EN + RU translations directly via DBAL — this is exactly the
        // shape Gedmo Translatable persists when an entity is saved with
        // setTranslatableLocale(...) calls in app code.
        $conn = $this->em->getConnection();
        $rows = [
            ['en', 'slug', $articleId, $enSlug],
            ['en', 'title', $articleId, "Trilingual fixture EN {$marker}"],
            ['ru', 'slug', $articleId, $ruSlug],
            ['ru', 'title', $articleId, "Trilingual fixture RU {$marker}"],
        ];

        foreach ($rows as [$locale, $field, $foreignKey, $content]) {
            $conn->executeStatement(
                'INSERT INTO ext_translations (locale, object_class, field, foreign_key, content) '
                . 'VALUES (?, ?, ?, ?, ?)',
                [$locale, 'App\\Entity\\Article', $field, $foreignKey, $content]
            );
        }

        // Clear identity map so that subsequent fetches go through Gedmo's
        // translation listener and pull the values we just inserted.
        $this->em->clear();

        return $article;
    }

    private function uniqueMarker(): string
    {
        return bin2hex(random_bytes(6));
    }

    private function extractMarkerFromSlug(string $slug): string
    {
        $parts = explode('-', $slug);
        return end($parts) ?: 'unknown';
    }
}
