/**
 * Unified API Client — single typed transport for Deschide News frontend.
 *
 * Consolidates best features from:
 * - lib/api/client.ts  (retry with exponential backoff, timeout, error classes)
 * - lib/api-client.ts  (server/client URL detection, auth token refresh)
 * - lib/dal.ts         (session-aware token injection for server components)
 *
 * Usage:
 *   import { api, ApiError, TimeoutError } from '@/lib/api/api-client';
 *   const articles = await api.get<ArticleListResponse>('/api/articles');
 */

// Server-side uses internal URL (bypasses Nginx), client-side uses public URL
const API_BASE_URL =
  (typeof window === 'undefined'
    ? process.env.INTERNAL_API_URL || process.env.NEXT_PUBLIC_API_URL
    : process.env.NEXT_PUBLIC_API_URL) ?? '';

const DEFAULT_TIMEOUT = 10_000; // 10s (generous for SSR)
const MAX_RETRIES = 3;
const INITIAL_RETRY_DELAY = 1_000;

// ============================================================================
// Error classes
// ============================================================================

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code?: number,
    public readonly errors?: Record<string, string[]>,
    public readonly violations?: Array<{
      propertyPath: string;
      message: string;
      code?: string;
    }>,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export class TimeoutError extends Error {
  constructor(message = 'Request timed out') {
    super(message);
    this.name = 'TimeoutError';
  }
}

export class NetworkError extends Error {
  constructor(message = 'Network error') {
    super(message);
    this.name = 'NetworkError';
  }
}

// ============================================================================
// Request options
// ============================================================================

export interface ApiRequestOptions extends Omit<RequestInit, 'body'> {
  /** JWT token — injected as Authorization: Bearer header */
  token?: string;
  /** Per-request timeout in ms (default 10 000) */
  timeout?: number;
  /** Max retry attempts for 5xx / network errors (default 3) */
  retries?: number;
  /** Accept-Language header value */
  locale?: string;
  /** Skip retry logic entirely */
  skipRetry?: boolean;
  /** Request body — objects are JSON.stringify'd automatically */
  body?: BodyInit | Record<string, unknown> | unknown[] | null;
}

// ============================================================================
// Helpers
// ============================================================================

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function isRetryable(err: unknown): boolean {
  if (err instanceof ApiError) return err.status >= 500;
  if (err instanceof NetworkError) return true;
  return false;
}

// ============================================================================
// Core request function
// ============================================================================

async function request<T>(
  endpoint: string,
  options: ApiRequestOptions = {},
): Promise<T> {
  const {
    token,
    timeout = DEFAULT_TIMEOUT,
    retries = MAX_RETRIES,
    locale,
    skipRetry = false,
    body,
    ...fetchInit
  } = options;

  const method = (fetchInit.method ?? 'GET').toUpperCase();
  const url = `${API_BASE_URL}${endpoint}`;

  // Build headers
  const headers: Record<string, string> = {
    Accept: 'application/ld+json',
    ...(fetchInit.headers as Record<string, string>),
  };

  if (body != null && !(body instanceof FormData)) {
    headers['Content-Type'] = 'application/ld+json';
  }

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  // Serialise body
  let serializedBody: BodyInit | null | undefined;
  if (body == null) {
    serializedBody = undefined;
  } else if (typeof body === 'string' || body instanceof FormData || body instanceof URLSearchParams) {
    serializedBody = body;
  } else {
    serializedBody = JSON.stringify(body);
  }

  // Retry loop
  const maxAttempts = skipRetry ? 1 : retries;
  let lastError: unknown;

  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeout);

    try {
      const res = await fetch(url, {
        ...fetchInit,
        method,
        headers,
        body: serializedBody,
        signal: controller.signal,
      });

      clearTimeout(timer);

      if (!res.ok) {
        // On 401 with token — try a single client-side token refresh
        if (res.status === 401 && token && attempt === 1 && typeof window !== 'undefined') {
          try {
            const refreshRes = await fetch('/api/auth/token', { cache: 'no-store' });
            if (refreshRes.ok) {
              const { token: freshToken } = await refreshRes.json();
              if (freshToken && freshToken !== token) {
                headers['Authorization'] = `Bearer ${freshToken}`;
                const retryRes = await fetch(url, {
                  ...fetchInit,
                  method,
                  headers,
                  body: serializedBody,
                });
                if (retryRes.ok) {
                  return retryRes.json();
                }
              }
            }
          } catch {
            // refresh failed — fall through to error handling
          }
        }

        const errBody = await res.json().catch(() => ({
          code: res.status,
          message: `HTTP ${res.status}`,
        }));

        const err = new ApiError(
          errBody.message ?? errBody['hydra:description'] ?? `HTTP ${res.status}`,
          res.status,
          errBody.code,
          errBody.errors,
          errBody.violations,
        );

        // 4xx are not retryable
        if (res.status >= 400 && res.status < 500) throw err;
        throw err; // 5xx falls through to retry check below
      }

      // 204 No Content
      if (res.status === 204) return undefined as T;

      return res.json();
    } catch (err: unknown) {
      clearTimeout(timer);
      lastError = err;

      if (err instanceof Error && err.name === 'AbortError') {
        lastError = new TimeoutError(`Timed out after ${timeout}ms: ${method} ${endpoint}`);
      }
      if (err instanceof TypeError && String(err.message).includes('fetch')) {
        lastError = new NetworkError(err.message);
      }

      if (attempt < maxAttempts && isRetryable(lastError)) {
        await sleep(INITIAL_RETRY_DELAY * attempt);
        continue;
      }

      throw lastError;
    }
  }

  throw lastError;
}

// ============================================================================
// Convenience methods
// ============================================================================

function get<T>(endpoint: string, opts?: Omit<ApiRequestOptions, 'method' | 'body'>): Promise<T> {
  return request<T>(endpoint, { ...opts, method: 'GET' });
}

function post<T>(endpoint: string, body?: ApiRequestOptions['body'], opts?: Omit<ApiRequestOptions, 'method' | 'body'>): Promise<T> {
  return request<T>(endpoint, { ...opts, method: 'POST', body });
}

function put<T>(endpoint: string, body?: ApiRequestOptions['body'], opts?: Omit<ApiRequestOptions, 'method' | 'body'>): Promise<T> {
  return request<T>(endpoint, { ...opts, method: 'PUT', body });
}

function patch<T>(endpoint: string, body?: ApiRequestOptions['body'], opts?: Omit<ApiRequestOptions, 'method' | 'body'>): Promise<T> {
  return request<T>(endpoint, { ...opts, method: 'PATCH', body });
}

function del<T = void>(endpoint: string, opts?: Omit<ApiRequestOptions, 'method' | 'body'>): Promise<T> {
  return request<T>(endpoint, { ...opts, method: 'DELETE' });
}

async function healthCheck(): Promise<boolean> {
  try {
    await get('/api', { timeout: 3_000, skipRetry: true });
    return true;
  } catch {
    return false;
  }
}

// ============================================================================
// Public API object
// ============================================================================

/**
 * Unified typed API client with retry, timeout, and token refresh.
 *
 * @example
 * ```ts
 * import { api } from '@/lib/api/api-client';
 * import type { ArticleListResponse } from '@/lib/api/types/entities';
 *
 * const data = await api.get<ArticleListResponse>('/api/articles', { locale: 'ro' });
 * ```
 */
export const api = {
  request,
  get,
  post,
  put,
  patch,
  del,
  healthCheck,
} as const;

export default api;
