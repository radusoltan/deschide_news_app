import type {
  LiveText,
  LiveTextListItem,
  LiveTextStatus,
  LiveTextApiResponse,
  LiveTextCollectionResponse,
  LiveTextAnalytics,
  LiveTextViewerCount,
  TrackViewRequest,
  TrackViewResponse
} from '@/lib/types/livetext';

/**
 * API configuration
 */
const API_URL = process.env.NEXT_PUBLIC_API_URL ?? '';
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
 * LiveText list filters
 */
export interface LiveTextFilters {
  status?: LiveTextStatus | LiveTextStatus[];
  category?: number;
  locale?: string;
  page?: number;
  itemsPerPage?: number;
  orderBy?: 'startTime' | 'endTime' | 'createdAt' | 'title';
  orderDirection?: 'ASC' | 'DESC';
}

/**
 * Build headers for API requests
 */
function buildHeaders(locale?: string): HeadersInit {
  const headers: HeadersInit = {
    'Accept': 'application/ld+json',
    'Content-Type': 'application/ld+json'
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  return headers;
}

/**
 * Build query string from filters
 */
function buildQueryString(filters: LiveTextFilters): string {
  const params = new URLSearchParams();

  if (filters.status) {
    if (Array.isArray(filters.status)) {
      filters.status.forEach(s => params.append('status[]', s));
    } else {
      params.append('status', filters.status);
    }
  }

  if (filters.category) {
    params.append('category.id', filters.category.toString());
  }

  if (filters.locale) {
    params.append('locale', filters.locale);
  }

  if (filters.page) {
    params.append('page', filters.page.toString());
  }

  if (filters.itemsPerPage) {
    params.append('itemsPerPage', filters.itemsPerPage.toString());
  }

  if (filters.orderBy) {
    const direction = filters.orderDirection || 'DESC';
    params.append(`order[${filters.orderBy}]`, direction);
  }

  const queryString = params.toString();
  return queryString ? `?${queryString}` : '';
}

/**
 * Transform API response to LiveText type
 */
function transformLiveTextResponse(data: LiveTextApiResponse): LiveText {
  return {
    id: data.id,
    title: data.title,
    slug: data.slug,
    description: data.description,
    status: data.status,
    startTime: data.startTime,
    endTime: data.endTime,
    locale: data.locale,
    author: data.author,
    category: data.category,
    template: (data as any).template || null,
    sportMatch: data.sportMatch || null,
    collaborators: data.collaborators,
    posts: data.posts,
    createdAt: data.createdAt,
    updatedAt: data.updatedAt
  };
}

/**
 * Fetch all LiveTexts with optional filtering
 *
 * @param filters - Filter options
 * @param options - Fetch options (locale, cache)
 * @returns Promise with array of LiveText items and pagination info
 *
 * @example
 * ```ts
 * const { items, totalItems } = await getLiveTexts({
 *   status: ['live', 'paused'],
 *   orderBy: 'startTime',
 *   orderDirection: 'DESC'
 * }, { locale: 'ro' });
 * ```
 */
export async function getLiveTexts(
  filters: LiveTextFilters = {},
  options: FetchOptions = {}
): Promise<{ items: LiveTextListItem[]; totalItems: number }> {
  const queryString = buildQueryString(filters);
  const url = `${API_BASE}/live_texts${queryString}`;

  const response = await fetch(url, {
    headers: buildHeaders(options.locale),
    cache: options.cache || 'no-store',
    next: options.revalidate !== undefined ? { revalidate: options.revalidate } : undefined
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch LiveTexts: ${response.status} ${response.statusText}`);
  }

  const data: LiveTextCollectionResponse = await response.json();

  return {
    items: data.member.map(transformLiveTextResponse) as LiveTextListItem[],
    totalItems: data.totalItems || data.member.length
  };
}

/**
 * Fetch a single LiveText by slug
 *
 * @param slug - The LiveText slug
 * @param options - Fetch options (locale, cache)
 * @returns Promise with LiveText data or null if not found
 *
 * @example
 * ```ts
 * const liveText = await getLiveTextBySlug('breaking-news-updates', { locale: 'ro' });
 * ```
 */
export async function getLiveTextBySlug(
  slug: string,
  options: FetchOptions = {}
): Promise<LiveText | null> {
  const url = `${API_BASE}/live_texts?slug=${encodeURIComponent(slug)}`;

  const response = await fetch(url, {
    headers: buildHeaders(options.locale),
    cache: options.cache || 'no-store',
    next: options.revalidate !== undefined ? { revalidate: options.revalidate } : undefined
  });

  if (!response.ok) {
    if (response.status === 404) {
      return null;
    }
    throw new Error(`Failed to fetch LiveText: ${response.status} ${response.statusText}`);
  }

  const data: LiveTextCollectionResponse = await response.json();

  if (!data.member || data.member.length === 0) {
    return null;
  }

  return transformLiveTextResponse(data.member[0]);
}

/**
 * Fetch a single LiveText by ID
 *
 * @param id - The LiveText ID
 * @param options - Fetch options (locale, cache)
 * @returns Promise with LiveText data
 *
 * @example
 * ```ts
 * const liveText = await getLiveTextById(123, { locale: 'ro' });
 * ```
 */
export async function getLiveTextById(
  id: number,
  options: FetchOptions = {}
): Promise<LiveText> {
  const url = `${API_BASE}/live_texts/${id}`;

  const response = await fetch(url, {
    headers: buildHeaders(options.locale),
    cache: options.cache || 'no-store',
    next: options.revalidate !== undefined ? { revalidate: options.revalidate } : undefined
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch LiveText: ${response.status} ${response.statusText}`);
  }

  const data: LiveTextApiResponse = await response.json();
  return transformLiveTextResponse(data);
}

/**
 * Fetch active LiveTexts (live or paused status)
 *
 * @param options - Fetch options (locale, cache)
 * @returns Promise with array of active LiveText items
 *
 * @example
 * ```ts
 * const activeLiveTexts = await getActiveLiveTexts({ locale: 'ro' });
 * ```
 */
export async function getActiveLiveTexts(
  options: FetchOptions = {}
): Promise<LiveTextListItem[]> {
  const { items } = await getLiveTexts({
    status: ['live', 'paused'],
    orderBy: 'startTime',
    orderDirection: 'DESC'
  }, options);

  return items;
}

/**
 * Fetch key points (important posts) for a LiveText
 *
 * @param liveTextId - The LiveText ID
 * @param options - Fetch options (locale, cache)
 * @returns Promise with array of key point posts
 *
 * @example
 * ```ts
 * const keyPoints = await getLiveTextKeyPoints(123, { locale: 'ro' });
 * ```
 */
export async function getLiveTextKeyPoints(
  liveTextId: number,
  options: FetchOptions = {}
): Promise<any[]> {
  const url = `${API_BASE}/live_texts/${liveTextId}/key_points`;

  const response = await fetch(url, {
    headers: buildHeaders(options.locale),
    cache: options.cache || 'no-store',
    next: options.revalidate !== undefined ? { revalidate: options.revalidate } : undefined
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch key points: ${response.status} ${response.statusText}`);
  }

  const data = await response.json();
  return data.member || [];
}

/**
 * Get the Mercure topic URL for a LiveText
 *
 * @param liveTextId - The LiveText ID
 * @returns The Mercure topic URL
 */
export function getMercureTopicUrl(liveTextId: number): string {
  return `${API_URL}/api/live_texts/${liveTextId}`;
}

/**
 * Get all available LiveText templates
 *
 * @param options - Fetch options
 * @returns Array of templates
 */
export async function getLiveTextTemplates(
  options: FetchOptions = {}
): Promise<any[]> {
  const url = `${API_BASE}/live_text_templates`;

  const response = await fetch(url, {
    headers: buildHeaders(options.locale),
    cache: options.cache || 'default',
    next: options.revalidate !== undefined ? { revalidate: options.revalidate } : undefined
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch templates: ${response.status} ${response.statusText}`);
  }

  const data = await response.json();
  return data.member || [];
}
