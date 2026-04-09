/**
 * @deprecated Use `@/lib/api/api-client` instead.
 * This client is superseded by the unified API client which consolidates
 * retry, timeout, and token refresh from all three legacy transports.
 *
 * Enhanced API Client for Backend Communication
 * Provides base functionality with error handling, retry logic, and timeout support
 */

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';
const DEFAULT_TIMEOUT = 5000; // 5 seconds
const MAX_RETRIES = 3;
const RETRY_DELAY = 1000; // 1 second

// ============================================================================
// Types
// ============================================================================

export interface ApiClientOptions extends RequestInit {
  token?: string;
  timeout?: number;
  retries?: number;
  locale?: string;
  skipRetry?: boolean;
}

export interface ApiErrorResponse {
  code: number;
  message: string;
  errors?: Record<string, string[]>;
}

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public code?: number,
    public errors?: Record<string, string[]>
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export class TimeoutError extends Error {
  constructor(message: string = 'Request timeout') {
    super(message);
    this.name = 'TimeoutError';
  }
}

export class NetworkError extends Error {
  constructor(message: string = 'Network error') {
    super(message);
    this.name = 'NetworkError';
  }
}

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Sleep helper for retry delays
 */
function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Check if error is retryable
 */
function isRetryableError(error: unknown): boolean {
  if (error instanceof ApiError) {
    // Retry on server errors (5xx) but not client errors (4xx)
    return error.status >= 500;
  }
  if (error instanceof NetworkError) {
    return true;
  }
  return false;
}

/**
 * Log request/response in development
 */
function logRequest(
  method: string,
  url: string,
  options?: ApiClientOptions
): void {
  if (process.env.NODE_ENV === 'development') {
    console.log(`[API Request] ${method} ${url}`, {
      headers: options?.headers,
      body: options?.body,
    });
  }
}

function logResponse(
  method: string,
  url: string,
  status: number,
  data?: unknown
): void {
  if (process.env.NODE_ENV === 'development') {
    console.log(`[API Response] ${method} ${url} - ${status}`, data);
  }
}

function logError(method: string, url: string, error: unknown): void {
  if (process.env.NODE_ENV === 'development') {
    console.error(`[API Error] ${method} ${url}`, error);
  }
}

// ============================================================================
// Core API Client
// ============================================================================

/**
 * Make API request with timeout, retry logic, and error handling
 *
 * @param endpoint - API endpoint path (e.g., '/api/articles')
 * @param options - Request options with custom timeout, retries, etc.
 * @returns Parsed JSON response
 * @throws ApiError, TimeoutError, or NetworkError
 */
export async function apiRequest<T>(
  endpoint: string,
  options: ApiClientOptions = {}
): Promise<T> {
  const {
    token,
    timeout = DEFAULT_TIMEOUT,
    retries = MAX_RETRIES,
    locale,
    skipRetry = false,
    ...fetchOptions
  } = options;

  const method = fetchOptions.method || 'GET';
  const url = `${API_BASE_URL}${endpoint}`;

  // Build headers
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(fetchOptions.headers as Record<string, string>),
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  // Debug logging
  if (process.env.NODE_ENV === 'development' && token) {
    console.log(`[API] Token present for ${url}, headers:`, { Authorization: headers['Authorization']?.substring(0, 30) + '...' });
  }

  logRequest(method, url, { ...options, headers });

  // Retry loop
  let lastError: unknown;
  const maxAttempts = skipRetry ? 1 : retries;

  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    try {
      // Create abort controller for timeout
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), timeout);

      try {
        const response = await fetch(url, {
          ...fetchOptions,
          headers,
          signal: controller.signal,
        });

        clearTimeout(timeoutId);

        // Handle non-OK responses
        if (!response.ok) {
          // On 401, try to get a fresh token and retry once
          if (response.status === 401 && token && attempt === 1) {
            try {
              const tokenResponse = await fetch('/api/auth/token', { cache: 'no-store' });
              if (tokenResponse.ok) {
                const { token: newToken } = await tokenResponse.json();
                if (newToken && newToken !== token) {
                  headers['Authorization'] = `Bearer ${newToken}`;
                  const retryResponse = await fetch(url, {
                    ...fetchOptions,
                    headers,
                    signal: controller.signal,
                  });
                  if (retryResponse.ok) {
                    const data = await retryResponse.json();
                    logResponse(method, url, retryResponse.status, data);
                    return data;
                  }
                }
              }
            } catch (refreshErr) {
              logError(method, url, refreshErr);
            }
          }

          const errorData: ApiErrorResponse = await response
            .json()
            .catch(() => ({
              code: response.status,
              message: `Request failed with status ${response.status}`,
            }));

          const error = new ApiError(
            errorData.message || `HTTP ${response.status}`,
            response.status,
            errorData.code,
            errorData.errors
          );

          // Don't retry client errors (4xx)
          if (response.status >= 400 && response.status < 500) {
            logError(method, url, error);
            throw error;
          }

          // Retry server errors (5xx)
          throw error;
        }

        // Success - parse and return
        const data = await response.json();
        logResponse(method, url, response.status, data);
        return data;
      } finally {
        clearTimeout(timeoutId);
      }
    } catch (error: unknown) {
      lastError = error;

      // Handle abort (timeout)
      if (error instanceof Error && error.name === 'AbortError') {
        lastError = new TimeoutError(
          `Request timeout after ${timeout}ms`
        );
      }

      // Handle network errors
      if (error instanceof TypeError && error.message.includes('fetch')) {
        lastError = new NetworkError('Network request failed');
      }

      // Check if we should retry
      if (attempt < maxAttempts && isRetryableError(lastError)) {
        logError(
          method,
          url,
          `Attempt ${attempt}/${maxAttempts} failed, retrying...`
        );
        await sleep(RETRY_DELAY * attempt); // Exponential backoff
        continue;
      }

      // No more retries or non-retryable error
      logError(method, url, lastError);
      throw lastError;
    }
  }

  // Should never reach here, but TypeScript needs it
  throw lastError;
}

/**
 * Convenience method for GET requests
 */
export async function get<T>(
  endpoint: string,
  options?: Omit<ApiClientOptions, 'method' | 'body'>
): Promise<T> {
  return apiRequest<T>(endpoint, { ...options, method: 'GET' });
}

/**
 * Convenience method for POST requests
 */
export async function post<T>(
  endpoint: string,
  body?: unknown,
  options?: Omit<ApiClientOptions, 'method' | 'body'>
): Promise<T> {
  return apiRequest<T>(endpoint, {
    ...options,
    method: 'POST',
    body: body ? JSON.stringify(body) : undefined,
  });
}

/**
 * Convenience method for PUT requests
 */
export async function put<T>(
  endpoint: string,
  body?: unknown,
  options?: Omit<ApiClientOptions, 'method' | 'body'>
): Promise<T> {
  return apiRequest<T>(endpoint, {
    ...options,
    method: 'PUT',
    body: body ? JSON.stringify(body) : undefined,
  });
}

/**
 * Convenience method for PATCH requests
 */
export async function patch<T>(
  endpoint: string,
  body?: unknown,
  options?: Omit<ApiClientOptions, 'method' | 'body'>
): Promise<T> {
  return apiRequest<T>(endpoint, {
    ...options,
    method: 'PATCH',
    body: body ? JSON.stringify(body) : undefined,
  });
}

/**
 * Convenience method for DELETE requests
 */
export async function del<T>(
  endpoint: string,
  options?: Omit<ApiClientOptions, 'method' | 'body'>
): Promise<T> {
  return apiRequest<T>(endpoint, { ...options, method: 'DELETE' });
}

// ============================================================================
// Health Check
// ============================================================================

/**
 * Check if API is available
 */
export async function healthCheck(): Promise<boolean> {
  try {
    await get('/api', { timeout: 2000, skipRetry: true });
    return true;
  } catch {
    return false;
  }
}
