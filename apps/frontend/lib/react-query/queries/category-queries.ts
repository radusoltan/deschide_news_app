/**
 * Category Query Definitions
 * React Query queries for category data
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type { UseQueryOptions, UseMutationOptions } from '@tanstack/react-query';
import { get, post, put, del } from '@/lib/api/client';
import { categoryCache, createCacheKey } from '@/lib/services/cache-manager';
import type { Locale } from '@/lib/types';

/**
 * Query Keys
 */
export const categoryKeys = {
  all: ['categories'] as const,
  lists: () => [...categoryKeys.all, 'list'] as const,
  list: (locale: Locale) => [...categoryKeys.lists(), locale] as const,
  details: () => [...categoryKeys.all, 'detail'] as const,
  detail: (id: number, locale: Locale) => [...categoryKeys.details(), id, locale] as const,
  bySlug: (slug: string, locale: Locale) =>
    [...categoryKeys.all, 'slug', slug, locale] as const,
};

/**
 * Fetch all categories
 */
export function useCategories(
  locale: Locale,
  options?: Omit<UseQueryOptions<unknown, Error>, 'queryKey' | 'queryFn'>
) {
  return useQuery({
    queryKey: categoryKeys.list(locale),
    queryFn: async () => {
      // Check cache first
      const cacheKey = createCacheKey('categories', locale);
      const cached = categoryCache.get(cacheKey);

      if (cached) {
        return cached;
      }

      // Fetch from API
      const data = await get('/api/categories', { locale });

      // Cache for 10 minutes
      categoryCache.set(cacheKey, data, 600000);

      return data;
    },
    // Categories don't change often, can be cached longer
    staleTime: 10 * 60 * 1000, // 10 minutes
    ...options,
  });
}

/**
 * Fetch category by ID
 */
export function useCategory(
  id: number,
  locale: Locale,
  options?: Omit<UseQueryOptions<unknown, Error>, 'queryKey' | 'queryFn'>
) {
  return useQuery({
    queryKey: categoryKeys.detail(id, locale),
    queryFn: async () => {
      const cacheKey = createCacheKey('category', id, locale);
      const cached = categoryCache.get(cacheKey);

      if (cached) {
        return cached;
      }

      const data = await get(`/api/categories/${id}`, { locale });

      // Cache for 10 minutes
      categoryCache.set(cacheKey, data, 600000);

      return data;
    },
    staleTime: 10 * 60 * 1000,
    ...options,
  });
}

/**
 * Fetch category by slug
 */
export function useCategoryBySlug(
  slug: string,
  locale: Locale,
  options?: Omit<UseQueryOptions<unknown, Error>, 'queryKey' | 'queryFn'>
) {
  return useQuery({
    queryKey: categoryKeys.bySlug(slug, locale),
    queryFn: async () => {
      const cacheKey = createCacheKey('category-slug', slug, locale);
      const cached = categoryCache.get(cacheKey);

      if (cached) {
        return cached;
      }

      // Fetch from API - assumes backend has by-slug endpoint
      const data = await get(`/api/categories/by-slug/${slug}`, { locale });

      // Cache for 10 minutes
      categoryCache.set(cacheKey, data, 600000);

      return data;
    },
    staleTime: 10 * 60 * 1000,
    ...options,
  });
}

/**
 * Create category mutation
 */
export function useCreateCategory(
  options?: UseMutationOptions<unknown, Error, unknown>
) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: unknown) => {
      return post('/api/categories', data);
    },
    onSuccess: () => {
      // Invalidate all category lists
      queryClient.invalidateQueries({ queryKey: categoryKeys.lists() });

      // Clear category cache
      categoryCache.clear();
    },
    ...options,
  });
}

/**
 * Update category mutation
 */
export function useUpdateCategory(
  id: number,
  options?: UseMutationOptions<unknown, Error, unknown>
) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: unknown) => {
      return put(`/api/categories/${id}`, data);
    },
    onSuccess: () => {
      // Invalidate category detail and lists
      queryClient.invalidateQueries({ queryKey: categoryKeys.detail(id, 'ro') });
      queryClient.invalidateQueries({ queryKey: categoryKeys.detail(id, 'en') });
      queryClient.invalidateQueries({ queryKey: categoryKeys.detail(id, 'ru') });
      queryClient.invalidateQueries({ queryKey: categoryKeys.lists() });

      // Clear category cache
      categoryCache.clear();
    },
    ...options,
  });
}

/**
 * Delete category mutation
 */
export function useDeleteCategory(
  id: number,
  options?: UseMutationOptions<unknown, Error, void>
) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async () => {
      return del(`/api/categories/${id}`);
    },
    onSuccess: () => {
      // Invalidate category detail and lists
      queryClient.invalidateQueries({ queryKey: categoryKeys.detail(id, 'ro') });
      queryClient.invalidateQueries({ queryKey: categoryKeys.detail(id, 'en') });
      queryClient.invalidateQueries({ queryKey: categoryKeys.detail(id, 'ru') });
      queryClient.invalidateQueries({ queryKey: categoryKeys.lists() });

      // Clear category cache
      categoryCache.clear();
    },
    ...options,
  });
}

/**
 * Prefetch categories
 */
export async function prefetchCategories(
  queryClient: ReturnType<typeof useQueryClient>,
  locale: Locale
) {
  await queryClient.prefetchQuery({
    queryKey: categoryKeys.list(locale),
    queryFn: async () => {
      return get('/api/categories', { locale });
    },
  });
}

/**
 * Prefetch category by slug
 */
export async function prefetchCategoryBySlug(
  queryClient: ReturnType<typeof useQueryClient>,
  slug: string,
  locale: Locale
) {
  await queryClient.prefetchQuery({
    queryKey: categoryKeys.bySlug(slug, locale),
    queryFn: async () => {
      return get(`/api/categories/by-slug/${slug}`, { locale });
    },
  });
}
