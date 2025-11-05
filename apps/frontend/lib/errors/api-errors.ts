/**
 * API Error Types and Utilities
 * Standardized error handling for API operations
 */

/**
 * Base API error class
 */
export class ApiError extends Error {
  constructor(
    message: string,
    public statusCode: number,
    public code?: string,
    public errors?: Record<string, string[]>
  ) {
    super(message);
    this.name = 'ApiError';
    Object.setPrototypeOf(this, ApiError.prototype);
  }

  /**
   * Check if error is retryable
   */
  isRetryable(): boolean {
    // Retry on server errors (5xx) and rate limiting (429)
    return this.statusCode >= 500 || this.statusCode === 429;
  }

  /**
   * Get user-friendly message
   */
  getUserMessage(): string {
    if (this.statusCode === 404) {
      return 'The requested resource was not found.';
    }
    if (this.statusCode === 429) {
      return 'Too many requests. Please try again later.';
    }
    if (this.statusCode >= 500) {
      return 'A server error occurred. Please try again.';
    }
    return this.message;
  }
}

/**
 * Not Found Error (404)
 */
export class NotFoundError extends ApiError {
  constructor(message: string = 'Resource not found', resource?: string) {
    super(message, 404, 'NOT_FOUND');
    this.name = 'NotFoundError';
    Object.setPrototypeOf(this, NotFoundError.prototype);

    if (resource) {
      this.message = `${resource} not found`;
    }
  }
}

/**
 * Redirect Error (for handling redirects as errors)
 */
export class RedirectError extends Error {
  constructor(
    public url: string,
    public statusCode: number = 302,
    message?: string
  ) {
    super(message || `Redirect to ${url}`);
    this.name = 'RedirectError';
    Object.setPrototypeOf(this, RedirectError.prototype);
  }

  isPermanent(): boolean {
    return this.statusCode === 301 || this.statusCode === 308;
  }

  isTemporary(): boolean {
    return this.statusCode === 302 || this.statusCode === 307;
  }
}

/**
 * Validation Error (400)
 */
export class ValidationError extends ApiError {
  constructor(
    message: string = 'Validation failed',
    public fieldErrors?: Record<string, string[]>
  ) {
    super(message, 400, 'VALIDATION_ERROR', fieldErrors);
    this.name = 'ValidationError';
    Object.setPrototypeOf(this, ValidationError.prototype);
  }

  getFieldError(field: string): string | null {
    if (!this.fieldErrors || !this.fieldErrors[field]) {
      return null;
    }
    return this.fieldErrors[field][0] || null;
  }

  hasFieldError(field: string): boolean {
    return !!this.fieldErrors && !!this.fieldErrors[field];
  }
}

/**
 * Authentication Error (401)
 */
export class AuthenticationError extends ApiError {
  constructor(message: string = 'Authentication required') {
    super(message, 401, 'AUTHENTICATION_ERROR');
    this.name = 'AuthenticationError';
    Object.setPrototypeOf(this, AuthenticationError.prototype);
  }
}

/**
 * Authorization Error (403)
 */
export class AuthorizationError extends ApiError {
  constructor(message: string = 'Access denied') {
    super(message, 403, 'AUTHORIZATION_ERROR');
    this.name = 'AuthorizationError';
    Object.setPrototypeOf(this, AuthorizationError.prototype);
  }
}

/**
 * Rate Limit Error (429)
 */
export class RateLimitError extends ApiError {
  constructor(
    message: string = 'Too many requests',
    public retryAfter?: number
  ) {
    super(message, 429, 'RATE_LIMIT_ERROR');
    this.name = 'RateLimitError';
    Object.setPrototypeOf(this, RateLimitError.prototype);
  }

  getRetryAfterSeconds(): number | null {
    return this.retryAfter || null;
  }
}

/**
 * Server Error (500+)
 */
export class ServerError extends ApiError {
  constructor(
    message: string = 'Server error occurred',
    statusCode: number = 500
  ) {
    super(message, statusCode, 'SERVER_ERROR');
    this.name = 'ServerError';
    Object.setPrototypeOf(this, ServerError.prototype);
  }
}

/**
 * Network Error
 */
export class NetworkError extends Error {
  constructor(message: string = 'Network request failed') {
    super(message);
    this.name = 'NetworkError';
    Object.setPrototypeOf(this, NetworkError.prototype);
  }
}

/**
 * Timeout Error
 */
export class TimeoutError extends Error {
  constructor(
    message: string = 'Request timeout',
    public timeoutMs?: number
  ) {
    super(message);
    this.name = 'TimeoutError';
    Object.setPrototypeOf(this, TimeoutError.prototype);
  }
}

/**
 * Parse error from API response
 */
export async function parseApiError(response: Response): Promise<ApiError> {
  let errorData: {
    message?: string;
    code?: string;
    errors?: Record<string, string[]>;
  } = {};

  try {
    errorData = await response.json();
  } catch {
    // Failed to parse error response
  }

  const message = errorData.message || `HTTP ${response.status}`;
  const code = errorData.code;
  const errors = errorData.errors;

  // Create specific error based on status code
  switch (response.status) {
    case 400:
      return new ValidationError(message, errors);
    case 401:
      return new AuthenticationError(message);
    case 403:
      return new AuthorizationError(message);
    case 404:
      return new NotFoundError(message);
    case 429:
      const retryAfter = response.headers.get('Retry-After');
      return new RateLimitError(
        message,
        retryAfter ? parseInt(retryAfter, 10) : undefined
      );
    default:
      if (response.status >= 500) {
        return new ServerError(message, response.status);
      }
      return new ApiError(message, response.status, code, errors);
  }
}

/**
 * Check if error is API error
 */
export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError;
}

/**
 * Check if error is specific type
 */
export function isNotFoundError(error: unknown): error is NotFoundError {
  return error instanceof NotFoundError;
}

export function isRedirectError(error: unknown): error is RedirectError {
  return error instanceof RedirectError;
}

export function isValidationError(error: unknown): error is ValidationError {
  return error instanceof ValidationError;
}

export function isAuthenticationError(
  error: unknown
): error is AuthenticationError {
  return error instanceof AuthenticationError;
}

export function isAuthorizationError(
  error: unknown
): error is AuthorizationError {
  return error instanceof AuthorizationError;
}

export function isRateLimitError(error: unknown): error is RateLimitError {
  return error instanceof RateLimitError;
}

export function isServerError(error: unknown): error is ServerError {
  return error instanceof ServerError;
}

export function isNetworkError(error: unknown): error is NetworkError {
  return error instanceof NetworkError;
}

export function isTimeoutError(error: unknown): error is TimeoutError {
  return error instanceof TimeoutError;
}

/**
 * Get user-friendly error message
 */
export function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    return error.getUserMessage();
  }

  if (error instanceof Error) {
    return error.message;
  }

  return 'An unexpected error occurred';
}

/**
 * Log error (development only)
 */
export function logError(error: unknown, context?: string): void {
  if (process.env.NODE_ENV === 'development') {
    console.error(`[Error${context ? ` - ${context}` : ''}]`, error);
  }
}

/**
 * Handle error with fallback
 */
export function handleError<T>(
  error: unknown,
  fallback: T,
  context?: string
): T {
  logError(error, context);
  return fallback;
}
