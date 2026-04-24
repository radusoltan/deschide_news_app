/**
 * Sitemap Configuration
 *
 * Centralized configuration for sitemap generation across all locales
 */

export const SITEMAP_CONFIG = {
  // Base URL - will be overridden by environment variable
  baseUrl: process.env.NEXT_PUBLIC_SITE_URL || 'https://deschide.md',

  // Supported locales
  locales: ['ro', 'en', 'ru'] as const,

  // Default content locale. Public URLs still include the locale prefix (/ro).
  defaultLocale: 'ro' as const,

  // Change frequencies
  changeFrequency: {
    homepage: 'hourly',
    article: 'daily',
    category: 'daily',
    author: 'weekly',
    archive: 'weekly',
    static: 'monthly',
  } as const,

  // Priorities (0.0 - 1.0)
  priority: {
    homepage: 1.0,
    featuredArticle: 0.9,
    article: 0.8,
    category: 0.7,
    author: 0.6,
    archive: 0.5,
    static: 0.5,
  } as const,

  // News sitemap configuration
  news: {
    // Include articles published within last 48 hours
    maxAgeHours: 48,
    publicationName: 'Deschide News',
    languageMap: {
      ro: 'ro',
      en: 'en',
      ru: 'ru',
    },
  } as const,

  // Image sitemap configuration
  images: {
    // Include article featured images and inline images
    includeFeatured: true,
    includeInline: true,
    maxImagesPerArticle: 10,
  } as const,

  // Pagination for large sitemaps
  pagination: {
    // Google's limit is 50,000 URLs per sitemap
    maxUrlsPerSitemap: 50000,
  } as const,
} as const;

export type Locale = typeof SITEMAP_CONFIG.locales[number];
export type ChangeFrequency = 'always' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'yearly' | 'never';
