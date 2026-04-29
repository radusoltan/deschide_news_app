/**
 * URL Builder Utility Tests
 */

import {
  buildArticleLocaleAlternates,
  buildArticleUrl,
  buildCategoryLocaleAlternates,
  buildCategoryUrl,
  buildImageUrl,
  getCategorySlugForLocale,
} from '@/lib/utils/url-builder';
import { mockArticle, mockArticles } from '@/__tests__/__mocks__/articles';
import { mockCategory } from '@/__tests__/__mocks__/categories';
import type { Article, Category } from '@/lib/types/article';

describe('URL Builder Utilities', () => {
  describe('getCategorySlugForLocale', () => {
    const trilingual: Category = {
      ...mockCategory,
      slug: 'politica',
      translatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
    };

    it('returns the translated slug when present for the locale', () => {
      expect(getCategorySlugForLocale(trilingual, 'en')).toBe('politics');
      expect(getCategorySlugForLocale(trilingual, 'ru')).toBe('politika');
      expect(getCategorySlugForLocale(trilingual, 'ro')).toBe('politica');
    });

    it('falls back to base slug when locale translation is missing', () => {
      const partial: Category = {
        ...mockCategory,
        slug: 'politica',
        translatedSlugs: { ro: 'politica' },
      };
      expect(getCategorySlugForLocale(partial, 'en')).toBe('politica');
      expect(getCategorySlugForLocale(partial, 'ru')).toBe('politica');
    });

    it('falls back to base slug when translatedSlugs is absent entirely', () => {
      const noTranslations: Category = { ...mockCategory, slug: 'politica' };
      expect(getCategorySlugForLocale(noTranslations, 'en')).toBe('politica');
      expect(getCategorySlugForLocale(noTranslations, 'ru')).toBe('politica');
    });

    it('returns the bare string when category is a string', () => {
      expect(getCategorySlugForLocale('politica', 'en')).toBe('politica');
    });

    it('returns "uncategorized" only when category is null/undefined or has no slug at all', () => {
      expect(getCategorySlugForLocale(null, 'en')).toBe('uncategorized');
      expect(getCategorySlugForLocale(undefined, 'en')).toBe('uncategorized');
      const slugless = { ...mockCategory, slug: undefined as unknown as string, translatedSlugs: undefined };
      expect(getCategorySlugForLocale(slugless, 'en')).toBe('uncategorized');
    });
  });


  describe('buildArticleUrl', () => {
    it('should build article URL for default locale (ro)', () => {
      const url = buildArticleUrl(mockArticle, 'ro');
      expect(url).toBe('/politics/test-article-title');
    });

    it('should build article URL for English locale', () => {
      const url = buildArticleUrl(mockArticle, 'en');
      expect(url).toBe('/en/politics/test-article-title');
    });

    it('should build article URL for Russian locale', () => {
      const url = buildArticleUrl(mockArticle, 'ru');
      expect(url).toBe('/ru/politics/test-article-title');
    });

    it('should handle article with string category', () => {
      const articleWithStringCategory = {
        ...mockArticle,
        category: 'politics',
      };
      const url = buildArticleUrl(articleWithStringCategory, 'ro');
      expect(url).toBe('/politics/test-article-title');
    });

    it('should handle missing category gracefully', () => {
      const articleWithoutCategory = {
        ...mockArticle,
        category: undefined,
      };
      const url = buildArticleUrl(articleWithoutCategory as any, 'ro');
      expect(url).toBe('/uncategorized/test-article-title');
    });
  });

  describe('buildCategoryUrl', () => {
    it('should build category URL for default locale (ro)', () => {
      const url = buildCategoryUrl(mockCategory, 'ro');
      expect(url).toBe('/politics');
    });

    it('should build category URL for English locale', () => {
      const url = buildCategoryUrl(mockCategory, 'en');
      expect(url).toBe('/en/politics');
    });

    it('should build category URL for Russian locale', () => {
      const url = buildCategoryUrl(mockCategory, 'ru');
      expect(url).toBe('/ru/politics');
    });

    it('should handle string category', () => {
      const url = buildCategoryUrl('politics', 'ro');
      expect(url).toBe('/politics');
    });

    it('should handle missing category', () => {
      const url = buildCategoryUrl(undefined as any, 'ro');
      expect(url).toBe('/uncategorized');
    });
  });

  // Note: buildImageUrl is in lib/api/important-articles.ts, not in url-builder.ts

  describe('buildArticleLocaleAlternates', () => {
    const trilingualCategory: Category = {
      ...mockCategory,
      slug: 'politica',
      translatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
    };

    it('builds alternates from translatedSlugs on both article and category', () => {
      const article: Article = {
        ...mockArticle,
        slug: 'moldova-eu',
        category: trilingualCategory,
        translatedSlugs: { ro: 'moldova-ue', en: 'moldova-eu', ru: 'moldova-es' },
      };

      const alternates = buildArticleLocaleAlternates(article);
      expect(alternates).toEqual({
        ro: '/politica/moldova-ue',
        en: '/en/politics/moldova-eu',
        ru: '/ru/politika/moldova-es',
      });
    });

    it('falls back to base RO slugs when a locale translation is missing', () => {
      const article: Article = {
        ...mockArticle,
        slug: 'moldova-eu',
        category: { ...trilingualCategory, translatedSlugs: { ro: 'politica', en: 'politics' } },
        translatedSlugs: { ro: 'moldova-ue', en: 'moldova-eu' },
      };

      const alternates = buildArticleLocaleAlternates(article);
      expect(alternates.ro).toBe('/politica/moldova-ue');
      expect(alternates.en).toBe('/en/politics/moldova-eu');
      // RU has no translation → falls back to RO base slugs
      expect(alternates.ru).toBe('/ru/politica/moldova-eu');
    });

    it('omits a locale only when category resolves to "uncategorized"', () => {
      const article: Article = {
        ...mockArticle,
        slug: 'orphan',
        category: undefined as unknown as Category,
        translatedSlugs: undefined,
      };

      const alternates = buildArticleLocaleAlternates(article);
      expect(alternates).toEqual({});
    });
  });

  describe('buildCategoryLocaleAlternates', () => {
    it('builds /{locale}/{translated} for each locale, omitting locale prefix for ro', () => {
      const trilingual: Category = {
        ...mockCategory,
        slug: 'politica',
        translatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
      };

      const alternates = buildCategoryLocaleAlternates(trilingual);
      expect(alternates).toEqual({
        ro: '/politica',
        en: '/en/politics',
        ru: '/ru/politika',
      });
    });

    it('falls back to base slug for missing locales', () => {
      const partial: Category = {
        ...mockCategory,
        slug: 'sport',
        translatedSlugs: { ro: 'sport' },
      };

      const alternates = buildCategoryLocaleAlternates(partial);
      expect(alternates).toEqual({
        ro: '/sport',
        en: '/en/sport',
        ru: '/ru/sport',
      });
    });
  });
});
