/**
 * LiveText Analytics API Functions
 */

import type {
  LiveTextAnalytics,
  LiveTextViewerCount,
  TrackViewRequest,
  TrackViewResponse
} from '@/lib/types/livetext';

/**
 * API configuration
 */
const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';
const API_BASE = `${API_URL}/api`;

/**
 * Fetch options for API requests
 */
interface FetchOptions {
  locale?: string;
  cache?: RequestCache;
  revalidate?: number;
}

/**
 * Build headers for API requests
 */
function buildHeaders(locale?: string): HeadersInit {
  const headers: HeadersInit = {
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  return headers;
}

/**
 * Get analytics for a LiveText
 *
 * @param liveTextId - The LiveText ID
 * @param detailed - Whether to include detailed analytics (views over time, post engagement, etc.)
 * @param options - Fetch options
 * @returns Promise with LiveTextAnalytics data
 *
 * @example
 * ```ts
 * const analytics = await getLiveTextAnalytics(123, true);
 * console.log(analytics.totalViews, analytics.currentViewers);
 * ```
 */
export async function getLiveTextAnalytics(
  liveTextId: number,
  detailed: boolean = false,
  options: FetchOptions = {}
): Promise<LiveTextAnalytics> {
  const detailedParam = detailed ? '?detailed=true' : '';
  const url = `${API_BASE}/live_texts/${liveTextId}/analytics${detailedParam}`;

  const response = await fetch(url, {
    headers: buildHeaders(options.locale),
    cache: options.cache || 'no-store',
    next: options.revalidate !== undefined ? { revalidate: options.revalidate } : undefined
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch analytics: ${response.status} ${response.statusText}`);
  }

  return await response.json();
}

/**
 * Get current viewer count for a LiveText
 *
 * @param liveTextId - The LiveText ID
 * @returns Promise with current viewer count
 *
 * @example
 * ```ts
 * const { currentViewers } = await getLiveTextViewerCount(123);
 * console.log(`${currentViewers} watching now`);
 * ```
 */
export async function getLiveTextViewerCount(
  liveTextId: number
): Promise<LiveTextViewerCount> {
  const url = `${API_BASE}/live_texts/${liveTextId}/viewers`;

  const response = await fetch(url, {
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    cache: 'no-store'
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch viewer count: ${response.status} ${response.statusText}`);
  }

  return await response.json();
}

/**
 * Track a view session (initial view or heartbeat)
 *
 * @param liveTextId - The LiveText ID
 * @param request - Track view request (sessionId, optional timeSpent)
 * @returns Promise with TrackViewResponse
 *
 * @example
 * ```ts
 * const sessionId = generateSessionId();
 * const response = await trackLiveTextView(123, { sessionId, timeSpent: 30 });
 * console.log(`View tracked, ${response.currentViewers} viewers`);
 * ```
 */
export async function trackLiveTextView(
  liveTextId: number,
  request: TrackViewRequest
): Promise<TrackViewResponse> {
  const url = `${API_BASE}/live_texts/${liveTextId}/track_view`;

  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(request)
  });

  if (!response.ok) {
    throw new Error(`Failed to track view: ${response.status} ${response.statusText}`);
  }

  return await response.json();
}

/**
 * Generate a unique session ID for anonymous viewers
 *
 * @returns A unique session ID string
 *
 * @example
 * ```ts
 * const sessionId = generateSessionId();
 * localStorage.setItem('livetext_session_id', sessionId);
 * ```
 */
export function generateSessionId(): string {
  return `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
}
