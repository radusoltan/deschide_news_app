/**
 * Unit tests for app/sitemap.ts — article hreflang emission.
 *
 * Covers T60.1 acceptance criteria:
 *  1. Multi-locale translated article → 3 distinct <loc> URLs + 3 distinct
 *     hreflang alternates, each using the target-locale article slug AND
 *     the target-locale category slug.
 *  2. Partial publishedLocales → no hreflang (or <loc>) emitted for the
 *     unpublished locale.
 *  3. Missing translatedSlugs → graceful fallback, no throw.
 *  4. Per-locale translated category slugs propagate to <loc> and alternates
 *     (verifies category fetcher gap is NOT in T60.1's blast radius).
 *
 * Placed under __tests__/unit/ instead of __tests__/integration/ because
 * jest.config.mjs ignores the integration directory (reserved for
 * Playwright). See T60.1 report for the deviation note.
 */

jest.mock('@/lib/api/sitemap-data');

import sitemap from '@/app/sitemap';
import {
  fetchAllArticlesForSitemap,
  fetchAllCategoriesForSitemap,
  fetchAllAuthorsForSitemap,
  type SitemapArticle,
  type ArticleTranslation,
} from '@/lib/api/sitemap-data';

const mockFetchAllArticles = fetchAllArticlesForSitemap as jest.MockedFunction<
  typeof fetchAllArticlesForSitemap
>;
const mockFetchAllCategories = fetchAllCategoriesForSitemap as jest.MockedFunction<
  typeof fetchAllCategoriesForSitemap
>;
const mockFetchAllAuthors = fetchAllAuthorsForSitemap as jest.MockedFunction<
  typeof fetchAllAuthorsForSitemap
>;

type Locale = 'ro' | 'en' | 'ru';

function makeTranslation(
  locale: Locale,
  slug: string,
  categorySlug: string,
  title = `Title ${locale}`
): ArticleTranslation {
  return { locale, slug, categorySlug, title };
}

function makeArticle(overrides: Partial<SitemapArticle> = {}): SitemapArticle {
  return {
    id: 1,
    slug: 'base-ro-slug',
    publishedAt: '2026-04-24T00:00:00Z',
    updatedAt: '2026-04-24T00:00:00Z',
    isFeatured: false,
    publishedLocales: ['ro', 'en', 'ru'],
    category: { slug: 'politica' },
    translations: {
      ro: makeTranslation('ro', 'articol-ro', 'politica'),
      en: makeTranslation('en', 'article-en', 'politics'),
      ru: makeTranslation('ru', 'statya-ru', 'politika'),
    },
    articleImages: [],
    ...overrides,
  };
}

const STATIC_PATHS = new Set([
  'all',
  'trending',
  'archive',
  'about',
  'contact',
  'author',
]);

const LOCALE_SEGMENTS = new Set(['ro', 'en', 'ru']);

function articleEntries(entries: Awaited<ReturnType<typeof sitemap>>) {
  return entries.filter((e) => {
    const segments = new URL(e.url).pathname.split('/').filter(Boolean);
    const withoutLocale =
      segments.length > 0 && LOCALE_SEGMENTS.has(segments[0]!)
        ? segments.slice(1)
        : segments;
    if (withoutLocale.length !== 2) return false;
    if (STATIC_PATHS.has(withoutLocale[0]!)) return false;
    return true;
  });
}

describe('sitemap — article hreflang emission', () => {
  beforeEach(() => {
    mockFetchAllArticles.mockReset();
    mockFetchAllCategories.mockReset().mockResolvedValue([]);
    mockFetchAllAuthors.mockReset().mockResolvedValue([]);
  });

  it('emits distinct translated slugs per locale when all 3 locales are translated', async () => {
    mockFetchAllArticles.mockResolvedValue([makeArticle()]);

    const entries = await sitemap();
    const articles = articleEntries(entries);

    expect(articles).toHaveLength(3);

    const urls = articles.map((e) => e.url);
    expect(urls).toEqual(expect.arrayContaining([
      expect.stringContaining('/politica/articol-ro'),
      expect.stringContaining('/en/politics/article-en'),
      expect.stringContaining('/ru/politika/statya-ru'),
    ]));
    expect(new Set(urls).size).toBe(3);

    for (const entry of articles) {
      const langs = entry.alternates?.languages ?? {};
      expect(langs.en).toMatch(/\/en\/politics\/article-en$/);
      expect(langs.ru).toMatch(/\/ru\/politika\/statya-ru$/);
      expect(langs.ro).toMatch(/\/politica\/articol-ro$/);
      expect(new Set([langs.en, langs.ru, langs.ro]).size).toBe(3);
    }
  });

  it('skips hreflang for unpublished locale', async () => {
    mockFetchAllArticles.mockResolvedValue([
      makeArticle({ publishedLocales: ['ro', 'en'] }),
    ]);

    const entries = await sitemap();
    const articles = articleEntries(entries);

    expect(articles).toHaveLength(2);
    expect(articles.map((e) => e.url)).toEqual(
      expect.not.arrayContaining([expect.stringContaining('/ru/')])
    );

    for (const entry of articles) {
      const langs = entry.alternates?.languages ?? {};
      expect(langs.ru).toBeUndefined();
      expect(langs.en).toBeDefined();
      expect(langs.ro).toBeDefined();
    }
  });

  it('falls back gracefully when translatedSlugs are missing for non-RO locales', async () => {
    mockFetchAllArticles.mockResolvedValue([
      makeArticle({
        publishedLocales: ['ro'],
        translations: {
          ro: makeTranslation('ro', 'articol-ro', 'politica'),
        },
      }),
    ]);

    const entries = await sitemap();
    const articles = articleEntries(entries);

    expect(articles).toHaveLength(1);
    expect(articles[0]!.url).toMatch(/\/politica\/articol-ro$/);

    const langs = articles[0]!.alternates?.languages ?? {};
    expect(langs.ro).toBeDefined();
    // The bug's signature (/en/ or /ru/ pointing at a RO slug) means langs.en
    // and langs.ru would be DEFINED strings containing 'articol-ro'. They must
    // be absent entirely.
    expect(langs.en).toBeUndefined();
    expect(langs.ru).toBeUndefined();
  });

  it('uses translated category slug when available (translated-category assertion)', async () => {
    mockFetchAllArticles.mockResolvedValue([makeArticle()]);

    const entries = await sitemap();
    const articles = articleEntries(entries);

    const enEntry = articles.find((e) => e.url.includes('/en/'));
    const ruEntry = articles.find((e) => e.url.includes('/ru/'));
    const roEntry = articles.find(
      (e) => !e.url.includes('/en/') && !e.url.includes('/ru/')
    );

    expect(enEntry?.url).toContain('/politics/');
    expect(enEntry?.url).not.toContain('/politica/');
    expect(ruEntry?.url).toContain('/politika/');
    expect(ruEntry?.url).not.toContain('/politica/');
    expect(roEntry?.url).toContain('/politica/');
  });
});
