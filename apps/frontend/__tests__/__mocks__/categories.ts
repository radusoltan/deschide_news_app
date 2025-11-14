/**
 * Mock Category Data for Testing
 */

import type { Category } from '@/lib/types/article';

export const mockCategory: Category = {
  '@id': '/api/categories/1',
  '@type': 'Category',
  id: 1,
  title: 'Politics',
  slug: 'politics',
  description: 'Political news and analysis',
};

export const mockCategories: Category[] = [
  mockCategory,
  {
    '@id': '/api/categories/2',
    '@type': 'Category',
    id: 2,
    title: 'Economy',
    slug: 'economy',
    description: 'Economic news and financial updates',
  },
  {
    '@id': '/api/categories/3',
    '@type': 'Category',
    id: 3,
    title: 'Technology',
    slug: 'technology',
    description: 'Tech news and innovation',
  },
  {
    '@id': '/api/categories/4',
    '@type': 'Category',
    id: 4,
    title: 'Society',
    slug: 'society',
    description: 'Social issues and community news',
  },
];

export const mockCategoryListResponse = {
  '@context': '/api/contexts/Category',
  '@id': '/api/categories',
  '@type': 'hydra:Collection',
  'hydra:member': mockCategories,
  'hydra:totalItems': 4,
};
