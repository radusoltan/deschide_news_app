/**
 * Unit tests for lib/seo/locale-url.ts navigation helpers
 * (applyLocalePrefix + buildLocaleUrlFor{Article,Category,Generic}).
 *
 * Covers the acceptance criteria from ADR-028:
 *  - All 3 locales have a translated slug  → build correct URL
 *  - Target locale missing from translatedSlugs → return null
 *  - Target locale is defaultLocale → URL without /ro prefix
 *  - Category fallback when translatedSlugs is empty
 *  - Generic path swap strips existing locale segment
 */

import {
  applyLocalePrefix,
  buildLocaleUrlForArticle,
  buildLocaleUrlForCategory,
  buildLocaleUrlGeneric,
} from '@/lib/seo/locale-url';

describe('applyLocalePrefix', () => {
  it('returns path without /ro prefix for default locale', () => {
    expect(applyLocalePrefix('ro', 'politica/x')).toBe('/politica/x');
  });

  it('prefixes non-default locale', () => {
    expect(applyLocalePrefix('en', 'politica/x')).toBe('/en/politica/x');
    expect(applyLocalePrefix('ru', 'politica/x')).toBe('/ru/politica/x');
  });

  it('handles leading slash on input', () => {
    expect(applyLocalePrefix('en', '/politica')).toBe('/en/politica');
  });

  it('handles empty path for RO (root)', () => {
    expect(applyLocalePrefix('ro', '')).toBe('/');
    expect(applyLocalePrefix('ro', '/')).toBe('/');
  });

  it('handles empty path for non-default locale', () => {
    expect(applyLocalePrefix('en', '')).toBe('/en');
    expect(applyLocalePrefix('en', '/')).toBe('/en');
  });
});

describe('buildLocaleUrlForArticle', () => {
  const fullyTranslatedArticle = {
    translatedSlugs: {
      ro: 'articol-ro',
      en: 'article-en',
      ru: 'statya-ru',
    },
    category: {
      slug: 'politica',
      translatedSlugs: {
        ro: 'politica',
        en: 'politics',
        ru: 'politika',
      },
    },
  };

  it('builds URL when target locale has translated slug', () => {
    expect(buildLocaleUrlForArticle('en', fullyTranslatedArticle, 'ro')).toBe(
      '/en/politics/article-en'
    );
    expect(buildLocaleUrlForArticle('ru', fullyTranslatedArticle, 'ro')).toBe(
      '/ru/politika/statya-ru'
    );
  });

  it('builds URL without prefix for default locale', () => {
    expect(buildLocaleUrlForArticle('ro', fullyTranslatedArticle, 'en')).toBe(
      '/politica/articol-ro'
    );
  });

  it('returns null when target locale missing from translatedSlugs', () => {
    const partial = {
      translatedSlugs: { ro: 'articol-ro', en: 'article-en' },
      category: fullyTranslatedArticle.category,
    };
    expect(buildLocaleUrlForArticle('ru', partial, 'ro')).toBeNull();
  });

  it('falls back to currentLocale category slug when targetLocale category slug missing', () => {
    const article = {
      translatedSlugs: { ro: 'articol', en: 'article', ru: 'statya' },
      category: {
        slug: 'politica',
        translatedSlugs: { ro: 'politica', en: 'politics' }, // ru missing
      },
    };
    // target RU, category RU missing → fallback to current RO slug
    expect(buildLocaleUrlForArticle('ru', article, 'ro')).toBe('/ru/politica/statya');
  });

  it('uses category.slug when translatedSlugs empty', () => {
    const article = {
      translatedSlugs: { ro: 'articol', en: 'article', ru: 'statya' },
      category: { slug: 'politica' },
    };
    expect(buildLocaleUrlForArticle('en', article, 'ro')).toBe('/en/politica/article');
  });

  it('returns null when no category info at all', () => {
    const article = {
      translatedSlugs: { en: 'article' },
      category: undefined,
    };
    expect(buildLocaleUrlForArticle('en', article, 'ro')).toBeNull();
  });
});

describe('buildLocaleUrlForCategory', () => {
  it('uses translated slug for target locale', () => {
    const category = {
      slug: 'politica',
      translatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
    };
    expect(buildLocaleUrlForCategory('en', category, 'ro')).toBe('/en/politics');
    expect(buildLocaleUrlForCategory('ru', category, 'ro')).toBe('/ru/politika');
    expect(buildLocaleUrlForCategory('ro', category, 'en')).toBe('/politica');
  });

  it('falls back to currentLocale slug when target missing', () => {
    const category = {
      slug: 'politica',
      translatedSlugs: { ro: 'politica', en: 'politics' },
    };
    expect(buildLocaleUrlForCategory('ru', category, 'ro')).toBe('/ru/politica');
  });

  it('falls back to category.slug when translatedSlugs empty', () => {
    const category = { slug: 'politica' };
    expect(buildLocaleUrlForCategory('en', category, 'ro')).toBe('/en/politica');
  });

  it('never returns null (categories are visible across locales)', () => {
    const minimal = { slug: 'economie' };
    expect(buildLocaleUrlForCategory('en', minimal, 'ro')).toBe('/en/economie');
    expect(buildLocaleUrlForCategory('ru', minimal, 'ro')).toBe('/ru/economie');
  });
});

describe('buildLocaleUrlGeneric', () => {
  it('swaps existing locale prefix', () => {
    expect(buildLocaleUrlGeneric('en', '/ru/about', 'ru')).toBe('/en/about');
    expect(buildLocaleUrlGeneric('ro', '/en/about', 'en')).toBe('/about');
  });

  it('adds prefix when path has no locale segment', () => {
    expect(buildLocaleUrlGeneric('en', '/about', 'ro')).toBe('/en/about');
    expect(buildLocaleUrlGeneric('ro', '/about', 'ro')).toBe('/about');
  });

  it('handles root path', () => {
    expect(buildLocaleUrlGeneric('en', '/', 'ro')).toBe('/en');
    expect(buildLocaleUrlGeneric('ro', '/en', 'en')).toBe('/');
    expect(buildLocaleUrlGeneric('ro', '/', 'ro')).toBe('/');
  });

  it('preserves nested paths', () => {
    expect(buildLocaleUrlGeneric('en', '/search?q=test', 'ro')).toBe('/en/search?q=test');
  });
});
