/**
 * Important Articles API Service
 * Public endpoint for fetching important articles (no authentication required)
 */

import { ImportantArticlesListResponse } from '../types/article';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

/**
 * Fetch important articles list
 * GET /api/important_articles
 *
 * @param locale - Language locale (ro, en, ru). Optional - defaults to browser/server locale
 * @returns ImportantArticlesListResponse with articles ordered by position
 */
export async function fetchImportantArticles(
  locale?: string
): Promise<ImportantArticlesListResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const response = await fetch(`${API_BASE_URL}/api/important_articles`, {
    method: 'GET',
    headers,
    next: { tags: ['articles'] },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch important articles: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Helper function to get featured image from article
 * Returns the first featured image or first image if no featured image exists
 *
 * @param articleImages - Array of ArticleImage objects
 * @returns Image object or null
 */
export function getFeaturedImage(articleImages: any[]) {
  if (!articleImages || articleImages.length === 0) {
    return null;
  }

  // Find first featured image
  const featured = articleImages.find((ai) => ai.isFeatured);
  if (featured && typeof featured.image === 'object') {
    return featured.image;
  }

  // Fallback to first image with object data
  const first = articleImages[0];
  if (first && typeof first.image === 'object') {
    return first.image;
  }

  return null;
}

/**
 * Build CDN URL for an image
 *
 * @param path - Image path from API (e.g., "images/image_xxx.png")
 * @returns Full CDN URL
 */
export function buildImageUrl(path: string): string {
  const CDN_URL = process.env.NEXT_PUBLIC_CDN_URL || 'http://127.0.0.1:8082';
  return `${CDN_URL}/uploads/${path}`;
}

/**
 * Get thumbnail with specific profile from image thumbnails
 *
 * @param image - Image object with thumbnails array
 * @param profileName - Profile name to search for (e.g., "article_wide")
 * @returns Thumbnail object or null if not found
 */
export function getThumbnailByProfile(image: any, profileName: string) {
  if (!image || !image.thumbnails || !Array.isArray(image.thumbnails)) {
    return null;
  }

  return image.thumbnails.find(
    (thumb: any) =>
      typeof thumb.profile === 'object' && thumb.profile?.name === profileName
  );
}
