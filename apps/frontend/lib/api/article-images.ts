/**
 * Server-side API client functions for ArticleImage operations
 * These functions run on the server and use authentication
 */

import 'server-only';
import { getAccessToken } from '@/lib/dal';
import type { ArticleImage, ArticleImageListResponse } from '@/lib/types/image';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

/**
 * Fetch all images attached to an article
 */
export async function getArticleImages(articleId: number): Promise<ArticleImageListResponse> {
  const token = await getAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  // Force API Platform to include full image object instead of just IRI
  const url = `${API_BASE_URL}/api/article_images?article.id=${articleId}&order[position]=asc`;

  const response = await fetch(url, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/ld+json',
    },
    cache: 'no-store',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch article images: ${response.statusText}`);
  }

  return response.json();
}

/**
 * Attach an image to an article
 */
export async function attachImageToArticle(data: {
  articleId: number;
  imageId: number;
  position?: number;
  isFeatured?: boolean;
}): Promise<ArticleImage> {
  const token = await getAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  const payload = {
    article: `/api/articles/${data.articleId}`,
    image: `/api/images/${data.imageId}`,
    position: data.position ?? 0,
    isFeatured: data.isFeatured ?? false,
  };

  const response = await fetch(`${API_BASE_URL}/api/article_images`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/ld+json',
    },
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: response.statusText }));
    throw new Error(error.message || 'Failed to attach image to article');
  }

  return response.json();
}

/**
 * Update article-image metadata (position, isFeatured)
 */
export async function updateArticleImage(
  id: number,
  data: {
    position?: number;
    isFeatured?: boolean;
  }
): Promise<ArticleImage> {
  const token = await getAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  const response = await fetch(`${API_BASE_URL}/api/article_images/${id}`, {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/ld+json',
    },
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: response.statusText }));
    throw new Error(error.message || 'Failed to update article image');
  }

  return response.json();
}

/**
 * Detach an image from an article (delete the ArticleImage pivot record)
 */
export async function detachImageFromArticle(articleImageId: number): Promise<void> {
  const token = await getAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  const response = await fetch(`${API_BASE_URL}/api/article_images/${articleImageId}`, {
    method: 'DELETE',
    headers: {
      'Authorization': `Bearer ${token}`,
    },
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: response.statusText }));
    throw new Error(error.message || 'Failed to detach image from article');
  }
}

/**
 * Set featured image for an article
 * This will unset any existing featured image and set the new one
 */
export async function setFeaturedImage(articleId: number, articleImageId: number): Promise<void> {
  const token = await getAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  // First, fetch all article images to find current featured
  const articleImages = await getArticleImages(articleId);

  // Handle both 'hydra:member' and 'member' response formats
  const members = articleImages['hydra:member'] || (articleImages as { member?: ArticleImage[] }).member || [];

  // Unset current featured image if exists
  const currentFeatured = members.find((ai: ArticleImage) => ai.isFeatured);
  if (currentFeatured && currentFeatured.id !== articleImageId) {
    await updateArticleImage(currentFeatured.id, { isFeatured: false });
  }

  // Set new featured image
  await updateArticleImage(articleImageId, { isFeatured: true });
}

/**
 * Reorder article images (update positions)
 */
export async function reorderArticleImages(
  updates: Array<{ id: number; position: number }>
): Promise<void> {
  const token = await getAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  // Update all positions in parallel
  await Promise.all(
    updates.map(({ id, position }) =>
      updateArticleImage(id, { position })
    )
  );
}
