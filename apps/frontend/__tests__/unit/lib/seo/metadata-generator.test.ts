/**
 * Unit tests for lib/seo/metadata-generator.ts — alternate-language URL
 * generation (T59.1 / ADR-028 follow-up C2).
 *
 * `buildAlternateUrls` is the source of truth for the per-article
 * `<link rel="alternate" hreflang>` tags emitted by Next.js Metadata API.
 * Drift between this generator and `app/sitemap.ts` is a real risk:
 *  - sitemap iterates SitemapArticle.translations (per-locale full slugs)
 *  - metadata-generator pulls from Article.translatedSlugs +
 *    Article.category.translatedSlugs and falls back to the current
 *    article slug + category slug
 *
 * Tests below pin the contract so future refactors don't silently
 * desynchronize the two paths.
 *
 * Note: `process.env.NEXT_PUBLIC_SITE_URL` is set in jest.setup.js to
 * `http://localhost:3005`. We assert against that base.
 */

import {
  buildAlternateUrls,
  generateArticleMetadata,
} from '@/lib/seo/metadata-generator';
import type { Article, Category } from '@/lib/types/article';

const BASE = 'http://localhost:3005';

function makeCategory(overrides: Partial<Category> = {}): Category {
  return {
    '@id': '/api/categories/1',
    '@type': 'Category',
    id: 1,
    title: 'Politica',
    slug: 'politica',
    translatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
    ...overrides,
  };
}

function makeArticle(overrides: Partial<Article> = {}): Article {
  return {
    '@id': '/api/articles/1',
    '@type': 'Article',
    id: 1,
    title: 'Articol de test',
    slug: 'articol-de-test',
    lead: 'Lead-ul articolului.',
    content: '<p>Conținut.</p>',
    status: 'published',
    publishedAt: '2026-04-01T10:00:00Z',
    updatedAt: '2026-04-02T10:00:00Z',
    createdAt: '2026-04-01T09:00:00Z',
    authors: [],
    articleImages: [],
    viewCount: 0,
    category: makeCategory(),
    publishedLocales: ['ro', 'en', 'ru'],
    translatedSlugs: {
      ro: 'articol-de-test',
      en: 'test-article',
      ru: 'testovaya-statya',
    },
    ...overrides,
  };
}

describe('buildAlternateUrls — direct invocation', () => {
  it('returns hreflang for ro-MD, ro, en, ru, x-default with full translations map', () => {
    const article = makeArticle();
    const translations = {
      ro: { slug: 'articol-de-test', category: { slug: 'politica' } },
      en: { slug: 'test-article', category: { slug: 'politics' } },
      ru: { slug: 'testovaya-statya', category: { slug: 'politika' } },
    };

    const result = buildAlternateUrls(article, translations);

    expect(Object.keys(result).sort()).toEqual(
      ['en', 'ro', 'ro-MD', 'ru', 'x-default'].sort()
    );

    expect(result['ro-MD']).toBe(`${BASE}/ro/politica/articol-de-test`);
    expect(result['ro']).toBe(`${BASE}/ro/politica/articol-de-test`);
    expect(result['en']).toBe(`${BASE}/en/politics/test-article`);
    expect(result['ru']).toBe(`${BASE}/ru/politika/testovaya-statya`);
    expect(result['x-default']).toBe(`${BASE}/ro/politica/articol-de-test`);
  });

  it('falls back to article+category slug when translations argument is omitted', () => {
    const article = makeArticle({
      slug: 'fallback-slug',
      category: makeCategory({ slug: 'fallback-cat', translatedSlugs: undefined }),
    });

    const result = buildAlternateUrls(article);

    expect(result['ro']).toBe(`${BASE}/ro/fallback-cat/fallback-slug`);
    expect(result['en']).toBe(`${BASE}/en/fallback-cat/fallback-slug`);
    expect(result['ru']).toBe(`${BASE}/ru/fallback-cat/fallback-slug`);
  });
});

describe('generateArticleMetadata.alternates.languages — full coverage of C2', () => {
  it('emits hreflang for all 5 keys (ro-MD, ro, en, ru, x-default) when full translatedSlugs present', () => {
    const article = makeArticle();
    const meta = generateArticleMetadata(article, 'ro');
    const langs = meta.alternates?.languages as Record<string, string>;

    expect(langs).toBeDefined();
    expect(Object.keys(langs).sort()).toEqual(
      ['en', 'ro', 'ro-MD', 'ru', 'x-default'].sort()
    );
    expect(langs['en']).toBe(`${BASE}/en/politics/test-article`);
    expect(langs['ru']).toBe(`${BASE}/ru/politika/testovaya-statya`);
  });

  it('partial translatedSlugs (RO+EN only) → RU URL falls back to article.slug, all 5 keys still emitted', () => {
    // Implementation contract: buildAlternateUrls always emits the 5 keys.
    // For C2 we verify the *URL value* for missing locales falls back to
    // `article.slug` (the article's primary slug) and category fallback
    // chain — preventing dead hreflang entries from emitting a 404-bound
    // URL with a fabricated translated slug.
    const article = makeArticle({
      // Primary article.slug is "articol-de-test" (from makeArticle defaults)
      translatedSlugs: { ro: 'articol-ro', en: 'article-en' }, // RU missing
      category: makeCategory({
        // Primary category.slug = "politica"
        translatedSlugs: { ro: 'politica', en: 'politics' }, // RU missing
        slug: 'politica',
      }),
    });

    const meta = generateArticleMetadata(article, 'ro');
    const langs = meta.alternates?.languages as Record<string, string>;

    expect(Object.keys(langs).sort()).toEqual(
      ['en', 'ro', 'ro-MD', 'ru', 'x-default'].sort()
    );
    // Translated entries use translated slugs
    expect(langs['en']).toBe(`${BASE}/en/politics/article-en`);
    // RU entry falls back to article.slug (primary) + category.slug since
    // both translated slugs are missing for RU. See metadata-generator.ts
    // lines 230–242: `articleSlugs?.ru || article.slug` and
    // `categorySlugs?.ru || getCategorySlug(article.category)`.
    expect(langs['ru']).toBe(`${BASE}/ru/politica/articol-de-test`);
    // ro-MD + x-default mirror the RO entry, which DOES have a translated
    // slug → uses translated values.
    expect(langs['ro']).toBe(`${BASE}/ro/politica/articol-ro`);
    expect(langs['ro-MD']).toBe(`${BASE}/ro/politica/articol-ro`);
    expect(langs['x-default']).toBe(`${BASE}/ro/politica/articol-ro`);
  });

  it('empty translatedSlugs and no category translatedSlugs → all locales fall back to article.slug', () => {
    const article = makeArticle({
      translatedSlugs: undefined,
      category: makeCategory({ translatedSlugs: undefined, slug: 'politica' }),
    });

    const meta = generateArticleMetadata(article, 'ro');
    const langs = meta.alternates?.languages as Record<string, string>;

    // Without translated slugs the generator skips the translations map
    // (line 228–243) → buildAlternateUrls fallback path emits the same
    // article.slug for every locale.
    expect(langs['ro']).toBe(`${BASE}/ro/politica/articol-de-test`);
    expect(langs['en']).toBe(`${BASE}/en/politica/articol-de-test`);
    expect(langs['ru']).toBe(`${BASE}/ru/politica/articol-de-test`);
    expect(langs['x-default']).toBe(`${BASE}/ro/politica/articol-de-test`);
  });

  it('URL structure (host + locale prefix + categorySlug + articleSlug) matches sitemap convention', () => {
    // Cross-cutting consistency check: sitemap.ts builds URLs as
    // `${SITE_URL}/${locale}/${categorySlug}/${articleSlug}` — the
    // metadata generator must produce the exact same shape so search
    // engines see consistent <link rel=alternate> ↔ sitemap entries.
    const article = makeArticle();
    const meta = generateArticleMetadata(article, 'en');
    const langs = meta.alternates?.languages as Record<string, string>;

    for (const [tag, url] of Object.entries(langs)) {
      // Every URL must start with the configured base.
      expect(url.startsWith(BASE + '/')).toBe(true);

      const path = url.slice(BASE.length); // e.g. "/en/politics/test-article"
      // Path begins with one of the supported locales.
      const locale = tag === 'ro-MD' || tag === 'x-default' ? 'ro' : tag;
      expect(path.startsWith(`/${locale}/`)).toBe(true);

      // Exactly 3 segments after the leading slash: locale / category / article
      const segments = path.split('/').filter(Boolean);
      expect(segments).toHaveLength(3);
    }
  });
});
