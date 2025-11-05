/**
 * Error Handler for Middleware
 * Handles errors that occur during middleware execution
 */

import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

/**
 * Error types that can occur in middleware
 */
export enum MiddlewareErrorType {
  REDIRECT_CHECK_FAILED = 'REDIRECT_CHECK_FAILED',
  REDIRECT_TIMEOUT = 'REDIRECT_TIMEOUT',
  REDIRECT_LOOP = 'REDIRECT_LOOP',
  LOCALE_DETECTION_FAILED = 'LOCALE_DETECTION_FAILED',
  UNKNOWN = 'UNKNOWN',
}

/**
 * Middleware error class
 */
export class MiddlewareError extends Error {
  constructor(
    public type: MiddlewareErrorType,
    message: string,
    public originalError?: unknown
  ) {
    super(message);
    this.name = 'MiddlewareError';
  }
}

/**
 * Handle middleware errors gracefully
 * Returns a response that allows the request to continue
 */
export function handleMiddlewareError(
  error: unknown,
  request: NextRequest
): NextResponse {
  const errorInfo = extractErrorInfo(error);

  // Log error in development
  if (process.env.NODE_ENV === 'development') {
    console.error('[Middleware Error]', {
      type: errorInfo.type,
      message: errorInfo.message,
      path: request.nextUrl.pathname,
      error: errorInfo.originalError,
    });
  }

  // For production, log to monitoring service (if configured)
  // TODO: Add monitoring service integration (e.g., Sentry)

  // Continue with the request - fail gracefully
  return NextResponse.next();
}

/**
 * Extract error information
 */
function extractErrorInfo(error: unknown): {
  type: MiddlewareErrorType;
  message: string;
  originalError?: unknown;
} {
  if (error instanceof MiddlewareError) {
    return {
      type: error.type,
      message: error.message,
      originalError: error.originalError,
    };
  }

  if (error instanceof Error) {
    // Check for timeout errors
    if (error.name === 'AbortError') {
      return {
        type: MiddlewareErrorType.REDIRECT_TIMEOUT,
        message: 'Redirect check timed out',
        originalError: error,
      };
    }

    // Check for network errors
    if (error.message.includes('fetch')) {
      return {
        type: MiddlewareErrorType.REDIRECT_CHECK_FAILED,
        message: 'Failed to check redirect',
        originalError: error,
      };
    }

    return {
      type: MiddlewareErrorType.UNKNOWN,
      message: error.message,
      originalError: error,
    };
  }

  return {
    type: MiddlewareErrorType.UNKNOWN,
    message: 'Unknown error occurred',
    originalError: error,
  };
}

/**
 * Create error response with custom headers (for debugging)
 */
export function createErrorResponse(
  request: NextRequest,
  errorType: MiddlewareErrorType,
  message: string
): NextResponse {
  const response = NextResponse.next();

  // Add custom headers in development for debugging
  if (process.env.NODE_ENV === 'development') {
    response.headers.set('X-Middleware-Error', errorType);
    response.headers.set('X-Middleware-Error-Message', message);
  }

  return response;
}

/**
 * Wrap async middleware function with error handling
 */
export function withErrorHandling<T>(
  fn: (request: NextRequest) => Promise<T>
): (request: NextRequest) => Promise<T | NextResponse> {
  return async (request: NextRequest) => {
    try {
      return await fn(request);
    } catch (error) {
      return handleMiddlewareError(error, request);
    }
  };
}

/**
 * Check if error is retryable
 */
export function isRetryableError(error: unknown): boolean {
  if (error instanceof MiddlewareError) {
    return (
      error.type === MiddlewareErrorType.REDIRECT_TIMEOUT ||
      error.type === MiddlewareErrorType.REDIRECT_CHECK_FAILED
    );
  }
  return false;
}

/**
 * Create timeout promise for race conditions
 */
export function createTimeoutPromise<T>(
  promise: Promise<T>,
  timeoutMs: number,
  errorMessage: string = 'Operation timed out'
): Promise<T> {
  return Promise.race([
    promise,
    new Promise<never>((_, reject) =>
      setTimeout(
        () =>
          reject(
            new MiddlewareError(
              MiddlewareErrorType.REDIRECT_TIMEOUT,
              errorMessage
            )
          ),
        timeoutMs
      )
    ),
  ]);
}
