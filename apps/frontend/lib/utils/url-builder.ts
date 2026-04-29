/**
 * URL Builder Utilities
 * Helper functions to build correct article and category URLs
 */

import type { Article, Category } from '@/lib/types/article';
import type { Locale } from '@/lib/types';

/**
 * Get category slug from Category object or string (locale-agnostic, RO base slug).
 *
 * NOTE: For URL building, prefer `getCategorySlugForLocale` so non-RO locales
 * resolve to the translated slug (e.g. /en/politics, /ru/politika) instead of
 * leaking the RO slug (/en/politica) or falling back to /uncategorized/.
 */
export function getCategorySlug(category: Category | string | undefined | null): string {
  if (!category) {
    return 'uncategorized';
  }

  if (typeof category === 'object' && category?.slug) {
    return category.slug;
  }

  if (typeof category === 'string') {
    return category;
  }

  return 'uncategorized';
}

/**
 * Resolve the category slug for a given locale, preferring `translatedSlugs[locale]`,
 * falling back to the base RO slug, and only as a last resort 'uncategorized'.
 *
 * This is the single point of truth for category slugs in URL building (T60.6 Cluster B):
 * before this helper, consumers used `category?.slug || 'uncategorized'`, which leaked
 * the RO slug into EN/RU URLs and triggered 'uncategorized' for any non-RO locale that
 * lacked a fallback chain.
 */
export function getCategorySlugForLocale(
  category: Category | string | undefined | null,
  locale: Locale,
): string {
  if (!category) {
    return 'uncategorized';
  }

  if (typeof category === 'string') {
    return category;
  }

  const translated = category.translatedSlugs?.[locale];
  if (translated) {
    return translated;
  }

  if (category.slug) {
    return category.slug;
  }

  return 'uncategorized';
}

/**
 * Build article URL: /{locale}/{category_slug}/{article_slug}
 * For Romanian (default locale), omit the locale prefix.
 *
 * Uses translated category + article slugs when available.
 */
export function buildArticleUrl(
  article: Article,
  locale: Locale
): string {
  const categorySlug = getCategorySlugForLocale(article.category, locale);
  const articleSlug = article.translatedSlugs?.[locale] ?? article.slug;
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;

  return `/${localePrefix}${categorySlug}/${articleSlug}`;
}

/**
 * Build category URL: /{locale}/{category_slug}
 * For Romanian (default locale), omit the locale prefix.
 *
 * Uses translated slug when available.
 */
export function buildCategoryUrl(
  category: Category | string,
  locale: Locale
): string {
  const categorySlug = getCategorySlugForLocale(category, locale);
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;

  return `/${localePrefix}${categorySlug}`;
}

/**
 * Build author URL: /{locale}/author/{author_slug}
 * Author slugs are NOT translatable
 */
export function buildAuthorUrl(
  authorSlug: string,
  locale: Locale
): string {
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;

  return `/${localePrefix}author/${authorSlug}`;
}

/**
 * Build locale-prefixed URL
 * For Romanian (default locale), omit the locale prefix
 */
export function buildLocalizedUrl(
  path: string,
  locale: Locale
): string {
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;
  // Remove leading slash if present
  const cleanPath = path.startsWith('/') ? path.substring(1) : path;

  const result = `/${localePrefix}${cleanPath}`;
  // Remove trailing slash (except for root '/')
  return result.length > 1 && result.endsWith('/') ? result.slice(0, -1) : result;
}
