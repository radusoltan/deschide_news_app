/**
 * Data Access Layer - Barrel Exports
 * Centralized exports for cached data fetching with Next.js 16 `use cache` directive
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 2 Caching Alignment
 *
 * Usage:
 * ```typescript
 * import { getLatestArticles, CACHE_TAGS } from '@/lib/data';
 *
 * // In Server Components
 * const articles = await getLatestArticles('ro', 10);
 *
 * // For cache invalidation
 * revalidateTag(CACHE_TAGS.articles, 'max');
 * ```
 */

// ============================================================================
// Cache Configuration
// ============================================================================

export {
  CACHE_TAGS,
  CACHE_TTL,
  getArticleCacheTags,
  getCategoryCacheTags,
  getHomepageCacheTags,
  type CacheTag,
  type CacheTTL,
} from './cache-config';

// ============================================================================
// Articles Data Access
// ============================================================================

export {
  // Core fetching functions
  getLatestArticles,
  getArticlesByCategory,
  getArticleById,
  getImportantArticles,
  getSpecialArticles,
  getTrendingArticles,
  getArticlesByAuthor,
  getArticlesByTag,
  getArchiveArticles,
  getRelatedArticles,

  // Cached versions (request-level deduplication)
  getArticleByIdCached,
  getImportantArticlesCached,

  // Types
  type GetArticlesOptions,
} from './articles';

// Re-export Article type from types
export type { Article } from '@/lib/types/article';

// ============================================================================
// Categories Data Access
// ============================================================================

export {
  // Core fetching functions
  getAllCategories,
  getFrontPageCategories,
  getNavigationCategories,
  getCategoryById,
  getCategoryBySlug,

  // Cached versions (request-level deduplication)
  getAllCategoriesCached,
  getNavigationCategoriesCached,
  getCategoryBySlugCached,

  // Utilities
  getCategoryColor,
  getCategoryBadgeClass,

  // Types
  type CategoriesListResponse,
  type CategoryWithArticleCount,
} from './categories';

// ============================================================================
// Authors Data Access
// ============================================================================

export {
  // Core fetching functions
  getAllAuthors,
  getAuthorById,
  getAuthorBySlug,
  getFeaturedAuthors,

  // Cached versions (request-level deduplication)
  getAllAuthorsCached,
  getAuthorBySlugCached,
  getAuthorByIdCached,

  // Utilities
  getAuthorInitials,
  getAuthorAvatarUrl,

  // Types
  type Author,
  type AuthorsListResponse,
} from './authors';

// ============================================================================
// Videos Data Access
// ============================================================================

export {
  // Video Shows
  getAllVideoShows,
  getVideoShowBySlug,

  // YouTube Videos
  getHomepageVideos,
  getVideosByShow,
  getAllVideos,
  getVideoById,

  // Cached versions (request-level deduplication)
  getAllVideoShowsCached,
  getHomepageVideosCached,
  getVideoShowBySlugCached,

  // Utilities
  extractYouTubeId,
  getYouTubeThumbnail,
  getYouTubeEmbedUrl,
  formatVideoDuration,
} from './videos';
