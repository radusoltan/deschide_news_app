'use client';

/**
 * Category Hook
 * High-level hook for category operations
 */

import { useCategories, useCategoryBySlug } from '../react-query/queries/category-queries';
import type { Locale } from '../types';

export interface UseCategoryOptions {
  enabled?: boolean;
  onError?: (error: Error) => void;
  onSuccess?: (data: unknown) => void;
}

/**
 * Hook for fetching all categories
 */
export function useCategoriesList(locale: Locale, options?: UseCategoryOptions) {
  const query = useCategories(locale, {
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
    categories: query.data,
    isLoading: query.isLoading,
    isError: query.isError,
    error: query.error,
    isSuccess: query.isSuccess,
    refetch: query.refetch,
  };
}

/**
 * Hook for fetching category by slug
 */
export function useCategory(slug: string, locale: Locale, options?: UseCategoryOptions) {
  const query = useCategoryBySlug(slug, locale, {
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
    category: query.data,
    isLoading: query.isLoading,
    isError: query.isError,
    error: query.error,
    isSuccess: query.isSuccess,
    refetch: query.refetch,
  };
}

/**
 * Check if category data is valid
 */
export function isValidCategory(category: unknown): boolean {
  if (!category || typeof category !== 'object') {
    return false;
  }

  const c = category as Record<string, unknown>;
  return !!(c.id && c.title && c.slug);
}
