/**
 * Redirect Utilities
 * Helper functions for redirect handling
 */

/**
 * Normalize URL for comparison
 * Removes trailing slashes and converts to lowercase
 */
export function normalizeUrl(url: string): string {
  return url.replace(/\/$/, '').toLowerCase();
}

/**
 * Check if URL is absolute
 */
export function isAbsoluteUrl(url: string): boolean {
  try {
    new URL(url);
    return true;
  } catch {
    return false;
  }
}

/**
 * Check if URL is internal (same domain)
 */
export function isInternalUrl(url: string, baseUrl: string): boolean {
  if (!isAbsoluteUrl(url)) {
    return true; // Relative URLs are internal
  }

  try {
    const urlObj = new URL(url);
    const baseUrlObj = new URL(baseUrl);
    return urlObj.hostname === baseUrlObj.hostname;
  } catch {
    return false;
  }
}

/**
 * Extract path from URL
 * Handles both absolute and relative URLs
 */
export function extractPath(url: string): string {
  if (!isAbsoluteUrl(url)) {
    return url;
  }

  try {
    const urlObj = new URL(url);
    return urlObj.pathname + urlObj.search + urlObj.hash;
  } catch {
    return url;
  }
}

/**
 * Build absolute URL from path
 */
export function buildAbsoluteUrl(path: string, baseUrl: string): string {
  if (isAbsoluteUrl(path)) {
    return path;
  }

  const cleanBaseUrl = baseUrl.replace(/\/$/, '');
  const cleanPath = path.startsWith('/') ? path : `/${path}`;
  return `${cleanBaseUrl}${cleanPath}`;
}

/**
 * Check if path matches pattern
 * Supports wildcards: /admin/* matches /admin/articles
 */
export function matchesPattern(path: string, pattern: string): boolean {
  // Exact match
  if (path === pattern) {
    return true;
  }

  // Wildcard match
  if (pattern.endsWith('/*')) {
    const prefix = pattern.slice(0, -2);
    return path.startsWith(prefix);
  }

  // Regex pattern
  if (pattern.startsWith('^') && pattern.endsWith('$')) {
    try {
      const regex = new RegExp(pattern);
      return regex.test(path);
    } catch {
      return false;
    }
  }

  return false;
}

/**
 * Check if path should be excluded from redirect checking
 */
export function shouldSkipRedirectCheck(path: string): boolean {
  const skipPatterns = [
    '/api/*',
    '/_next/*',
    '/static/*',
    '/favicon.ico',
    '/robots.txt',
    '/sitemap.xml',
    '/admin/*',
    '/login',
  ];

  return skipPatterns.some((pattern) => matchesPattern(path, pattern));
}

/**
 * Parse query string from URL
 */
export function parseQueryString(url: string): Record<string, string> {
  const query: Record<string, string> = {};
  const queryStart = url.indexOf('?');

  if (queryStart === -1) {
    return query;
  }

  const queryString = url.slice(queryStart + 1);
  const params = queryString.split('&');

  for (const param of params) {
    const [key, value] = param.split('=');
    if (key) {
      query[decodeURIComponent(key)] = decodeURIComponent(value || '');
    }
  }

  return query;
}

/**
 * Build query string from object
 */
export function buildQueryString(params: Record<string, string>): string {
  const entries = Object.entries(params).filter(([_, value]) => value !== '');

  if (entries.length === 0) {
    return '';
  }

  const queryString = entries
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
    .join('&');

  return `?${queryString}`;
}

/**
 * Preserve query parameters during redirect
 */
export function preserveQueryParams(
  originalUrl: string,
  redirectUrl: string
): string {
  const originalParams = parseQueryString(originalUrl);
  const redirectParams = parseQueryString(redirectUrl);

  // Merge params (redirect params take precedence)
  const mergedParams = { ...originalParams, ...redirectParams };

  // Remove query string from redirect URL
  const baseRedirectUrl = redirectUrl.split('?')[0];

  // Build new URL with merged params
  const queryString = buildQueryString(mergedParams);
  return baseRedirectUrl + queryString;
}

/**
 * Get redirect status code display name
 */
export function getRedirectStatusName(statusCode: number): string {
  switch (statusCode) {
    case 301:
      return 'Moved Permanently';
    case 302:
      return 'Found (Temporary Redirect)';
    case 307:
      return 'Temporary Redirect';
    case 308:
      return 'Permanent Redirect';
    default:
      return 'Redirect';
  }
}

/**
 * Check if status code is permanent redirect
 */
export function isPermanentRedirect(statusCode: number): boolean {
  return statusCode === 301 || statusCode === 308;
}

/**
 * Check if status code is temporary redirect
 */
export function isTemporaryRedirect(statusCode: number): boolean {
  return statusCode === 302 || statusCode === 307;
}
