/**
 * Unit tests for `lib/server/resolve-locale-context-data.ts`
 *
 * The resolver runs in `(public)/layout.tsx` to prime LocaleContext for
 * the SSR pass so the shared LanguageSwitcher emits canonical hreflang
 * URLs in raw HTML (T60.15 / ADR-029).
 *
 * Each branch is mocked independently to verify:
 *  - correct route-type detection
 *  - correct LocaleContextData shape returned
 *  - fail-soft on fetch errors / nulls
 */

import { resolveLocaleContextData } from '@/lib/server/resolve-locale-context-data';
import { lookupArticle } from '@/lib/api/slug-lookup';
import { fetchCategories } from '@/lib/api/categories';
import { fetchTags } from '@/lib/api/tags';
import { fetchTopicBySlug } from '@/lib/api/topics';

jest.mock('@/lib/api/slug-lookup');
jest.mock('@/lib/api/categories');
jest.mock('@/lib/api/tags');
jest.mock('@/lib/api/topics');

const mockLookupArticle = lookupArticle as jest.MockedFunction<typeof lookupArticle>;
const mockFetchCategories = fetchCategories as jest.MockedFunction<typeof fetchCategories>;
const mockFetchTags = fetchTags as jest.MockedFunction<typeof fetchTags>;
const mockFetchTopicBySlug = fetchTopicBySlug as jest.MockedFunction<typeof fetchTopicBySlug>;

const GENERIC = {
  publishedLocales: undefined,
  translatedSlugs: undefined,
  categoryTranslatedSlugs: undefined,
  context: 'generic' as const,
};

describe('resolveLocaleContextData — homepage + generic-context routes', () => {
  it('returns GENERIC for /<locale> (homepage)', async () => {
    expect(await resolveLocaleContextData('/en', 'en')).toEqual(GENERIC);
    expect(await resolveLocaleContextData('/ro', 'ro')).toEqual(GENERIC);
    expect(await resolveLocaleContextData('/ru', 'ru')).toEqual(GENERIC);
  });

  it('returns GENERIC for /<locale>/ (trailing slash)', async () => {
    expect(await resolveLocaleContextData('/en/', 'en')).toEqual(GENERIC);
  });

  it('returns GENERIC for /<locale>/search', async () => {
    expect(await resolveLocaleContextData('/en/search', 'en')).toEqual(GENERIC);
  });

  it('returns GENERIC for /<locale>/archive', async () => {
    expect(await resolveLocaleContextData('/en/archive', 'en')).toEqual(GENERIC);
    expect(await resolveLocaleContextData('/en/archive/2025', 'en')).toEqual(GENERIC);
  });

  it('returns GENERIC for /<locale>/all and /trending', async () => {
    expect(await resolveLocaleContextData('/en/all', 'en')).toEqual(GENERIC);
    expect(await resolveLocaleContextData('/en/trending', 'en')).toEqual(GENERIC);
  });
});

describe('resolveLocaleContextData — topic branch', () => {
  it('returns topic context with translated slugs for /<locale>/topics/<slug>', async () => {
    mockFetchTopicBySlug.mockResolvedValueOnce({
      id: 1,
      title: 'Education',
      slug: 'education',
      lvl: 0,
      isActive: true,
      translatedSlugs: { ro: 'educatie', en: 'education', ru: 'obrazovanie' },
    });

    const result = await resolveLocaleContextData('/en/topics/education', 'en');

    expect(mockFetchTopicBySlug).toHaveBeenCalledWith('education', 'en');
    expect(result).toEqual({
      publishedLocales: undefined,
      translatedSlugs: { ro: 'educatie', en: 'education', ru: 'obrazovanie' },
      categoryTranslatedSlugs: undefined,
      context: 'topic',
    });
  });

  it('returns topic context with no slugs when topic not found (fail-soft)', async () => {
    mockFetchTopicBySlug.mockResolvedValueOnce(null);

    const result = await resolveLocaleContextData('/en/topics/missing', 'en');

    expect(result.context).toBe('topic');
    expect(result.translatedSlugs).toBeUndefined();
  });

  it('returns topic context when topic exists but lacks translatedSlugs', async () => {
    mockFetchTopicBySlug.mockResolvedValueOnce({
      id: 2,
      title: 'X',
      slug: 'x',
      lvl: 0,
      isActive: true,
      // no translatedSlugs
    });

    const result = await resolveLocaleContextData('/en/topics/x', 'en');
    expect(result.context).toBe('topic');
    expect(result.translatedSlugs).toBeUndefined();
  });
});

describe('resolveLocaleContextData — author branch (always generic)', () => {
  it('returns GENERIC for /<locale>/author/<slug> without any fetch (slug shared)', async () => {
    const result = await resolveLocaleContextData('/en/author/john-doe', 'en');
    expect(result).toEqual(GENERIC);
    expect(mockLookupArticle).not.toHaveBeenCalled();
    expect(mockFetchCategories).not.toHaveBeenCalled();
  });
});

describe('resolveLocaleContextData — tag branch', () => {
  it('returns tag context with translated slugs for /<locale>/tags/<slug>', async () => {
    // Use partial cast since Tag has many other fields not relevant here.
    mockFetchTags.mockResolvedValueOnce({
      'hydra:member': [{
        id: 1,
        name: 'Politics',
        slug: 'politics',
        translatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
        usageCount: 5,
      }],
      'hydra:totalItems': 1,
    } as Awaited<ReturnType<typeof fetchTags>>);

    const result = await resolveLocaleContextData('/en/tags/politics', 'en');

    expect(mockFetchTags).toHaveBeenCalledWith('en', { slug: 'politics', itemsPerPage: 1 });
    expect(result).toEqual({
      publishedLocales: undefined,
      translatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
      categoryTranslatedSlugs: undefined,
      context: 'tag',
    });
  });

  it('returns tag context with no slugs when tag fetch errors (fail-soft)', async () => {
    mockFetchTags.mockRejectedValueOnce(new Error('boom'));
    const result = await resolveLocaleContextData('/en/tags/anything', 'en');
    expect(result.context).toBe('tag');
    expect(result.translatedSlugs).toBeUndefined();
  });
});

describe('resolveLocaleContextData — static fast-path (D4)', () => {
  it.each(['gdpr', 'license', 'team', 'advertise', 'media-kit', 'emisiuni'])(
    'returns GENERIC for /<locale>/%s without category fetch',
    async (slug) => {
      const result = await resolveLocaleContextData(`/en/${slug}`, 'en');
      expect(result).toEqual(GENERIC);
      expect(mockFetchCategories).not.toHaveBeenCalled();
    }
  );
});

describe('resolveLocaleContextData — reserved slugs (about/contact/privacy/terms)', () => {
  it.each(['about', 'contact', 'privacy', 'terms'])(
    'returns GENERIC for /<locale>/%s without category fetch',
    async (slug) => {
      const result = await resolveLocaleContextData(`/en/${slug}`, 'en');
      expect(result).toEqual(GENERIC);
      expect(mockFetchCategories).not.toHaveBeenCalled();
    }
  );
});

describe('resolveLocaleContextData — article branch', () => {
  it('returns article context for matching /<locale>/<cat>/<art>', async () => {
    mockLookupArticle.mockResolvedValueOnce({
      id: 42,
      title: 'X',
      slug: 'article-en',
      publishedLocales: ['ro', 'en', 'ru'],
      translatedSlugs: { ro: 'articol-ro', en: 'article-en', ru: 'statya-ru' },
      category: {
        id: 7,
        slug: 'politics',
        title: 'Politics',
        translatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
      },
    } as Awaited<ReturnType<typeof lookupArticle>>);

    const result = await resolveLocaleContextData('/en/politics/article-en', 'en');

    expect(mockLookupArticle).toHaveBeenCalledWith('article-en', 'en');
    expect(result).toEqual({
      publishedLocales: ['ro', 'en', 'ru'],
      translatedSlugs: { ro: 'articol-ro', en: 'article-en', ru: 'statya-ru' },
      categoryTranslatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
      context: 'article',
    });
  });

  it('falls through to category lookup when article category mismatches URL', async () => {
    mockLookupArticle.mockResolvedValueOnce({
      id: 42,
      title: 'X',
      slug: 'article-en',
      category: { id: 7, slug: 'politics', title: 'Politics' },
    } as Awaited<ReturnType<typeof lookupArticle>>);
    // After article fails the category match, resolver tries category... but URL has 2 segments,
    // so category branch (which requires segments.length === 1) is skipped → GENERIC.
    const result = await resolveLocaleContextData('/en/society/article-en', 'en');
    expect(result).toEqual(GENERIC);
  });

  it('returns GENERIC when article not found and only 2 segments', async () => {
    mockLookupArticle.mockResolvedValueOnce(null);
    const result = await resolveLocaleContextData('/en/some-cat/missing-article', 'en');
    expect(result).toEqual(GENERIC);
  });
});

describe('resolveLocaleContextData — category branch', () => {
  it('returns category context for /<locale>/<cat>', async () => {
    mockFetchCategories.mockResolvedValueOnce({
      member: [
        {
          id: 7,
          slug: 'politics',
          title: 'Politics',
          translatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
        },
      ],
    } as Awaited<ReturnType<typeof fetchCategories>>);

    const result = await resolveLocaleContextData('/en/politics', 'en');

    expect(mockFetchCategories).toHaveBeenCalledWith('en');
    expect(result).toEqual({
      publishedLocales: undefined,
      translatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
      categoryTranslatedSlugs: undefined,
      context: 'category',
    });
  });

  it('returns GENERIC when category not found in list', async () => {
    mockFetchCategories.mockResolvedValueOnce({
      member: [],
    } as Awaited<ReturnType<typeof fetchCategories>>);
    const result = await resolveLocaleContextData('/en/no-such-cat', 'en');
    expect(result).toEqual(GENERIC);
  });

  it('returns GENERIC when fetchCategories rejects (fail-soft)', async () => {
    mockFetchCategories.mockRejectedValueOnce(new Error('network'));
    const result = await resolveLocaleContextData('/en/politics', 'en');
    expect(result).toEqual(GENERIC);
  });
});

describe('resolveLocaleContextData — locale parsing', () => {
  it('strips ro/en/ru locale segment from pathname', async () => {
    mockFetchTopicBySlug.mockResolvedValue({
      id: 1, title: 'X', slug: 'x', lvl: 0, isActive: true,
      translatedSlugs: { ro: 'x-ro' },
    });

    await resolveLocaleContextData('/ro/topics/x-ro', 'ro');
    await resolveLocaleContextData('/en/topics/x-ro', 'en');
    await resolveLocaleContextData('/ru/topics/x-ro', 'ru');

    expect(mockFetchTopicBySlug).toHaveBeenCalledTimes(3);
    expect(mockFetchTopicBySlug).toHaveBeenNthCalledWith(1, 'x-ro', 'ro');
    expect(mockFetchTopicBySlug).toHaveBeenNthCalledWith(2, 'x-ro', 'en');
    expect(mockFetchTopicBySlug).toHaveBeenNthCalledWith(3, 'x-ro', 'ru');
  });

  it('handles pathname without leading slash', async () => {
    expect(await resolveLocaleContextData('en', 'en')).toEqual(GENERIC);
    expect(await resolveLocaleContextData('en/', 'en')).toEqual(GENERIC);
  });
});
