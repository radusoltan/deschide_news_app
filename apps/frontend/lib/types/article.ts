/**
 * Article type definitions aligned with deschide_backend Article entity
 */

import { ArticleImage } from './image';
import { Tag } from './tag';

export type ArticleStatus = 'draft' | 'published' | 'archived';

export interface Category {
  '@id': string;
  '@type': string;
  id: number;
  title: string;
  slug: string;
  description?: string;
  status?: string;
  onFrontPage?: boolean;
  inMenu?: boolean;
  inFooterMenu?: boolean;
  articleCount?: number;
}

export interface Article {
  '@id': string;
  '@type': string;
  id: number;
  title: string;
  slug: string;
  lead: string | null;
  content?: string;
  category: Category | string; // Full Category object or IRI
  authors: string[]; // IRIs to Authors
  articleImages: ArticleImage[];
  relatedArticles?: (Article | string)[]; // Full Article objects or IRIs
  tags?: (Tag | string)[]; // Full Tag objects or IRIs
  status: ArticleStatus;
  viewCount: number;
  createdAt: string;
  updatedAt: string;
  publishedAt: string | null;
  archivedAt?: string | null; // When article was archived
  archiveReason?: string; // Reason for archival (e.g., 'outdated', 'inaccurate', 'manual')
  locale?: string;
  translatableLocale?: string | null;
}

export interface ArticleListResponse {
  '@context': string;
  '@id': string;
  '@type': string;
  member: Article[];
  totalItems: number;
  view?: {
    '@id': string;
    '@type': string;
    first?: string;
    last?: string;
    previous?: string;
    next?: string;
  };
  search?: any;
}

/**
 * ImportantArticlesList type definitions
 */

export interface ImportantArticle {
  '@id': string;
  '@type': string;
  id: number;
  article: Article;
  position: number;
  createdAt: string;
}

export interface ImportantArticlesListResponse {
  '@context': string;
  '@id': string;
  '@type': string;
  totalItems: number;
  member: ImportantArticle[];
}
