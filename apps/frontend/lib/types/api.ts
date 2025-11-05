/**
 * General API-related TypeScript interfaces
 */

// ============================================================================
// Common API Response Types
// ============================================================================

export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: string;
  timestamp?: string;
}

export interface PaginatedResponse<T> {
  'hydra:member': T[];
  'hydra:totalItems': number;
  'hydra:view'?: {
    '@id': string;
    '@type': string;
    'hydra:first'?: string;
    'hydra:last'?: string;
    'hydra:previous'?: string;
    'hydra:next'?: string;
  };
}

// ============================================================================
// Error Types
// ============================================================================

export interface ApiError {
  code: number;
  message: string;
  errors?: Record<string, string[]>;
  violations?: Array<{
    propertyPath: string;
    message: string;
    code?: string;
  }>;
}

export interface ValidationError {
  field: string;
  message: string;
  code?: string;
}

// ============================================================================
// Request Options
// ============================================================================

export interface RequestOptions {
  cache?: RequestCache;
  revalidate?: number;
  tags?: string[];
}

export interface PaginationOptions {
  page?: number;
  itemsPerPage?: number;
}

export interface SortOptions {
  field: string;
  order: 'asc' | 'desc';
}

export interface FilterOptions {
  [key: string]: string | number | boolean | string[] | number[];
}

// ============================================================================
// Locale & Internationalization
// ============================================================================

export type Locale = 'ro' | 'en' | 'ru';

export interface LocalizedContent {
  locale: Locale;
  [key: string]: unknown;
}

export interface TranslationInfo {
  locale: Locale;
  title: string;
  slug: string;
  [key: string]: unknown;
}

// ============================================================================
// Metadata Types
// ============================================================================

export interface Timestamps {
  created_at: string;
  updated_at: string;
}

export interface PublishInfo {
  published_at: string | null;
  published_by?: string;
}

export interface SEOMetadata {
  title?: string;
  description?: string;
  keywords?: string[];
  og_image?: string;
  canonical_url?: string;
}
