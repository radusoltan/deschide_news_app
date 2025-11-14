'use client';

import { useState, useEffect } from 'react';
import { getSession } from '@/lib/auth/session';

// ============================================================================
// Types
// ============================================================================

export interface Article {
  '@id': string;
  '@type': string;
  id: number;
  title: string;
  slug: string;
  content?: string;
  excerpt?: string;
  status?: string;
  publishedAt?: string;
  createdAt?: string;
  updatedAt?: string;
  category?: string | object;
  author?: string | object;
}

export interface ArticlesCollection {
  '@context': string;
  '@id': string;
  '@type': string;
  totalItems: number;
  member: Article[];
}

export interface UseArticlesOptions {
  page?: number;
  itemsPerPage?: number;
  locale?: string;
  autoFetch?: boolean; // Auto-fetch on mount
}

export interface UseArticlesReturn {
  articles: Article[];
  totalItems: number;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

// ============================================================================
// Hook
// ============================================================================

/**
 * Hook for fetching articles from the API
 * Automatically handles authentication and session management
 */
export function useArticles(options: UseArticlesOptions = {}): UseArticlesReturn {
  const {
    page = 1,
    itemsPerPage = 30,
    locale = 'ro',
    autoFetch = true,
  } = options;

  const [articles, setArticles] = useState<Article[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchArticles = async () => {
    setLoading(true);
    setError(null);

    try {
      // Get session with fresh token
      const session = await getSession();

      if (!session || !session.tokens.accessToken) {
        throw new Error('Not authenticated');
      }

      // Build query params
      const queryParams = new URLSearchParams();
      queryParams.set('page', page.toString());
      queryParams.set('itemsPerPage', itemsPerPage.toString());

      // Fetch articles
      const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';
      const response = await fetch(
        `${API_BASE_URL}/api/articles?${queryParams.toString()}`,
        {
          headers: {
            'Authorization': `Bearer ${session.tokens.accessToken}`,
            'Accept-Language': locale,
            'Content-Type': 'application/ld+json',
          },
          cache: 'no-store',
        }
      );

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.message || `HTTP ${response.status}`);
      }

      const data: ArticlesCollection = await response.json();
      setArticles(data.member || []);
      setTotalItems(data.totalItems || 0);
    } catch (err) {
      console.error('Failed to fetch articles:', err);
      setError(err instanceof Error ? err.message : 'Failed to fetch articles');
      setArticles([]);
      setTotalItems(0);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (autoFetch) {
      fetchArticles();
    }
  }, [page, itemsPerPage, locale, autoFetch]);

  return {
    articles,
    totalItems,
    loading,
    error,
    refetch: fetchArticles,
  };
}
