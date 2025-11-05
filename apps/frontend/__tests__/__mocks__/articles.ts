/**
 * Mock Article Data for Testing
 */

import type { Article } from '@/lib/types/article';

export const mockArticle: Article = {
  '@id': '/api/articles/1',
  '@type': 'Article',
  id: 1,
  title: 'Test Article Title',
  slug: 'test-article-title',
  lead: 'This is a test article lead paragraph for testing purposes.',
  content: '<p>This is the full article content with <strong>HTML</strong> formatting.</p>',
  status: 'published',
  publishedAt: '2025-10-31T10:00:00+00:00',
  updatedAt: '2025-10-31T12:00:00+00:00',
  createdAt: '2025-10-30T15:00:00+00:00',
  authors: ['/api/authors/1'],
  category: {
    '@id': '/api/categories/1',
    '@type': 'Category',
    id: 1,
    title: 'Politics',
    slug: 'politics',
    description: 'Political news and analysis',
  },
  articleImages: [
    {
      '@id': '/api/article_images/1',
      '@type': 'ArticleImage',
      id: 1,
      article: '/api/articles/1',
      image: {
        '@id': '/api/images/1',
        '@type': 'Image',
        id: 1,
        filename: 'test-image.jpg',
        originalFilename: 'test-image.jpg',
        mimeType: 'image/jpeg',
        size: 102400,
        width: 1920,
        height: 1080,
        alt: 'Test image alt text',
        caption: 'Test image caption',
        description: 'Test image description',
        uploadedAt: '2025-10-30T14:00:00+00:00',
        updatedAt: null,
        aspectRatio: 1.78,
        formattedSize: '100 KB',
        file: null,
        contentUrl: null,
      },
      position: 0,
      isFeatured: true,
    },
  ],
  isFeatured: false,
  isBreaking: false,
  badge: null,
  views: 0,
};

export const mockArticles: Article[] = [
  mockArticle,
  {
    ...mockArticle,
    id: 2,
    '@id': '/api/articles/2',
    title: 'Second Test Article',
    slug: 'second-test-article',
    lead: 'This is the second test article.',
    publishedAt: '2025-10-31T09:00:00+00:00',
  },
  {
    ...mockArticle,
    id: 3,
    '@id': '/api/articles/3',
    title: 'Third Test Article',
    slug: 'third-test-article',
    lead: 'This is the third test article.',
    publishedAt: '2025-10-31T08:00:00+00:00',
    category: {
      '@id': '/api/categories/2',
      '@type': 'Category',
      id: 2,
      title: 'Economy',
      slug: 'economy',
      description: 'Economic news',
    },
  },
];

export const mockArticleListResponse = {
  '@context': '/api/contexts/Article',
  '@id': '/api/articles',
  '@type': 'hydra:Collection',
  'hydra:member': mockArticles,
  'hydra:totalItems': 3,
  'hydra:view': {
    '@id': '/api/articles?page=1',
    '@type': 'hydra:PartialCollectionView',
    'hydra:first': '/api/articles?page=1',
    'hydra:last': '/api/articles?page=1',
  },
};
