/**
 * Topic type definitions aligned with deschide_backend Topic entity.
 *
 * Topics provide hierarchical thematic classification (Nested Set tree)
 * for articles. They are translatable (ro/en/ru).
 */

export interface Topic {
  '@id'?: string;
  '@type'?: string;
  id: number;
  title: string;
  slug: string;
  description?: string | null;
  lvl: number;
  position: number;
  isActive: boolean;
  parent?: { id: number; title: string } | null;
  children?: Topic[];
  createdAt?: string;
  updatedAt?: string;
  translatedSlugs?: {
    ro?: string;
    en?: string;
    ru?: string;
  };
}

export interface TopicTreeNode {
  id: number;
  title: string;
  slug: string;
  description?: string | null;
  lvl: number;
  position: number;
  isActive: boolean;
  children: TopicTreeNode[];
}

export interface TopicSuggestion {
  topic: {
    id: number;
    title: string;
    slug: string;
    path: string;
  };
  confidence: 'high' | 'medium' | 'low';
  reason: string;
}

export interface TopicFlatItem {
  id: number;
  title: string;
  slug: string;
  lvl: number;
  indent: string;
}
