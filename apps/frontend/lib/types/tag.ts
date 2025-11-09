/**
 * Tag type definitions aligned with deschide_backend Tag entity
 *
 * Tags provide SEO keywords and categorization for articles.
 * They are translatable (ro/en/ru) and support usage count tracking.
 */

/**
 * Tag entity
 * Represents a keyword/tag that can be assigned to articles
 */
export interface Tag {
  '@id': string;
  '@type': string;
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  usageCount: number;
  createdAt: string;
  updatedAt: string;
  locale?: string;
  translatableLocale?: string | null;
}

/**
 * Hydra Collection response for tags
 */
export interface TagCollection {
  '@context': string;
  '@id': string;
  '@type': string;
  'hydra:member': Tag[];
  'hydra:totalItems': number;
  'hydra:view'?: {
    '@id': string;
    '@type': string;
    'hydra:first'?: string;
    'hydra:last'?: string;
    'hydra:next'?: string;
    'hydra:previous'?: string;
  };
}

/**
 * Tag statistics from backend
 */
export interface TagStatistics {
  totalTags: number;
  totalUsages: number;
  averageUsage: number;
  tagsInUse: number;
  unusedTags: number;
  mostUsedTag: Tag | null;
}

/**
 * Parameters for fetching tags
 */
export interface GetTagsParams {
  page?: number;
  itemsPerPage?: number;
  name?: string;
  slug?: string;
  minUsageCount?: number;
  order?: {
    usageCount?: 'DESC' | 'ASC';
    name?: 'ASC' | 'DESC';
    createdAt?: 'DESC' | 'ASC';
  };
}

/**
 * Related tags response (co-occurring tags)
 */
export interface RelatedTagsResponse {
  '@context': string;
  '@type': string;
  'hydra:member': Tag[];
  'hydra:totalItems': number;
}
