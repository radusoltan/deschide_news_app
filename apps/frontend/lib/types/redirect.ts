/**
 * Redirect-related TypeScript interfaces
 * Based on Sprint 1 backend API contracts
 */

import { Locale } from './slug';

export type RedirectType = 'article' | 'category' | 'author' | 'manual';
export type RedirectStatusCode = 301 | 302 | 307 | 308;

// ============================================================================
// Redirect Entity Types
// ============================================================================

export interface Redirect {
  id: number;
  old_url: string;
  new_url: string;
  locale: Locale;
  http_status_code: RedirectStatusCode;
  type: RedirectType;
  entity_id: number | null;
  created_at: string;
  hit_count: number;
  last_accessed_at: string | null;
}

// ============================================================================
// Redirect Statistics Types
// ============================================================================

export interface RedirectStatistics {
  total_redirects: number;
  by_status_code: Record<string, number>;
  by_type: Record<string, number>;
  by_locale: Record<string, number>;
  most_used: Array<{
    old_url: string;
    new_url: string;
    hit_count: number;
    type: RedirectType;
  }>;
  recent: Array<{
    old_url: string;
    new_url: string;
    created_at: string;
    type: RedirectType;
  }>;
  unused_count: number;
  chains_detected: number;
}

// ============================================================================
// Entity Redirects Types
// ============================================================================

export interface EntityRedirectsRequest {
  entity_type: RedirectType;
  entity_id: number;
}

export interface EntityRedirectsResponse {
  entity_type: RedirectType;
  entity_id: number;
  redirects: Redirect[];
  count: number;
}

// ============================================================================
// Health Check Types
// ============================================================================

export interface RedirectHealthResponse {
  status: 'healthy' | 'warning' | 'error';
  issues: Array<{
    type: 'circular' | 'chain' | 'broken' | 'outdated';
    severity: 'low' | 'medium' | 'high';
    description: string;
    affected_redirects: number;
    details?: unknown;
  }>;
  statistics: {
    total_redirects: number;
    circular_redirects: number;
    long_chains: number;
    broken_redirects: number;
    outdated_redirects: number;
  };
  recommendations: string[];
}

// ============================================================================
// Redirect Chains Types
// ============================================================================

export interface FindChainsRequest {
  min_length?: number;
  limit?: number;
}

export interface RedirectChain {
  chain: Array<{
    from: string;
    to: string;
    redirect_id: number;
    status_code: RedirectStatusCode;
  }>;
  length: number;
  is_circular: boolean;
  total_hits: number;
  oldest_created_at: string;
}

export interface FindChainsResponse {
  chains: RedirectChain[];
  total_chains: number;
  circular_chains: number;
  max_chain_length: number;
}

// ============================================================================
// API Response Wrapper Types
// ============================================================================

export interface RedirectApiResponse<T> {
  success: boolean;
  data?: T;
  error?: string;
  timestamp?: string;
}
