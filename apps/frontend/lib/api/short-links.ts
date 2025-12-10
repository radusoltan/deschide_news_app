/**
 * Short Links API Client
 * Handles all API calls for short link management
 */

import { apiRequest } from './client';

// ============================================================================
// Types
// ============================================================================

export interface ShortLink {
  id: number;
  code: string;
  originalUrl: string;
  title?: string;
  clickCount: number;
  createdAt: string;
  shortUrl: string;
  article?: {
    id: number;
    title: string;
  } | null;
}

export interface ShortLinkStats {
  shortLink: ShortLink;
  clicksPerDay: Array<{
    date: string;
    clicks: number;
  }>;
  topReferrers: Array<{
    referrer: string;
    count: number;
  }>;
  deviceTypes: Array<{
    deviceType: string;
    count: number;
  }>;
  countries: Array<{
    countryCode: string;
    count: number;
  }>;
}

export interface ShortLinkListResponse {
  'hydra:member': ShortLink[];
  'hydra:totalItems': number;
  'hydra:view'?: {
    'hydra:first'?: string;
    'hydra:last'?: string;
    'hydra:previous'?: string;
    'hydra:next'?: string;
  };
}

export interface CreateShortLinkData {
  originalUrl: string;
  code?: string;
  title?: string;
}

export interface GetShortLinksOptions {
  page?: number;
  itemsPerPage?: number;
  code?: string;
  title?: string;
  token?: string;
}

// ============================================================================
// API Functions
// ============================================================================

/**
 * Get list of short links with pagination and filtering
 */
export async function getShortLinks(
  options: GetShortLinksOptions = {}
): Promise<ShortLinkListResponse> {
  const {
    page = 1,
    itemsPerPage = 30,
    code,
    title,
    token,
  } = options;

  // Build query parameters
  const params = new URLSearchParams({
    page: page.toString(),
    itemsPerPage: itemsPerPage.toString(),
  });

  if (code) {
    params.append('code', code);
  }

  if (title) {
    params.append('title', title);
  }

  const endpoint = `/api/short_links?${params.toString()}`;

  return apiRequest<ShortLinkListResponse>(endpoint, {
    method: 'GET',
    token,
  });
}

/**
 * Create a new short link
 */
export async function createShortLink(
  data: CreateShortLinkData,
  token: string
): Promise<ShortLink> {
  return apiRequest<ShortLink>('/api/short_links', {
    method: 'POST',
    body: JSON.stringify(data),
    token,
  });
}

/**
 * Delete a short link
 */
export async function deleteShortLink(
  id: number,
  token: string
): Promise<void> {
  return apiRequest<void>(`/api/short_links/${id}`, {
    method: 'DELETE',
    token,
  });
}

/**
 * Get statistics for a short link
 */
export async function getShortLinkStats(
  id: number,
  token: string
): Promise<ShortLinkStats> {
  return apiRequest<ShortLinkStats>(`/api/short_links/${id}/stats`, {
    method: 'GET',
    token,
  });
}

/**
 * Get a single short link by ID
 */
export async function getShortLink(
  id: number,
  token: string
): Promise<ShortLink> {
  return apiRequest<ShortLink>(`/api/short_links/${id}`, {
    method: 'GET',
    token,
  });
}
