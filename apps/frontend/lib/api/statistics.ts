/**
 * Statistics API Client
 * Handles fetching analytics and performance metrics
 */

import { apiRequest } from './client';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

// ============================================================================
// Types
// ============================================================================

export interface ArticleStats {
  article_id: number;
  title: string | null;
  current_views: number;
  stats: Array<{
    date: string;
    views: number;
    unique_visitors: number;
    avg_reading_time: number | null;
    completion_rate: number | null;
  }>;
}

export interface TrendingArticle {
  id: number;
  title: string | null;
  slug: string | null;
  category: {
    id: number;
    name: string;
    slug: string;
  } | null;
  views_24h: number;
  published_at: string;
}

export interface SiteStats {
  realtime: {
    unique_visitors_today: number;
  };
  stats: Array<{
    date: string;
    total_visits: number;
    unique_visitors: number;
    new_visitors: number;
    bounce_rate: number;
    avg_session_duration: number;
  }>;
}

export interface RealTimeStats {
  timestamp: number;
  active_sessions: number;
  unique_visitors_today: number;
  trending_now: Array<{ article_id: number; views: number }>;
}

export type DateRange = 'today' | 'yesterday' | '7days' | '30days';

// ============================================================================
// API Functions
// ============================================================================

/**
 * Get article statistics for a specific article
 * Requires admin authentication
 */
export async function getArticleStats(
  articleId: number,
  dateRange: DateRange = '7days',
  token?: string
): Promise<ArticleStats> {
  const endpoint = `/api/admin/stats/article/${articleId}?range=${dateRange}`;

  const response = await apiRequest<ArticleStats>(endpoint, {
    token,
    next: { revalidate: 60 }, // Cache for 60 seconds
  });

  return response;
}

/**
 * Get trending articles (last 24h)
 * Public endpoint - no authentication required
 */
export async function getTrendingArticles(
  limit: number = 10,
  locale: string = 'ro'
): Promise<TrendingArticle[]> {
  const endpoint = `/api/admin/stats/trending?limit=${limit}`;

  const response = await apiRequest<TrendingArticle[]>(endpoint, {
    locale,
    next: { revalidate: 60 }, // Cache for 60 seconds
  });

  return response;
}

/**
 * Get site-wide statistics
 * Requires admin authentication
 */
export async function getSiteStats(
  dateRange: DateRange = '7days',
  token?: string
): Promise<SiteStats> {
  const endpoint = `/api/admin/stats/site?range=${dateRange}`;

  const response = await apiRequest<SiteStats>(endpoint, {
    token,
    next: { revalidate: 60 }, // Cache for 60 seconds
  });

  return response;
}

/**
 * Get real-time statistics
 * Requires admin authentication
 * No caching - always fetches fresh data
 */
export async function getRealTimeStats(token?: string): Promise<RealTimeStats> {
  const endpoint = `/api/admin/stats/realtime`;

  const response = await apiRequest<RealTimeStats>(endpoint, {
    token,
    cache: 'no-store', // No caching for real-time data
  });

  return response;
}

/**
 * Client-side function to fetch real-time stats with fetch
 * Used in client components for polling
 */
export async function fetchRealTimeStatsClient(token: string): Promise<RealTimeStats> {
  const url = `${API_BASE_URL}/api/admin/stats/realtime`;

  const response = await fetch(url, {
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    cache: 'no-store',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch real-time stats: ${response.statusText}`);
  }

  return response.json();
}

/**
 * Client-side function to fetch trending articles with fetch
 * Used in client components
 */
export async function fetchTrendingArticlesClient(
  limit: number = 10,
  locale: string = 'ro'
): Promise<TrendingArticle[]> {
  const url = `${API_BASE_URL}/api/admin/stats/trending?limit=${limit}`;

  const response = await fetch(url, {
    headers: {
      'Accept-Language': locale,
      'Content-Type': 'application/json',
    },
    cache: 'no-store',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch trending articles: ${response.statusText}`);
  }

  return response.json();
}
