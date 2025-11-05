/**
 * Data Access Layer (DAL)
 * Provides server-side data access with session verification
 * Based on Next.js authentication guide:
 * https://nextjs.org/docs/app/guides/authentication.md
 */

import 'server-only';
import { cookies } from 'next/headers';
import { decrypt, type SessionPayload } from '@/lib/auth/session';
import { cache } from 'react';

// ============================================================================
// Session Verification
// ============================================================================

export const verifySession = cache(async () => {
  const cookie = (await cookies()).get('session')?.value;

  if (!cookie) {
    return { isAuth: false };
  }

  const session = await decrypt(cookie);

  if (!session?.tokens?.accessToken) {
    return { isAuth: false };
  }

  // Note: We don't check token expiration here to avoid cookie modification errors
  // The API will return 401 if token is expired, and client can handle refresh
  return { isAuth: true, userId: session.user, tokens: session.tokens };
});

// ============================================================================
// API Helpers
// ============================================================================

/**
 * Get access token from current session
 * Returns null if not authenticated
 */
export async function getAccessToken(): Promise<string | null> {
  const session = await verifySession();
  return session.isAuth && session.tokens ? session.tokens.accessToken : null;
}

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

interface ApiRequestOptions extends RequestInit {
  locale?: string;
}

async function authenticatedFetch(
  endpoint: string,
  options: ApiRequestOptions = {}
): Promise<Response> {
  const session = await verifySession();

  if (!session.isAuth || !session.tokens) {
    throw new Error('Not authenticated');
  }

  const { locale, headers, ...fetchOptions } = options;

  const requestHeaders: Record<string, string> = {
    ...(headers as Record<string, string>),
    'Authorization': `Bearer ${session.tokens.accessToken}`,
    'Content-Type': 'application/ld+json',
    'Accept': 'application/ld+json',
  };

  if (locale) {
    requestHeaders['Accept-Language'] = locale;
  }

  return fetch(`${API_BASE_URL}${endpoint}`, {
    ...fetchOptions,
    headers: requestHeaders,
  });
}

// ============================================================================
// Articles Data Access
// ============================================================================

export interface Article {
  '@id': string;
  '@type': string;
  id: number;
  title: string;
  slug: string;
  lead?: string;
  content?: string;
  excerpt?: string;
  status?: string;
  publishedAt?: string;
  createdAt?: string;
  updatedAt?: string;
  category?: string | object;
  author?: string | object;
  authors?: Array<string | object>; // Array of author IRIs or objects
  relatedArticles?: Array<string | object>; // Array of related article IRIs or objects
}

export interface ArticlesCollection {
  '@context': string;
  '@id': string;
  '@type': string;
  totalItems: number;
  member: Article[];
}

export interface GetArticlesParams {
  page?: number;
  itemsPerPage?: number;
  locale?: string;
}

/**
 * Get articles from API (server-side only)
 * Automatically verifies session and adds authentication
 */
export async function getArticles(
  params: GetArticlesParams = {}
): Promise<ArticlesCollection> {
  const { page = 1, itemsPerPage = 30, locale = 'ro' } = params;

  const queryParams = new URLSearchParams();
  queryParams.set('page', page.toString());
  queryParams.set('itemsPerPage', itemsPerPage.toString());

  const response = await authenticatedFetch(
    `/api/articles?${queryParams.toString()}`,
    {
      locale,
      cache: 'no-store',
    }
  );

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.message || `Failed to fetch articles: ${response.status}`);
  }

  return response.json();
}

/**
 * Get single article by ID (server-side only)
 */
export async function getArticle(
  id: number,
  locale: string = 'ro'
): Promise<Article> {
  const response = await authenticatedFetch(`/api/articles/${id}`, {
    locale,
    cache: 'no-store',
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.message || `Failed to fetch article: ${response.status}`);
  }

  return response.json();
}

/**
 * Create new article (server-side only)
 */
export async function createArticle(
  data: {
    title: string;
    slug: string;
    lead?: string;
    content: string;
    excerpt?: string;
    status?: string;
    category?: string;
    authors?: string[]; // Array of author IRIs
  },
  locale: string = 'ro'
): Promise<Article> {
  const response = await authenticatedFetch('/api/articles', {
    method: 'POST',
    locale,
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    const errorText = await response.text();

    let error;
    try {
      error = JSON.parse(errorText);
    } catch {
      error = { message: errorText };
    }

    throw new Error(error.message || error['hydra:description'] || `Failed to create article: ${response.status}`);
  }

  return response.json();
}

/**
 * Update existing article (server-side only)
 */
export async function updateArticle(
  id: number,
  data: {
    title?: string;
    slug?: string;
    lead?: string;
    content?: string;
    excerpt?: string;
    status?: string;
    category?: string;
    authors?: string[]; // Array of author IRIs
  },
  locale: string = 'ro'
): Promise<Article> {
  const response = await authenticatedFetch(`/api/articles/${id}`, {
    method: 'PUT',
    locale,
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.message || `Failed to update article: ${response.status}`);
  }

  return response.json();
}

// ============================================================================
// Categories Data Access
// ============================================================================

export interface Category {
  '@id': string;
  '@type': string;
  id: number;
  title: string;
  slug: string;
  status?: string;
  onFrontPage?: boolean;
  createdAt?: string;
  updatedAt?: string;
}

export interface CategoriesCollection {
  '@context': string;
  '@id': string;
  '@type': string;
  totalItems: number;
  member: Category[];
}

/**
 * Get categories list (server-side only)
 */
export async function getCategories(
  params: GetArticlesParams = {}
): Promise<CategoriesCollection> {
  const { page = 1, itemsPerPage = 30, locale = 'ro' } = params;

  const queryParams = new URLSearchParams();
  queryParams.set('page', page.toString());
  queryParams.set('itemsPerPage', itemsPerPage.toString());

  const response = await authenticatedFetch(
    `/api/categories?${queryParams.toString()}`,
    {
      locale,
      cache: 'no-store',
    }
  );

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.message || `Failed to fetch categories: ${response.status}`);
  }

  return response.json();
}

/**
 * Get single category by ID (server-side only)
 */
export async function getCategory(
  id: number,
  locale: string = 'ro'
): Promise<Category> {
  const response = await authenticatedFetch(`/api/categories/${id}`, {
    locale,
    cache: 'no-store',
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.message || `Failed to fetch category: ${response.status}`);
  }

  return response.json();
}

/**
 * Create new category (server-side only)
 */
export async function createCategory(
  data: {
    title: string;
    slug: string;
    status?: string;
    onFrontPage?: boolean;
  },
  locale: string = 'ro'
): Promise<Category> {
  const response = await authenticatedFetch('/api/categories', {
    method: 'POST',
    locale,
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    const errorText = await response.text();
    console.error('Error response:', errorText);

    let error;
    try {
      error = JSON.parse(errorText);
    } catch {
      error = { message: errorText };
    }

    throw new Error(error.message || error['hydra:description'] || `Failed to create category: ${response.status}`);
  }

  return response.json();
}

/**
 * Update existing category (server-side only)
 */
export async function updateCategory(
  id: number,
  data: {
    title?: string;
    slug?: string;
    status?: string;
    onFrontPage?: boolean;
  },
  locale: string = 'ro'
): Promise<Category> {
  const response = await authenticatedFetch(`/api/categories/${id}`, {
    method: 'PUT',
    locale,
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    const errorText = await response.text();
    console.error('Error response:', errorText);

    let error;
    try {
      error = JSON.parse(errorText);
    } catch {
      error = { message: errorText };
    }

    throw new Error(error.message || error['hydra:description'] || `Failed to update category: ${response.status}`);
  }

  return response.json();
}
