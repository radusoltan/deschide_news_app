'use client';

/**
 * Article Hook
 * High-level hook for article operations
 */

import { useArticleBySlug } from '../react-query/queries/article-queries';
import type { Locale } from '../types';

export interface UseArticleOptions {
  enabled?: boolean;
  onError?: (error: Error) => void;
  onSuccess?: (data: unknown) => void;
}

/**
 * Hook for fetching article by slug
 * Combines slug resolution and data fetching
 */
export function useArticle(
  categorySlug: string,
  articleSlug: string,
  locale: Locale,
  options?: UseArticleOptions
) {
  const query = useArticleBySlug(categorySlug, articleSlug, locale, {
    enabled: options?.enabled,
  });

  // Handle success
  if (query.isSuccess && options?.onSuccess) {
    options.onSuccess(query.data);
  }

  // Handle error
  if (query.isError && options?.onError) {
    options.onError(query.error);
  }

  return {
    article: query.data,
    isLoading: query.isLoading,
    isError: query.isError,
    error: query.error,
    isSuccess: query.isSuccess,
    refetch: query.refetch,
  };
}

/**
 * Get article loading state message
 */
export function getArticleLoadingMessage(isLoading: boolean, isError: boolean): string {
  if (isLoading) {
    return 'Loading article...';
  }
  if (isError) {
    return 'Failed to load article';
  }
  return '';
}

/**
 * Check if article data is valid
 */
export function isValidArticle(article: unknown): boolean {
  if (!article || typeof article !== 'object') {
    return false;
  }

  const a = article as Record<string, unknown>;
  return !!(a.id && a.title && a.slug);
}
