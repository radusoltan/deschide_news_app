/**
 * URL Builder Utilities
 * Helper functions to build correct article and category URLs
 */

import type { Article, Category } from '@/lib/types/article';
import type { Locale } from '@/lib/types';

/**
 * Get category slug from Category object or string
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
 * Build article URL: /{locale}/{category_slug}/{article_slug}
 * For Romanian (default locale), omit the locale prefix
 */
export function buildArticleUrl(
  article: Article,
  locale: Locale
): string {
  const categorySlug = getCategorySlug(article.category);
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;

  return `/${localePrefix}${categorySlug}/${article.slug}`;
}

/**
 * Build category URL: /{locale}/{category_slug}
 * For Romanian (default locale), omit the locale prefix
 */
export function buildCategoryUrl(
  category: Category | string,
  locale: Locale
): string {
  const categorySlug = getCategorySlug(category);
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
