/**
 * URL Builder Utilities
 * Helper functions to build correct article and category URLs.
 *
 * Locale-prefix handling is delegated to `lib/seo/locale-url.ts` (the single
 * source of truth for "locale → URL path" mapping). See ADR-028.
 */

import type { Article, Category } from '@/lib/types/article';
import type { Locale } from '@/lib/types';
import { applyLocalePrefix } from '@/lib/seo/locale-url';

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
 * For Romanian (default locale with prefixDefault=false), omit the locale prefix.
 */
export function buildArticleUrl(
  article: Article,
  locale: Locale
): string {
  const categorySlug = getCategorySlug(article.category);
  return applyLocalePrefix(locale, `${categorySlug}/${article.slug}`);
}

/**
 * Build category URL: /{locale}/{category_slug}
 * For Romanian (default locale with prefixDefault=false), omit the locale prefix.
 */
export function buildCategoryUrl(
  category: Category | string,
  locale: Locale
): string {
  const categorySlug = getCategorySlug(category);
  return applyLocalePrefix(locale, categorySlug);
}

/**
 * Build author URL: /{locale}/author/{author_slug}
 * Author slugs are NOT translatable
 */
export function buildAuthorUrl(
  authorSlug: string,
  locale: Locale
): string {
  return applyLocalePrefix(locale, `author/${authorSlug}`);
}

/**
 * Build locale-prefixed URL
 * For Romanian (default locale with prefixDefault=false), omit the locale prefix.
 */
export function buildLocalizedUrl(
  path: string,
  locale: Locale
): string {
  return applyLocalePrefix(locale, path);
}
