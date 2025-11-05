/**
 * Sitemap Generation Utilities
 *
 * Helper functions for generating sitemaps with proper hreflang tags and metadata
 */

import { SITEMAP_CONFIG, type Locale } from './sitemap-config';

/**
 * Build URL for a specific locale
 */
export function buildLocalizedUrl(locale: Locale, path: string): string {
  const { baseUrl, defaultLocale } = SITEMAP_CONFIG;
  const cleanPath = path.startsWith('/') ? path.slice(1) : path;

  if (locale === defaultLocale) {
    // Default locale has no prefix
    return `${baseUrl}/${cleanPath}`;
  }

  return `${baseUrl}/${locale}/${cleanPath}`;
}

/**
 * Build article URL from slugs
 */
export function buildArticleUrl(
  locale: Locale,
  categorySlug: string,
  articleSlug: string
): string {
  return buildLocalizedUrl(locale, `${categorySlug}/${articleSlug}`);
}

/**
 * Build category URL from slug
 */
export function buildCategoryUrl(locale: Locale, categorySlug: string): string {
  return buildLocalizedUrl(locale, categorySlug);
}

/**
 * Build author URL from slug
 */
export function buildAuthorUrl(locale: Locale, authorSlug: string): string {
  return buildLocalizedUrl(locale, `author/${authorSlug}`);
}

/**
 * Build archive URL
 */
export function buildArchiveUrl(locale: Locale, year?: number, month?: number): string {
  let path = 'archive';
  if (year) path += `/${year}`;
  if (month) path += `/${month}`;
  return buildLocalizedUrl(locale, path);
}

/**
 * Generate language alternates for hreflang tags
 */
export interface LanguageAlternates {
  [locale: string]: string;
}

export function generateLanguageAlternates(
  paths: Record<Locale, string>
): { languages: LanguageAlternates } {
  const languages: LanguageAlternates = {};

  for (const locale of SITEMAP_CONFIG.locales) {
    const path = paths[locale];
    if (path) {
      languages[locale] = buildLocalizedUrl(locale, path);
    }
  }

  // Add x-default for default locale
  if (paths[SITEMAP_CONFIG.defaultLocale]) {
    languages['x-default'] = buildLocalizedUrl(
      SITEMAP_CONFIG.defaultLocale,
      paths[SITEMAP_CONFIG.defaultLocale]
    );
  }

  return { languages };
}

/**
 * Parse date string to Date object (handles ISO strings from API)
 */
export function parseDate(dateString: string | Date | null | undefined): Date {
  if (!dateString) return new Date();
  if (dateString instanceof Date) return dateString;

  try {
    return new Date(dateString);
  } catch {
    return new Date();
  }
}

/**
 * Check if article is recent enough for news sitemap
 */
export function isRecentArticle(publishedAt: string | Date): boolean {
  const date = parseDate(publishedAt);
  const now = new Date();
  const hoursDiff = (now.getTime() - date.getTime()) / (1000 * 60 * 60);
  return hoursDiff <= SITEMAP_CONFIG.news.maxAgeHours;
}

/**
 * Extract image URL from CDN path
 */
export function getCdnImageUrl(imagePath: string): string {
  const cdnUrl = process.env.NEXT_PUBLIC_CDN_URL || 'http://127.0.0.1:8082';
  // imagePath already includes 'images/' prefix from API
  return `${cdnUrl}/uploads/${imagePath}`;
}

/**
 * Chunk array for pagination
 */
export function chunkArray<T>(array: T[], size: number): T[][] {
  const chunks: T[][] = [];
  for (let i = 0; i < array.length; i += size) {
    chunks.push(array.slice(i, i + size));
  }
  return chunks;
}
