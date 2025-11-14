/**
 * API Client for Backend Communication
 * Handles all HTTP requests to Symfony backend API
 */

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

// ============================================================================
// Types
// ============================================================================

export interface LoginCredentials {
  username: string;
  password: string;
}

export interface AuthTokens {
  token: string; // JWT access token
  refresh_token: string;
  refresh_token_expires_at: number; // Unix timestamp
}

export interface ApiErrorResponse {
  code: number;
  message: string;
}

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public code?: number
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

// ============================================================================
// Authentication Endpoints
// ============================================================================

/**
 * Login user and get JWT tokens
 * POST /api/login_check
 *
 * @param credentials - { username, password }
 * @returns AuthTokens with token, refresh_token, and expiration
 * @throws ApiError on invalid credentials (401) or server error
 */
export async function loginUser(credentials: LoginCredentials): Promise<AuthTokens> {
  const response = await fetch(`${API_BASE_URL}/api/login_check`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(credentials),
    cache: 'no-store',
  });

  if (!response.ok) {
    const error: ApiErrorResponse = await response.json().catch(() => ({
      code: response.status,
      message: 'Login failed'
    }));

    throw new ApiError(
      error.message || 'Invalid credentials',
      response.status,
      error.code
    );
  }

  return response.json();
}

/**
 * Refresh JWT token using refresh token
 * POST /api/token/refresh
 *
 * IMPORTANT: Only requires the refresh_token parameter
 * - Do NOT send Authorization header (causes "Expired JWT Token" error)
 * - refresh_token in form-data body
 *
 * @param refreshToken - The refresh token string
 * @returns New AuthTokens
 * @throws ApiError on invalid refresh token (401)
 */
export async function refreshToken(
  refreshToken: string
): Promise<AuthTokens> {
  const response = await fetch(`${API_BASE_URL}/api/token/refresh`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
      refresh_token: refreshToken,
    }),
    cache: 'no-store',
  });

  if (!response.ok) {
    const error: ApiErrorResponse = await response.json().catch(() => ({
      code: response.status,
      message: 'Token refresh failed'
    }));

    throw new ApiError(
      error.message || 'Failed to refresh token',
      response.status,
      error.code
    );
  }

  return response.json();
}

// ============================================================================
// Authenticated API Requests
// ============================================================================

export interface ApiRequestOptions extends RequestInit {
  token?: string;
}

/**
 * Make authenticated API request
 *
 * @param endpoint - API endpoint path (e.g., '/api/articles')
 * @param options - Fetch options with optional token
 * @returns Parsed JSON response
 * @throws ApiError on non-2xx responses
 */
export async function apiRequest<T>(
  endpoint: string,
  options: ApiRequestOptions = {}
): Promise<T> {
  const { token, ...fetchOptions } = options;

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(fetchOptions.headers as Record<string, string>),
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...fetchOptions,
    headers,
  });

  if (!response.ok) {
    const error: ApiErrorResponse = await response.json().catch(() => ({
      code: response.status,
      message: `Request failed with status ${response.status}`
    }));

    throw new ApiError(
      error.message || `HTTP ${response.status}`,
      response.status,
      error.code
    );
  }

  return response.json();
}

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Check if JWT token is expired
 *
 * @param token - JWT token string
 * @returns true if token is expired or invalid
 */
export function isTokenExpired(token: string): boolean {
  try {
    const payload = JSON.parse(atob(token.split('.')[1]));
    return payload.exp * 1000 < Date.now();
  } catch {
    return true;
  }
}

/**
 * Check if refresh token is expired
 *
 * @param expiresAt - Unix timestamp
 * @returns true if refresh token is expired
 */
export function isRefreshTokenExpired(expiresAt: number): boolean {
  return expiresAt * 1000 < Date.now();
}

/**
 * Get user info from JWT token
 *
 * @param token - JWT token string
 * @returns User payload or null if invalid
 */
export function getUserFromToken(token: string): {
  username: string;
  roles: string[];
  iat: number;
  exp: number;
} | null {
  try {
    const payload = JSON.parse(atob(token.split('.')[1]));
    return payload;
  } catch {
    return null;
  }
}
