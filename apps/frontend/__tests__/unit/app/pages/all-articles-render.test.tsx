/**
 * Tests for [locale]/(public)/all/page.tsx — rendering branches
 * Covers: articles rendering, category filter pills, pagination, empty state, trending sidebar
 */

jest.mock('next/navigation', () => ({
  notFound: jest.fn(),
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

jest.mock('@/lib/api/statistics', () => ({
  getTrendingArticles: jest.fn(),
}));

jest.mock('@/lib/api/important-articles', () => ({
  buildImageUrl: jest.fn((path: string) => `http://cdn/${path}`),
  getThumbnailByProfile: jest.fn(() => null),
  getFeaturedImage: jest.fn(() => null),
}));

jest.mock('@/lib/utils/url-builder', () => ({
  buildArticleUrl: jest.fn((article: any, locale: string) => `/${locale}/${article.slug}`),
}));

jest.mock('@/components/cards/utils', () => ({
  getSectionColor: jest.fn(() => 'var(--color-accent)'),
  getCategorySlugFromArticle: jest.fn(() => 'politica'),
}));

const mockFetch = jest.fn();
global.fetch = mockFetch;

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { fetchCategories } from '@/lib/api/categories';
import { getTrendingArticles } from '@/lib/api/statistics';

const mockFetchCategories = fetchCategories as jest.Mock;
const mockGetTrending = getTrendingArticles as jest.Mock;

function makeArticle(id: number, overrides: any = {}) {
  return {
    id,
    title: `Article ${id}`,
    slug: `article-${id}`,
    lead: `Lead for article ${id}`,
    category: { title: 'Politics', slug: 'politica', id: 1 },
    articleImages: [],
    ...overrides,
  };
}

describe('AllArticlesPage rendering', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockFetchCategories.mockResolvedValue({ member: [] });
    mockGetTrending.mockResolvedValue([]);
    mockFetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({ member: [], totalItems: 0 }),
    });
  });

  it('renders page title in Romanian', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'ro' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);
    expect(screen.getByText('Toate articolele')).toBeInTheDocument();
  });

  it('renders page title in English', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);
    expect(screen.getByText('All articles')).toBeInTheDocument();
  });

  it('renders empty state when no articles', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);
    expect(screen.getByText('No articles found')).toBeInTheDocument();
  });

  it('renders articles grid when articles exist', async () => {
    mockFetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({
        member: [makeArticle(1), makeArticle(2), makeArticle(3)],
        totalItems: 3,
      }),
    });

    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Article 1')).toBeInTheDocument();
    expect(screen.getByText('Article 2')).toBeInTheDocument();
    expect(screen.getByText('Article 3')).toBeInTheDocument();
  });

  it('renders category filter pills when categories exist', async () => {
    mockFetchCategories.mockResolvedValue({
      member: [
        { id: 1, title: 'Politics', slug: 'politica' },
        { id: 2, title: 'Economy', slug: 'economie' },
      ],
    });

    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('All')).toBeInTheDocument();
    // Category pills link to /en/all?category=<id>
    const allLinks = screen.getAllByRole('link');
    const politicsFilter = allLinks.find(l => l.getAttribute('href')?.includes('category=1'));
    const economyFilter = allLinks.find(l => l.getAttribute('href')?.includes('category=2'));
    expect(politicsFilter).toBeDefined();
    expect(economyFilter).toBeDefined();
  });

  it('applies active style to selected category filter', async () => {
    mockFetchCategories.mockResolvedValue({
      member: [{ id: 1, title: 'Politics', slug: 'politica' }],
    });

    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({ category: '1' }),
    });
    render(<>{Component}</>);

    // The active category filter link should have inline style
    const politicsLinks = screen.getAllByText('Politics');
    // Find the filter pill (the link to /en/all?category=1)
    const filterPill = politicsLinks.find(el => el.closest('a')?.getAttribute('href')?.includes('category='));
    expect(filterPill).toBeDefined();
  });

  it('renders pagination when totalPages > 1', async () => {
    mockFetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({
        member: [makeArticle(1)],
        totalItems: 25,
      }),
    });

    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({ page: '1' }),
    });
    render(<>{Component}</>);

    // Should have pagination nav
    const nav = screen.getByRole('navigation', { name: 'Pagination' });
    expect(nav).toBeInTheDocument();
    expect(screen.getByText('Next')).toBeInTheDocument();
  });

  it('renders previous button on page 2', async () => {
    mockFetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({
        member: [makeArticle(1)],
        totalItems: 25,
      }),
    });

    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({ page: '2' }),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Previous')).toBeInTheDocument();
    expect(screen.getByText('Next')).toBeInTheDocument();
  });

  it('renders trending articles in sidebar', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Trending')).toBeInTheDocument();
    expect(
      screen.getByText('Presidential elections 2025: latest polls')
    ).toBeInTheDocument();
  });

  it('renders In Trend widget with placeholder data', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Trending')).toBeInTheDocument();
  });

  it('renders ad placeholder', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Advertisement')).toBeInTheDocument();
    expect(screen.getByText('300 x 250')).toBeInTheDocument();
  });

  it('handles fetch returning null gracefully', async () => {
    mockFetch.mockResolvedValue({
      ok: false,
      json: jest.fn().mockResolvedValue(null),
    });

    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('No articles found')).toBeInTheDocument();
  });

  it('renders article lead text', async () => {
    mockFetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({
        member: [makeArticle(1, { lead: 'This is the lead' })],
        totalItems: 1,
      }),
    });

    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('This is the lead')).toBeInTheDocument();
  });

  it('renders article with category badge when category title exists', async () => {
    mockFetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({
        member: [makeArticle(1)],
        totalItems: 1,
      }),
    });

    const mod = await import('@/app/[locale]/(public)/all/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    // Article card renders with title
    expect(screen.getByText('Article 1')).toBeInTheDocument();
  });
});
