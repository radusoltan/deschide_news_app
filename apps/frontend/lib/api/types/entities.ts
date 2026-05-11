/**
 * Unified entity type barrel for all backend API entities.
 *
 * Re-exports canonical types from lib/types/ and adds missing entity
 * interfaces for User and AppSetting.
 */

// Core entities
export type {
  Article,
  ArticleListResponse,
  ArticleStatus,
  ArticleBadge,
  Category,
  Author,
  ImportantArticle,
  ImportantArticlesListResponse,
} from '@/lib/types/article';

export type { ArticleImage, ArticleImageListResponse } from '@/lib/types/image';
export type { Tag } from '@/lib/types/tag';
export type { Topic, TopicTreeNode, TopicSuggestion } from '@/lib/types/topic';

// API infrastructure types
export type {
  PaginatedResponse,
  ApiError as ApiErrorResponse,
  ValidationError,
  Locale,
  PaginationOptions,
  SortOptions,
  FilterOptions,
} from '@/lib/types/api';

// Auth types
export type { AuthTokens, LoginCredentials } from '@/lib/api-client';

// --- Additional entities not yet defined elsewhere ---

export interface User {
  '@id': string;
  '@type': string;
  id: number;
  username: string;
  email: string;
  roles: string[];
  firstName?: string;
  lastName?: string;
  isActive: boolean;
  createdAt: string;
}

export interface AppSetting {
  '@id': string;
  '@type': string;
  id: number;
  key: string;
  value: string;
  type: 'string' | 'boolean' | 'integer' | 'json';
  description?: string;
}

/**
 * Generic Hydra collection response (JSON-LD).
 */
export interface HydraCollection<T> {
  '@context': string;
  '@id': string;
  '@type': string;
  'totalItems': number;
  'member': T[];
  'view'?: {
    '@id': string;
    '@type': string;
    first?: string;
    last?: string;
    previous?: string;
    next?: string;
  };
}
