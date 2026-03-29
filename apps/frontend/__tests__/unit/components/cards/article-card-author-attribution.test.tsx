/**
 * ArticleCard AuthorAttribution Tests
 *
 * Tests the author attribution rendering in the root ArticleCard component
 * (components/ArticleCard.tsx) based on author type.
 *
 * - journalist  -> renders "De [Name]"
 * - agency      -> renders "Sursa: [Name]"
 * - press_office -> renders "Comunicat: [Name]"
 */

import { render, screen } from '@testing-library/react';
import ArticleCard from '@/components/ArticleCard';
import type { Article, ArticleAuthor } from '@/lib/types/article';

// Mock next/link to avoid strict href validation in Next.js 16
jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href, ...props }: any) => (
    <a href={href || '#'} {...props}>{children}</a>
  ),
}));

// Mock the API utility functions
jest.mock('@/lib/api/important-articles', () => ({
  getFeaturedImage: jest.fn(() => null),
  getThumbnailByProfile: jest.fn(() => null),
  buildImageUrl: jest.fn((path: string) => `http://localhost:8082/uploads/${path}`),
}));

// Mock url-builder
jest.mock('@/lib/utils/url-builder', () => ({
  buildArticleUrl: jest.fn(() => '/ro/test/test-article'),
  buildCategoryUrl: jest.fn(() => '/ro/test'),
  getCategorySlug: jest.fn(() => 'test'),
}));

// Mock ViewCountBadge
jest.mock('@/components/public/ViewCountBadge', () => ({
  ViewCountBadge: () => null,
}));

// Mock TagList
jest.mock('@/components/tags', () => ({
  TagList: () => null,
}));

function createArticleWithAuthor(author: ArticleAuthor): Article {
  return {
    '@id': '/api/articles/1',
    '@type': 'Article',
    id: 1,
    title: 'Test Article',
    slug: 'test-article',
    lead: 'Test lead',
    content: '<p>Content</p>',
    status: 'published',
    publishedAt: '2025-10-31T10:00:00+00:00',
    updatedAt: '2025-10-31T12:00:00+00:00',
    createdAt: '2025-10-30T15:00:00+00:00',
    viewCount: 0,
    authors: [author],
    category: {
      '@id': '/api/categories/1',
      '@type': 'Category',
      id: 1,
      title: 'Test',
      slug: 'test',
    },
    articleImages: [],
  };
}

function createAuthor(overrides: Partial<ArticleAuthor> = {}): ArticleAuthor {
  return {
    id: 1,
    firstName: 'Ion',
    lastName: 'Popescu',
    slug: 'ion-popescu',
    fullName: 'Ion Popescu',
    type: 'journalist',
    ...overrides,
  };
}

describe('ArticleCard AuthorAttribution', () => {
  describe('Journalist author type', () => {
    it('renders "De [Name]" for journalist author', () => {
      const author = createAuthor({ type: 'journalist', fullName: 'Ion Popescu' });
      const article = createArticleWithAuthor(author);

      render(<ArticleCard article={article} locale="ro" />);

      expect(screen.getByText('De Ion Popescu')).toBeInTheDocument();
    });

    it('renders "De [Name]" when author type is undefined (defaults to journalist)', () => {
      const author = createAuthor({ type: undefined, fullName: 'Maria Ionescu' });
      const article = createArticleWithAuthor(author);

      render(<ArticleCard article={article} locale="ro" />);

      expect(screen.getByText('De Maria Ionescu')).toBeInTheDocument();
    });
  });

  describe('Agency author type', () => {
    it('renders "Sursa: [Name]" for agency author', () => {
      const author = createAuthor({
        type: 'agency',
        fullName: 'IPN Info-Prim Neo',
      });
      const article = createArticleWithAuthor(author);

      render(<ArticleCard article={article} locale="ro" />);

      expect(screen.getByText('Sursa: IPN Info-Prim Neo')).toBeInTheDocument();
    });
  });

  describe('Press office author type', () => {
    it('renders "Comunicat: [Name]" for press_office author', () => {
      const author = createAuthor({
        type: 'press_office',
        fullName: 'Guvernul RM',
      });
      const article = createArticleWithAuthor(author);

      render(<ArticleCard article={article} locale="ro" />);

      expect(screen.getByText('Comunicat: Guvernul RM')).toBeInTheDocument();
    });
  });

  describe('No author', () => {
    it('does not render author attribution when authors is empty', () => {
      const article = createArticleWithAuthor(createAuthor());
      article.authors = [];

      render(<ArticleCard article={article} locale="ro" />);

      expect(screen.queryByText(/^De /)).not.toBeInTheDocument();
      expect(screen.queryByText(/^Sursa: /)).not.toBeInTheDocument();
      expect(screen.queryByText(/^Comunicat: /)).not.toBeInTheDocument();
    });

    it('does not render author attribution when authors contains only IRIs', () => {
      const article = createArticleWithAuthor(createAuthor());
      article.authors = ['/api/authors/1'];

      render(<ArticleCard article={article} locale="ro" />);

      expect(screen.queryByText(/^De /)).not.toBeInTheDocument();
      expect(screen.queryByText(/^Sursa: /)).not.toBeInTheDocument();
      expect(screen.queryByText(/^Comunicat: /)).not.toBeInTheDocument();
    });
  });

  describe('All three types render distinct prefixes', () => {
    it('each author type produces a unique attribution prefix', () => {
      const types = [
        { type: 'journalist' as const, prefix: 'De' },
        { type: 'agency' as const, prefix: 'Sursa:' },
        { type: 'press_office' as const, prefix: 'Comunicat:' },
      ];

      for (const { type, prefix } of types) {
        const author = createAuthor({ type, fullName: 'Test Author' });
        const article = createArticleWithAuthor(author);

        const { unmount } = render(<ArticleCard article={article} locale="ro" />);

        expect(screen.getByText(`${prefix} Test Author`)).toBeInTheDocument();

        unmount();
      }
    });
  });
});
