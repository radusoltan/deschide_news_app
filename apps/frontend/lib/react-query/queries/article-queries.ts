/**
 * Article Query Definitions
 * React Query queries for article data
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type { UseQueryOptions, UseMutationOptions } from '@tanstack/react-query';
import { resolveAndFetchArticle, invalidateArticleCache } from '@/lib/services/slug-resolver';
import { get, post, put, del } from '@/lib/api/client';
import type { Locale } from '@/lib/types';

/**
 * Query Keys
 */
export const articleKeys = {
  all: ['articles'] as const,
  lists: () => [...articleKeys.all, 'list'] as const,
  list: (filters: string) => [...articleKeys.lists(), filters] as const,
  details: () => [...articleKeys.all, 'detail'] as const,
  detail: (id: number, locale: Locale) => [...articleKeys.details(), id, locale] as const,
  bySlug: (categorySlug: string, articleSlug: string, locale: Locale) =>
    [...articleKeys.all, 'slug', categorySlug, articleSlug, locale] as const,
};

/**
 * Fetch article by slug
 */
export function useArticleBySlug(
  categorySlug: string,
  articleSlug: string,
  locale: Locale,
  options?: Omit<UseQueryOptions<unknown, Error>, 'queryKey' | 'queryFn'>
) {
  return useQuery({
    queryKey: articleKeys.bySlug(categorySlug, articleSlug, locale),
    queryFn: async () => {
      const result = await resolveAndFetchArticle(categorySlug, articleSlug, locale);

      if (result.redirect) {
        throw new Error(`Redirect to ${result.redirect.url}`);
      }

      if (result.error) {
        throw new Error(result.error);
      }

      if (!result.article) {
        throw new Error('Article not found');
      }

      return result.article;
    },
    ...options,
  });
}

/**
 * Fetch article by ID
 */
export function useArticle(
  id: number,
  locale: Locale,
  options?: Omit<UseQueryOptions<unknown, Error>, 'queryKey' | 'queryFn'>
) {
  return useQuery({
    queryKey: articleKeys.detail(id, locale),
    queryFn: async () => {
      return get(`/api/articles/${id}`, { locale });
    },
    ...options,
  });
}

/**
 * Fetch articles list
 */
export function useArticles(
  filters: {
    category?: number;
    status?: string;
    page?: number;
    itemsPerPage?: number;
  } = {},
  locale?: Locale,
  options?: Omit<UseQueryOptions<unknown, Error>, 'queryKey' | 'queryFn'>
) {
  const filterString = JSON.stringify(filters);

  return useQuery({
    queryKey: articleKeys.list(filterString),
    queryFn: async () => {
      const params = new URLSearchParams();

      if (filters.category) {
        params.set('category[id]', filters.category.toString());
      }
      if (filters.status) {
        params.set('status', filters.status);
      }
      if (filters.page) {
        params.set('page', filters.page.toString());
      }
      if (filters.itemsPerPage) {
        params.set('itemsPerPage', filters.itemsPerPage.toString());
      }

      return get(`/api/articles?${params.toString()}`, { locale });
    },
    ...options,
  });
}

/**
 * Create article mutation
 */
export function useCreateArticle(
  options?: UseMutationOptions<unknown, Error, unknown>
) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: unknown) => {
      return post('/api/articles', data);
    },
    onSuccess: () => {
      // Invalidate articles list
      queryClient.invalidateQueries({ queryKey: articleKeys.lists() });
    },
    ...options,
  });
}

/**
 * Update article mutation
 */
export function useUpdateArticle(
  id: number,
  options?: UseMutationOptions<unknown, Error, unknown>
) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: unknown) => {
      return put(`/api/articles/${id}`, data);
    },
    onSuccess: () => {
      // Invalidate article detail and lists
      queryClient.invalidateQueries({ queryKey: articleKeys.detail(id, 'ro') });
      queryClient.invalidateQueries({ queryKey: articleKeys.detail(id, 'en') });
      queryClient.invalidateQueries({ queryKey: articleKeys.detail(id, 'ru') });
      queryClient.invalidateQueries({ queryKey: articleKeys.lists() });

      // Invalidate slug resolver cache
      invalidateArticleCache('', '', undefined);
    },
    ...options,
  });
}

/**
 * Delete article mutation
 */
export function useDeleteArticle(
  id: number,
  options?: UseMutationOptions<unknown, Error, void>
) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async () => {
      return del(`/api/articles/${id}`);
    },
    onSuccess: () => {
      // Invalidate article detail and lists
      queryClient.invalidateQueries({ queryKey: articleKeys.detail(id, 'ro') });
      queryClient.invalidateQueries({ queryKey: articleKeys.detail(id, 'en') });
      queryClient.invalidateQueries({ queryKey: articleKeys.detail(id, 'ru') });
      queryClient.invalidateQueries({ queryKey: articleKeys.lists() });
    },
    ...options,
  });
}

/**
 * Prefetch article by slug
 */
export async function prefetchArticleBySlug(
  queryClient: ReturnType<typeof useQueryClient>,
  categorySlug: string,
  articleSlug: string,
  locale: Locale
) {
  await queryClient.prefetchQuery({
    queryKey: articleKeys.bySlug(categorySlug, articleSlug, locale),
    queryFn: async () => {
      const result = await resolveAndFetchArticle(categorySlug, articleSlug, locale);
      return result.article;
    },
  });
}
