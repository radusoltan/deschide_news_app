/**
 * Data Access Layer (DAL)
 * Provides server-side data access with session verification
 * Based on Next.js authentication guide:
 * https://nextjs.org/docs/app/guides/authentication.md
 */

import 'server-only';
import { cookies } from 'next/headers';
import { decrypt, getSession, type SessionPayload } from '@/lib/auth/session';
import { isTokenExpired, isRefreshTokenExpired, refreshToken as apiRefreshToken } from '@/lib/api-client';
import { refreshSessionToken } from '@/lib/auth/actions';
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
 * Get a fresh access token from current session, refreshing if expired.
 * Returns null if not authenticated or refresh failed.
 */
export async function getAccessToken(): Promise<string | null> {
  return getFreshAccessToken();
}

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

interface ApiRequestOptions extends RequestInit {
  locale?: string;
}

/**
 * Get a fresh access token, refreshing if necessary.
 * Uses Server Action to properly update the session cookie after token refresh.
 *
 * Wrapped with React cache() to deduplicate within a single server render.
 * This prevents race conditions when multiple concurrent fetches (e.g.,
 * getArticles + getCategories on the same page) all detect an expired token
 * and try to refresh simultaneously — with single_use refresh tokens, only
 * the first refresh succeeds; the rest must reuse the same result.
 */
const getFreshAccessToken = cache(async (): Promise<string | null> => {
  const session = await getSession();
  if (!session) return null;

  const { accessToken, refreshToken, refreshTokenExpiresAt } = session.tokens;

  // If access token is still valid, return it
  if (!isTokenExpired(accessToken)) {
    return accessToken;
  }

  // Access token expired - check if refresh token is still valid
  if (isRefreshTokenExpired(refreshTokenExpiresAt)) {
    console.log('[DAL] Refresh token expired, session invalid');
    return null;
  }

  // Refresh the token directly during server rendering.
  // We cannot call cookies().set() during render (only in Server Actions
  // invoked from client or Route Handlers), so we just fetch a new access
  // token and return it for the current request without updating the cookie.
  try {
    console.log('[DAL] Access token expired, refreshing...');
    const newTokens = await apiRefreshToken(session.tokens.refreshToken);
    console.log('[DAL] Token refreshed successfully');
    return newTokens.token;
  } catch (error) {
    console.error('[DAL] Token refresh failed:', error);
    return null;
  }
});

async function authenticatedFetch(
  endpoint: string,
  options: ApiRequestOptions = {}
): Promise<Response> {
  // Get fresh token (refreshes automatically if expired)
  const accessToken = await getFreshAccessToken();

  if (!accessToken) {
    throw new Error('Not authenticated');
  }

  const { locale, headers, ...fetchOptions } = options;

  const requestHeaders: Record<string, string> = {
    'Authorization': `Bearer ${accessToken}`,
    'Content-Type': 'application/ld+json',
    'Accept': 'application/ld+json',
    ...(headers as Record<string, string>),
  };

  if (locale) {
    requestHeaders['Accept-Language'] = locale;
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...fetchOptions,
    headers: requestHeaders,
  });

  // Handle 401 responses - token might have expired during request
  if (response.status === 401) {
    console.log('[DAL] Got 401, attempting token refresh...');

    // Try to refresh and retry once
    const newToken = await getFreshAccessToken();
    if (newToken && newToken !== accessToken) {
      // Retry with new token
      requestHeaders['Authorization'] = `Bearer ${newToken}`;
      return fetch(`${API_BASE_URL}${endpoint}`, {
        ...fetchOptions,
        headers: requestHeaders,
      });
    }
  }

  return response;
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
  badge?: string | null;
  isFeatured?: boolean;
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
  category?: number;
  status?: string;
}

/**
 * Get articles from API (server-side only)
 * Automatically verifies session and adds authentication
 */
export async function getArticles(
  params: GetArticlesParams = {}
): Promise<ArticlesCollection> {
  const { page = 1, itemsPerPage = 30, locale = 'ro', category, status } = params;

  const queryParams = new URLSearchParams();
  queryParams.set('page', page.toString());
  queryParams.set('itemsPerPage', itemsPerPage.toString());
  if (category) queryParams.set('category', category.toString());
  if (status) queryParams.set('status', status);

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
    badge?: string | null;
    isFeatured?: boolean;
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
    badge?: string | null;
    isFeatured?: boolean;
  },
  locale: string = 'ro'
): Promise<Article> {
  const jsonBody = JSON.stringify(data);
  const response = await authenticatedFetch(`/api/articles/${id}`, {
    method: 'PUT',
    locale,
    body: jsonBody,
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.detail || error.message || `Failed to update article: ${response.status}`);
  }

  return response.json();
}

/**
 * Delete article (server-side only)
 */
export async function deleteArticle(id: number, locale: string = 'ro'): Promise<void> {
  const response = await authenticatedFetch(`/api/articles/${id}`, {
    method: 'DELETE',
    locale,
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.message || `Failed to delete article: ${response.status}`);
  }
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
  inMenu?: boolean;
  inFooterMenu?: boolean;
  parent?: any;
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
    parent?: string | null;
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
    parent?: string | null;
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

/**
 * Update front page positions for multiple categories
 */
export async function updateCategoryPositions(
  positions: Array<{ id: number; frontPagePosition: number }>,
  locale: string = 'ro'
): Promise<void> {
  for (const { id, frontPagePosition } of positions) {
    const response = await authenticatedFetch(`/api/categories/${id}`, {
      method: 'PUT',
      locale,
      body: JSON.stringify({ frontPagePosition }),
    });

    if (!response.ok) {
      throw new Error(`Failed to update position for category ${id}`);
    }
  }
}

/**
 * Delete category (server-side only)
 */
export async function deleteCategory(id: number, locale: string = 'ro'): Promise<void> {
  const response = await authenticatedFetch(`/api/categories/${id}`, {
    method: 'DELETE',
    locale,
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

    throw new Error(error.message || error['hydra:description'] || `Failed to delete category: ${response.status}`);
  }
}

// ============================================================================
// Authors Data Access
// ============================================================================

export interface Author {
  '@id': string;
  '@type': string;
  id: number;
  firstName: string;
  lastName: string;
  slug: string;
  email: string;
  bio?: string;
  status: string;
  isActive: boolean;
  twitter?: string;
  facebook?: string;
  linkedin?: string;
  website?: string;
  createdAt: string;
  updatedAt: string;
}

export interface AuthorsCollection {
  '@context': string;
  '@id': string;
  '@type': string;
  totalItems: number;
  member: Author[];
}

export async function getAuthors(
  params: GetArticlesParams = {}
): Promise<AuthorsCollection> {
  const { page = 1, itemsPerPage = 30, locale = 'ro' } = params;

  const queryParams = new URLSearchParams();
  queryParams.set('page', page.toString());
  queryParams.set('itemsPerPage', itemsPerPage.toString());

  const response = await authenticatedFetch(
    `/api/authors?${queryParams.toString()}`,
    {
      locale,
      cache: 'no-store',
    }
  );

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.message || `Failed to fetch authors: ${response.status}`);
  }

  return response.json();
}

export async function getAuthor(id: number, locale: string = 'ro'): Promise<Author> {
  const response = await authenticatedFetch(`/api/authors/${id}`, {
    locale,
    cache: 'no-store',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch author: ${response.status}`);
  }

  return response.json();
}

export async function createAuthor(
  data: Partial<Author>,
  locale: string = 'ro'
): Promise<Author> {
  const response = await authenticatedFetch('/api/authors', {
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

    throw new Error(error.message || error['hydra:description'] || `Failed to create author: ${response.status}`);
  }

  return response.json();
}

export async function updateAuthor(
  id: number,
  data: Partial<Author>,
  locale: string = 'ro'
): Promise<Author> {
  const response = await authenticatedFetch(`/api/authors/${id}`, {
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

    throw new Error(error.message || error['hydra:description'] || `Failed to update author: ${response.status}`);
  }

  return response.json();
}

export async function deleteAuthor(id: number, locale: string = 'ro'): Promise<void> {
  const response = await authenticatedFetch(`/api/authors/${id}`, {
    method: 'DELETE',
    locale,
  });

  if (!response.ok) {
    throw new Error(`Failed to delete author: ${response.status}`);
  }
}

// ============================================================================
// Users Data Access
// ============================================================================

export interface User {
  '@id': string;
  '@type': string;
  id: number;
  username: string;
  email: string;
  firstName?: string;
  lastName?: string;
  roles: string[];
  active: boolean;
}

export interface UsersCollection {
  '@context': string;
  '@id': string;
  '@type': string;
  totalItems: number;
  member: User[];
}

export async function getUsers(
  params: { page?: number; itemsPerPage?: number } = {}
): Promise<UsersCollection> {
  const { page = 1, itemsPerPage = 20 } = params;

  const queryParams = new URLSearchParams();
  queryParams.set('page', page.toString());
  queryParams.set('itemsPerPage', itemsPerPage.toString());

  const response = await authenticatedFetch(
    `/api/users?${queryParams.toString()}`,
    { cache: 'no-store' }
  );

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.message || `Failed to fetch users: ${response.status}`);
  }

  return response.json();
}

export async function getUser(id: number): Promise<User> {
  const response = await authenticatedFetch(`/api/users/${id}`, {
    cache: 'no-store',
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.message || `Failed to fetch user: ${response.status}`);
  }

  return response.json();
}

export async function createUser(data: {
  username: string;
  email: string;
  plainPassword: string;
  roles: string[];
  firstName?: string;
  lastName?: string;
  isActive?: boolean;
}): Promise<User> {
  const response = await authenticatedFetch('/api/users', {
    method: 'POST',
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
    throw new Error(error.detail || error['hydra:description'] || error.message || `Failed to create user: ${response.status}`);
  }

  return response.json();
}

export async function updateUser(
  id: number,
  data: {
    username?: string;
    email?: string;
    plainPassword?: string;
    roles?: string[];
    firstName?: string;
    lastName?: string;
    isActive?: boolean;
  }
): Promise<User> {
  // Remove empty plainPassword so it doesn't trigger validation
  const payload = { ...data };
  if (!payload.plainPassword) {
    delete payload.plainPassword;
  }

  const response = await authenticatedFetch(`/api/users/${id}`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/merge-patch+json',
    },
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const errorText = await response.text();
    let error;
    try {
      error = JSON.parse(errorText);
    } catch {
      error = { message: errorText };
    }
    throw new Error(error.detail || error['hydra:description'] || error.message || `Failed to update user: ${response.status}`);
  }

  return response.json();
}

export async function deleteUser(id: number): Promise<void> {
  const response = await authenticatedFetch(`/api/users/${id}`, {
    method: 'DELETE',
  });

  if (!response.ok) {
    const errorText = await response.text();
    let error;
    try {
      error = JSON.parse(errorText);
    } catch {
      error = { message: errorText };
    }
    throw new Error(error.detail || error['hydra:description'] || error.message || `Failed to delete user: ${response.status}`);
  }
}
