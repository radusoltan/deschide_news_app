/**
 * Slug-related TypeScript interfaces
 * Based on Sprint 1 backend API contracts
 */

import type { RedirectStatusCode } from './redirect';
import type { Locale } from './api';

// Re-export for convenience
export type { Locale };

export type SlugType = 'category' | 'article';

// ============================================================================
// Slug Lookup Types
// ============================================================================

export interface SlugLookupRequest {
  category_slug: string;
  article_slug: string;
  locale: Locale;
}

export interface SlugLookupResult {
  article_id: number;
  title: string;
  slug: string;
  category: {
    id: number;
    slug: string;
    title: string;
  };
  url: string;
  found_via: 'elasticsearch' | 'database';
}

export interface SlugRedirectInfo {
  old_url: string;
  new_url: string;
  status_code: RedirectStatusCode;
  type: string;
}

export interface SlugLookupResponse {
  success: boolean;
  data?: SlugLookupResult;
  redirect?: SlugRedirectInfo;
  error?: string;
}

// ============================================================================
// Slug Validation Types
// ============================================================================

export interface SlugValidationRequest {
  slug: string;
  type: SlugType;
  locale: Locale;
  exclude_id?: number;
}

export interface SlugValidationResponse {
  is_available: boolean;
  slug: string;
  conflicts?: Array<{
    id: number;
    type: SlugType;
    title: string;
    locale: Locale;
    url: string;
  }>;
}

// ============================================================================
// Redirect Check Types
// ============================================================================

export interface RedirectCheckRequest {
  url: string;
}

export interface RedirectChainItem {
  from: string;
  to: string;
  status_code: RedirectStatusCode;
  type: string;
  hit_count: number;
  created_at: string;
}

export interface RedirectCheckResponse {
  success: boolean;
  has_redirect: boolean;
  chain?: RedirectChainItem[];
  final_url?: string;
  chain_length?: number;
  warning?: string;
}

// ============================================================================
// Reserved Slugs Types
// ============================================================================

export interface ReservedSlugsResponse {
  slugs: string[];
  count: number;
}

export interface CheckReservedRequest {
  slug: string;
}

export interface CheckReservedResponse {
  is_reserved: boolean;
  slug: string;
  conflict?: string;
}

// ============================================================================
// Bulk Validation Types
// ============================================================================

export interface BulkValidationRequest {
  slugs: string[];
  type: SlugType;
  locale: Locale;
}

export interface BulkValidationItem {
  slug: string;
  is_available: boolean;
  conflicts?: Array<{
    id: number;
    type: SlugType;
    title: string;
  }>;
}

export interface BulkValidationResponse {
  results: BulkValidationItem[];
  total: number;
  available_count: number;
  unavailable_count: number;
}

// ============================================================================
// Slug Suggestions Types
// ============================================================================

export interface SlugSuggestionsRequest {
  title: string;
  type: SlugType;
  locale: Locale;
  max?: number;
}

export interface SlugSuggestionsResponse {
  suggestions: Array<{
    slug: string;
    is_available: boolean;
    similarity?: number;
  }>;
  base_slug: string;
}
