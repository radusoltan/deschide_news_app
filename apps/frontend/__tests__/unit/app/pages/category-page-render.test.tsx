/**
 * Tests for [locale]/(public)/[categorySlug]/page.tsx — rendering branches
 * Covers: articles rendering, hero card, grid cards, pagination, empty state, sidebar widgets
 */

const mockNotFound = jest.fn();
jest.mock('next/navigation', () => ({
  notFound: (...args: any[]) => { mockNotFound(...args); throw new Error('NEXT_NOT_FOUND'); },
  redirect: jest.fn(),
}));

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href, ...props }: any) => <a href={href} {...props}>{children}</a>,
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt, ...props }: any) => <img src={src} alt={alt} />,
}));

jest.mock('@/lib/api/categories', () => ({
  fetchCategories: jest.fn(),
}));

jest.mock('@/lib/api/articles', () => ({
  fetchArticlesByCategory: jest.fn(),
}));

jest.mock('@/lib/api/statistics', () => ({
  getTrendingArticles: jest.fn(),
}));

jest.mock('@/lib/constants/reserved-slugs', () => ({
  isReservedSlug: jest.fn((slug: string) => slug === 'admin' || slug === 'api'),
}));

jest.mock('@/lib/seo/meta-tags', () => ({
  generateCategoryMetadata: jest.fn(() => ({
    title: 'Category | Deschide News',
  })),
}));

jest.mock('@/lib/api/important-articles', () => ({
  buildImageUrl: jest.fn((path: string) => `http://cdn/${path}`),
  getThumbnailByProfile: jest.fn(() => null),
  getFeaturedImage: jest.fn(() => null),
}));

jest.mock('@/lib/utils/url-builder', () => ({
  buildArticleUrl: jest.fn((article: any, locale: string) => `/${locale}/${article.slug}`),
  buildCategoryUrl: jest.fn((cat: any, locale: string) => `/${locale}/${cat.slug}`),
}));

jest.mock('@/components/cards/utils', () => ({
  getSectionColor: jest.fn(() => 'var(--color-accent)'),
  getCategorySlugFromArticle: jest.fn(() => 'politica'),
}));

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { fetchCategories } from '@/lib/api/categories';
import { fetchArticlesByCategory } from '@/lib/api/articles';
import { getTrendingArticles } from '@/lib/api/statistics';

const mockFetchCategories = fetchCategories as jest.Mock;
const mockFetchArticles = fetchArticlesByCategory as jest.Mock;
const mockGetTrending = getTrendingArticles as jest.Mock;

function makeArticle(id: number, overrides: any = {}) {
  return {
    id,
    title: `Article ${id}`,
    slug: `article-${id}`,
    lead: `Lead for article ${id}`,
    category: { title: 'Politica', slug: 'politica', id: 1 },
    articleImages: [],
    ...overrides,
  };
}

describe('CategoryPage rendering', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockFetchCategories.mockResolvedValue({
      member: [
        { id: 1, title: 'Politica', slug: 'politica', description: 'Political news' },
      ],
    });
    mockFetchArticles.mockResolvedValue({ member: [], totalItems: 0 });
    mockGetTrending.mockResolvedValue([]);
  });

  it('calls notFound for reserved slug "admin"', async () => {
    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    try {
      await mod.default({
        params: Promise.resolve({ locale: 'ro', categorySlug: 'admin' }),
        searchParams: Promise.resolve({}),
      });
    } catch (e: any) {
      expect(e.message).toBe('NEXT_NOT_FOUND');
    }
    expect(mockNotFound).toHaveBeenCalled();
  });

  it('calls notFound when category not found', async () => {
    mockFetchCategories.mockResolvedValue({ member: [] });

    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    try {
      await mod.default({
        params: Promise.resolve({ locale: 'ro', categorySlug: 'nonexistent' }),
        searchParams: Promise.resolve({}),
      });
    } catch (e: any) {
      expect(e.message).toBe('NEXT_NOT_FOUND');
    }
    expect(mockNotFound).toHaveBeenCalled();
  });

  it('renders empty state when no articles', async () => {
    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', categorySlug: 'politica' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('No articles found')).toBeInTheDocument();
    expect(screen.getByText('There are no articles in this category yet.')).toBeInTheDocument();
  });

  it('renders category title as h1', async () => {
    mockFetchArticles.mockResolvedValue({
      member: [makeArticle(1)],
      totalItems: 1,
    });

    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'ro', categorySlug: 'politica' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    const heading = screen.getByRole('heading', { level: 1 });
    expect(heading).toHaveTextContent('Politica');
  });

  it('renders hero card for first article', async () => {
    mockFetchArticles.mockResolvedValue({
      member: [makeArticle(1, { title: 'Hero Article' }), makeArticle(2)],
      totalItems: 2,
    });

    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'ro', categorySlug: 'politica' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Hero Article')).toBeInTheDocument();
  });

  it('renders grid cards for articles after the first', async () => {
    mockFetchArticles.mockResolvedValue({
      member: [makeArticle(1), makeArticle(2, { title: 'Grid Article 2' }), makeArticle(3, { title: 'Grid Article 3' })],
      totalItems: 3,
    });

    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'ro', categorySlug: 'politica' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Grid Article 2')).toBeInTheDocument();
    expect(screen.getByText('Grid Article 3')).toBeInTheDocument();
  });

  it('renders pagination when totalPages > 1', async () => {
    mockFetchArticles.mockResolvedValue({
      member: Array.from({ length: 10 }, (_, i) => makeArticle(i + 1)),
      totalItems: 25,
    });

    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', categorySlug: 'politica' }),
      searchParams: Promise.resolve({ page: '1' }),
    });
    render(<>{Component}</>);

    const nav = screen.getByRole('navigation', { name: 'Pagination' });
    expect(nav).toBeInTheDocument();
  });

  it('renders trending articles sidebar', async () => {
    mockFetchArticles.mockResolvedValue({ member: [makeArticle(1)], totalItems: 1 });
    mockGetTrending.mockResolvedValue([
      { id: 50, title: 'Trending News', slug: 'trending', category: { slug: 'politica' } },
    ]);

    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', categorySlug: 'politica' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Most Read')).toBeInTheDocument();
    expect(screen.getByText('Trending News')).toBeInTheDocument();
  });

  it('renders In Trend widget', async () => {
    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', categorySlug: 'politica' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Trending')).toBeInTheDocument();
  });

  it('renders ad placeholder', async () => {
    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', categorySlug: 'politica' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Advertisement')).toBeInTheDocument();
  });

  it('handles fetchCategories error gracefully', async () => {
    mockFetchCategories.mockRejectedValue(new Error('Network error'));

    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    try {
      await mod.default({
        params: Promise.resolve({ locale: 'ro', categorySlug: 'politica' }),
        searchParams: Promise.resolve({}),
      });
    } catch (e: any) {
      expect(e.message).toBe('NEXT_NOT_FOUND');
    }
  });

  it('renders article lead in hero card', async () => {
    mockFetchArticles.mockResolvedValue({
      member: [makeArticle(1, { lead: 'Important breaking news about politics' })],
      totalItems: 1,
    });

    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'ro', categorySlug: 'politica' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Important breaking news about politics')).toBeInTheDocument();
  });

  it('generateMetadata handles reserved slug', async () => {
    const { isReservedSlug } = require('@/lib/constants/reserved-slugs');
    (isReservedSlug as jest.Mock).mockReturnValue(true);

    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'ro', categorySlug: 'admin' }),
      searchParams: Promise.resolve({}),
    });
    expect(result.title).toBe('Page Not Found');
  });

  it('generateMetadata handles unknown category', async () => {
    mockFetchCategories.mockResolvedValue({ member: [] });
    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'ro', categorySlug: 'nonexistent' }),
      searchParams: Promise.resolve({}),
    });
    expect(result.title).toBe('Category Not Found');
  });

  it('generateMetadata handles API error gracefully', async () => {
    mockFetchCategories.mockRejectedValue(new Error('API error'));
    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'ro', categorySlug: 'politica' }),
      searchParams: Promise.resolve({}),
    });
    expect(result.title).toBe('Category | Deschide News');
  });
});
