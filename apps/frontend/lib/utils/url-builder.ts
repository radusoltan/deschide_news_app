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
 * Minimal structural shape required by `getCategorySlugForLocale`. Both
 * `Category` (article API) and the trending/special-banner category projections
 * satisfy it without explicit casts.
 */
export type CategorySlugSource =
  | string
  | {
      slug?: string;
      translatedSlugs?: { ro?: string; en?: string; ru?: string };
    }
  | null
  | undefined;

/**
 * Resolve the category slug for a given locale, preferring `translatedSlugs[locale]`,
 * falling back to the base RO slug, and only as a last resort 'uncategorized'.
 *
 * Single point of truth for category slugs in URL building (T60.6 Cluster B):
 * before this helper, consumers used `category?.slug || 'uncategorized'`, which leaked
 * the RO slug into EN/RU URLs and triggered 'uncategorized' for any non-RO locale that
 * lacked a fallback chain.
 */
export function getCategorySlugForLocale(
  category: CategorySlugSource,
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
 * For Romanian (default locale with prefixDefault=false), omit the locale prefix.
 *
 * Uses translated category + article slugs when available.
 */
export function buildArticleUrl(
  article: Article,
  locale: Locale
): string {
  const categorySlug = getCategorySlugForLocale(article.category, locale);
  const articleSlug = article.translatedSlugs?.[locale] ?? article.slug;
  return applyLocalePrefix(locale, `${categorySlug}/${articleSlug}`);
}

/**
 * Build category URL: /{locale}/{category_slug}
 * For Romanian (default locale with prefixDefault=false), omit the locale prefix.
 *
 * Uses translated slug when available.
 */
export function buildCategoryUrl(
  category: Category | string,
  locale: Locale
): string {
  const categorySlug = getCategorySlugForLocale(category, locale);
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
