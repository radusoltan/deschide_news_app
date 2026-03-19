/**
 * Cache Configuration and Tag Definitions
 * Centralized cache tag management for Next.js 16 `use cache` directive
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 2 Caching Alignment
 */

// ============================================================================
// Cache Tag Definitions
// ============================================================================

/**
 * Centralized cache tag definitions
 * Use these constants throughout the application for consistent cache invalidation
 *
 * @example
 * // In data layer
 * cacheTag(CACHE_TAGS.articles, CACHE_TAGS.locale('ro'));
 *
 * // In revalidation
 * revalidateTag(CACHE_TAGS.articleById(123), 'max');
 */
export const CACHE_TAGS = {
  // =========================================================================
  // Global Tags
  // =========================================================================

  /** Invalidate all cached data - use sparingly */
  all: 'all',

  // =========================================================================
  // Content Type Tags
  // =========================================================================

  /** All articles cache */
  articles: 'articles',

  /** All categories cache */
  categories: 'categories',

  /** All authors cache */
  authors: 'authors',

  /** All tags cache */
  tags: 'tags',

  /** All images cache */
  images: 'images',

  /** All videos cache */
  videos: 'videos',

  /** Live text/broadcasts cache */
  livetext: 'livetext',

  // =========================================================================
  // Entity-Specific Tags (Dynamic)
  // =========================================================================

  /** Single article by ID */
  articleById: (id: number) => `article-${id}` as const,

  /** Articles by category */
  articlesByCategory: (categoryId: number) => `articles-cat-${categoryId}` as const,

  /** Articles by author */
  articlesByAuthor: (authorId: number) => `articles-author-${authorId}` as const,

  /** Articles by tag */
  articlesByTag: (tagId: number) => `articles-tag-${tagId}` as const,

  /** Single category by ID */
  categoryById: (id: number) => `category-${id}` as const,

  /** Category by slug */
  categoryBySlug: (slug: string) => `category-slug-${slug}` as const,

  /** Single author by ID */
  authorById: (id: number) => `author-${id}` as const,

  /** Author by slug */
  authorBySlug: (slug: string) => `author-slug-${slug}` as const,

  /** Single tag by ID */
  tagById: (id: number) => `tag-${id}` as const,

  /** Tag by slug */
  tagBySlug: (slug: string) => `tag-slug-${slug}` as const,

  // =========================================================================
  // Page-Level Tags
  // =========================================================================

  /** Homepage cache */
  homepage: 'homepage',

  /** Important articles list */
  importantArticles: 'important-articles',

  /** Special articles (breaking, alert, flash) */
  specialArticles: 'special-articles',

  /** Trending articles */
  trendingArticles: 'trending-articles',

  /** Archive page by year */
  archiveYear: (year: number) => `archive-${year}` as const,

  /** Archive page by year and month */
  archiveMonth: (year: number, month: number) => `archive-${year}-${month}` as const,

  /** Search results */
  search: 'search',

  /** Navigation menu */
  navigation: 'navigation',

  // =========================================================================
  // Locale Tags
  // =========================================================================

  /** Locale-specific content */
  locale: (locale: string) => `locale-${locale}` as const,

  // =========================================================================
  // Video Tags
  // =========================================================================

  /** Video shows list */
  videoShows: 'video-shows',

  /** Homepage videos */
  homepageVideos: 'homepage-videos',

  /** Video by show ID */
  videosByShow: (showId: number) => `videos-show-${showId}` as const,

  // =========================================================================
  // Static Content Tags
  // =========================================================================

  /** Static pages (about, contact, etc.) */
  staticPages: 'static-pages',

  /** Footer content */
  footer: 'footer',

} as const;

// ============================================================================
// Cache TTL Presets (for reference - actual TTL handled by cacheLife)
// ============================================================================

/**
 * Recommended TTL values for different content types
 * These are reference values - actual caching is handled by Next.js cacheLife profiles
 */
export const CACHE_TTL = {
  /** Real-time content: 30 seconds */
  realtime: 30,

  /** Frequently updated: 1 minute */
  frequent: 60,

  /** Standard content: 2 minutes */
  standard: 120,

  /** Stable content: 5 minutes */
  stable: 300,

  /** Static content: 1 hour */
  static: 3600,

  /** Archive content: 1 day */
  archive: 86400,
} as const;

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get all tags for an article (for comprehensive invalidation)
 */
export function getArticleCacheTags(
  articleId: number,
  categoryId?: number,
  authorIds?: number[],
  tagIds?: number[],
  locale?: string
): string[] {
  const tags: string[] = [
    CACHE_TAGS.articles,
    CACHE_TAGS.articleById(articleId),
  ];

  if (categoryId) {
    tags.push(CACHE_TAGS.articlesByCategory(categoryId));
  }

  if (authorIds) {
    authorIds.forEach(id => tags.push(CACHE_TAGS.articlesByAuthor(id)));
  }

  if (tagIds) {
    tagIds.forEach(id => tags.push(CACHE_TAGS.articlesByTag(id)));
  }

  if (locale) {
    tags.push(CACHE_TAGS.locale(locale));
  }

  // Always invalidate homepage as articles may appear there
  tags.push(CACHE_TAGS.homepage);

  return tags;
}

/**
 * Get all tags for a category (for comprehensive invalidation)
 */
export function getCategoryCacheTags(
  categoryId: number,
  slug?: string,
  locale?: string
): string[] {
  const tags: string[] = [
    CACHE_TAGS.categories,
    CACHE_TAGS.categoryById(categoryId),
  ];

  if (slug) {
    tags.push(CACHE_TAGS.categoryBySlug(slug));
  }

  if (locale) {
    tags.push(CACHE_TAGS.locale(locale));
  }

  // Category changes affect navigation
  tags.push(CACHE_TAGS.navigation);

  return tags;
}

/**
 * Get all homepage-related tags for invalidation
 */
export function getHomepageCacheTags(locale?: string): string[] {
  const tags: string[] = [
    CACHE_TAGS.homepage,
    CACHE_TAGS.importantArticles,
    CACHE_TAGS.specialArticles,
    CACHE_TAGS.trendingArticles,
    CACHE_TAGS.homepageVideos,
  ];

  if (locale) {
    tags.push(CACHE_TAGS.locale(locale));
  }

  return tags;
}

// ============================================================================
// Type Exports
// ============================================================================

export type CacheTag = typeof CACHE_TAGS[keyof typeof CACHE_TAGS];
export type CacheTTL = typeof CACHE_TTL[keyof typeof CACHE_TTL];
