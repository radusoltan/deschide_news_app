/**
 * Article type definitions aligned with deschide_backend Article entity
 */

import { ArticleImage } from './image';
import { Tag } from './tag';

export type ArticleStatus = 'draft' | 'published' | 'archived';

// Article badge types for special articles (breaking news, alerts, flash news)
export type ArticleBadge = 'breaking' | 'alert' | 'flash';

export interface Category {
  '@id': string;
  '@type': string;
  id: number;
  title: string;
  slug: string;
  description?: string;
  status?: string;
  onFrontPage?: boolean;
  frontPagePosition?: number;
  frontPageLayout?: string | null;
  inMenu?: boolean;
  inFooterMenu?: boolean;
  articleCount?: number;
  translatedSlugs?: { ro?: string; en?: string; ru?: string };
}

export interface Author {
  '@id': string;
  '@type': string;
  id: number;
  firstName: string;
  lastName: string;
  fullName: string;
  slug: string;
  initials?: string;
  email?: string;
  type?: string;
  status?: string;
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
  authors: (Author | string)[]; // Full Author objects or IRIs
  articleImages: ArticleImage[];
  relatedArticles?: (Article | string)[]; // Full Article objects or IRIs
  tags?: (Tag | string)[]; // Full Tag objects or IRIs
  status: ArticleStatus;
  badge?: ArticleBadge | null; // Special article badge (breaking, alert, flash)
  isFeatured?: boolean; // Featured article flag
  viewCount: number;
  createdAt: string;
  updatedAt: string;
  publishedAt: string | null;
  publishAt?: string | null; // Scheduled publication date
  archivedAt?: string | null; // When article was archived
  archiveReason?: string; // Reason for archival (e.g., 'outdated', 'inaccurate', 'manual')
  locale?: string;
  translatableLocale?: string | null;
  metaTitle?: string | null;
  metaDescription?: string | null;
  publishedLocales?: string[];
  translatedSlugs?: { ro?: string; en?: string; ru?: string };
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
