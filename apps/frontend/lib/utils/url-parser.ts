/**
 * URL Parser Utilities
 *
 * Parse Next.js URLs to extract locale, page type, and slugs
 */

import type { Locale } from '../types';
import { isReservedSlug } from '../constants/reserved-slugs';

export type PageType =
  | 'home'
  | 'article'
  | 'category'
  | 'author'
  | 'static'
  | 'archive-root'
  | 'archive-year'
  | 'archive-month'
  | 'unknown';

export interface ParsedUrl {
  locale: Locale;
  pageType: PageType;
  categorySlug?: string;
  articleSlug?: string;
  authorSlug?: string;
  staticPage?: string;
  year?: string;
  month?: string;
}

/**
 * Parse a Next.js pathname to extract components
 *
 * Examples:
 * - "/" → { locale: "ro", pageType: "home" }
 * - "/en" → { locale: "en", pageType: "home" }
 * - "/politica" → { locale: "ro", pageType: "category", categorySlug: "politica" }
 * - "/en/politics" → { locale: "en", pageType: "category", categorySlug: "politics" }
 * - "/politica/article-slug" → { locale: "ro", pageType: "article", categorySlug: "politica", articleSlug: "article-slug" }
 * - "/en/politics/article-slug" → { locale: "en", pageType: "article", categorySlug: "politics", articleSlug: "article-slug" }
 * - "/author/john-doe" → { locale: "ro", pageType: "author", authorSlug: "john-doe" }
 * - "/all" → { locale: "ro", pageType: "static", staticPage: "all" }
 * - "/archive" → { locale: "ro", pageType: "archive-root" }
 * - "/archive/2025" → { locale: "ro", pageType: "archive-year", year: "2025" }
 * - "/archive/2025/01" → { locale: "ro", pageType: "archive-month", year: "2025", month: "01" }
 *
 * @param pathname - Next.js pathname (e.g., "/en/politics/article-slug")
 * @returns Parsed URL components
 */
export function parseUrl(pathname: string): ParsedUrl {
  // Remove trailing slash
  const normalizedPath = pathname.replace(/\/$/, '') || '/';

  // Split into segments
  const segments = normalizedPath.split('/').filter(Boolean);

  // Default to Romanian locale
  let locale: Locale = 'ro';
  let startIndex = 0;

  // Check if first segment is a locale
  if (segments.length > 0 && ['en', 'ru'].includes(segments[0])) {
    locale = segments[0] as Locale;
    startIndex = 1;
  }

  // Home page
  if (segments.length === startIndex) {
    return { locale, pageType: 'home' };
  }

  const firstSlug = segments[startIndex];
  const secondSlug = segments[startIndex + 1];
  const thirdSlug = segments[startIndex + 2];

  // Archive pages
  if (firstSlug === 'archive') {
    if (!secondSlug) {
      return { locale, pageType: 'archive-root' };
    }
    if (!thirdSlug) {
      return { locale, pageType: 'archive-year', year: secondSlug };
    }
    return { locale, pageType: 'archive-month', year: secondSlug, month: thirdSlug };
  }

  // Author page
  if (firstSlug === 'author' && secondSlug) {
    return { locale, pageType: 'author', authorSlug: secondSlug };
  }

  // Static pages (reserved slugs)
  if (isReservedSlug(firstSlug)) {
    return { locale, pageType: 'static', staticPage: firstSlug };
  }

  // Article page (category + article slug)
  if (firstSlug && secondSlug) {
    return {
      locale,
      pageType: 'article',
      categorySlug: firstSlug,
      articleSlug: secondSlug,
    };
  }

  // Category page
  if (firstSlug) {
    return { locale, pageType: 'category', categorySlug: firstSlug };
  }

  return { locale, pageType: 'unknown' };
}

/**
 * Check if a URL can be translated (has translatable content)
 *
 * @param parsed - Parsed URL
 * @returns True if the URL can be translated
 */
export function isTranslatableUrl(parsed: ParsedUrl): boolean {
  // Article and category pages are translatable
  return parsed.pageType === 'article' || parsed.pageType === 'category';
}

/**
 * Build URL from components
 *
 * @param components - URL components
 * @returns Full pathname
 */
export function buildUrl(components: ParsedUrl): string {
  const { locale, pageType } = components;
  const localePrefix = locale === 'ro' ? '' : `/${locale}`;

  switch (pageType) {
    case 'home':
      return localePrefix || '/';

    case 'article':
      if (components.categorySlug && components.articleSlug) {
        return `${localePrefix}/${components.categorySlug}/${components.articleSlug}`;
      }
      break;

    case 'category':
      if (components.categorySlug) {
        return `${localePrefix}/${components.categorySlug}`;
      }
      break;

    case 'author':
      if (components.authorSlug) {
        return `${localePrefix}/author/${components.authorSlug}`;
      }
      break;

    case 'static':
      if (components.staticPage) {
        return `${localePrefix}/${components.staticPage}`;
      }
      break;

    case 'archive-root':
      return `${localePrefix}/archive`;

    case 'archive-year':
      if (components.year) {
        return `${localePrefix}/archive/${components.year}`;
      }
      break;

    case 'archive-month':
      if (components.year && components.month) {
        return `${localePrefix}/archive/${components.year}/${components.month}`;
      }
      break;
  }

  return localePrefix || '/';
}
