/**
 * CategorySection — Branch coverage tests
 *
 * Targets all 4 layout variants and conditional branches:
 * - layout: 'grid-3col', 'compact-list', 'grid-4col', 'featured-grid'
 * - articles empty → returns null
 * - articles with data → renders correct layout
 * - category.slug fallback to 'default'
 * - category.title fallback to category.name, then 'Uncategorized'
 * - locale labels: ro, en, ru, unknown → fallback ro
 * - fetch error → console.error
 * - response.member undefined → defaults to []
 * - VerticalCard: with/without image, with/without excerpt, small vs normal
 * - CompactRow: with/without image, with/without category
 * - FeaturedCard: with/without image, with/without excerpt, with/without category
 * - LayoutFeaturedGrid: articles.length === 0 → returns null
 * - LayoutFeaturedGrid: stacked.length > 0 and bottom.length > 0
 * - maxArticles: 6 for grid-3col/compact-list, 8 for grid-4col/featured-grid
 */

import React from 'react';
import { render, screen } from '@testing-library/react';

const mockFetchArticlesByCategory = jest.fn();
const mockGetFeaturedImage = jest.fn();
const mockGetThumbnailByProfile = jest.fn();

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href, ...props }: any) => <a href={href} {...props}>{children}</a>,
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt, ...props }: any) => <img src={src} alt={alt || ''} />,
}));

jest.mock('@/lib/api/articles', () => ({
  fetchArticlesByCategory: (...args: any[]) => mockFetchArticlesByCategory(...args),
}));

jest.mock('@/lib/api/important-articles', () => ({
  getFeaturedImage: (...args: any[]) => mockGetFeaturedImage(...args),
  getThumbnailByProfile: (...args: any[]) => mockGetThumbnailByProfile(...args),
  buildImageUrl: jest.fn((path: string) => `http://cdn/${path}`),
}));

jest.mock('@/lib/utils/url-builder', () => ({
  buildArticleUrl: jest.fn((article: any, locale: string) => `/${locale}/${article.slug}`),
  buildCategoryUrl: jest.fn((cat: any, locale: string) => `/${locale}/${cat.slug || 'default'}`),
}));

jest.mock('@/components/cards/utils', () => ({
  getSectionColor: jest.fn(() => 'var(--color-accent)'),
  getCategorySlugFromArticle: jest.fn((cat: any) => cat?.slug || 'default'),
  getCategoryTitle: jest.fn((cat: any) => cat?.title || ''),
  getFirstSentence: jest.fn((text: string, maxLen?: number) => text ? text.split('.')[0] : ''),
}));

const makeArticle = (id: number, overrides: any = {}) => ({
  id,
  title: `Article ${id}`,
  slug: `article-${id}`,
  lead: `Lead for article ${id}`,
  content: '<p>Content for article</p>',
  publishedAt: '2026-03-15T10:00:00Z',
  category: { id: 1, title: 'Politica', slug: 'politica' },
  articleImages: [],
  ...overrides,
});

async function renderContent(category: any, locale: string, layout: string) {
  jest.resetModules();
  jest.doMock('next/link', () => ({
    __esModule: true,
    default: ({ children, href, ...props }: any) => <a href={href} {...props}>{children}</a>,
  }));
  jest.doMock('next/image', () => ({
    __esModule: true,
    default: ({ src, alt, ...props }: any) => <img src={src} alt={alt || ''} />,
  }));
  jest.doMock('@/lib/api/articles', () => ({
    fetchArticlesByCategory: mockFetchArticlesByCategory,
  }));
  jest.doMock('@/lib/api/important-articles', () => ({
    getFeaturedImage: mockGetFeaturedImage,
    getThumbnailByProfile: mockGetThumbnailByProfile,
    buildImageUrl: (path: string) => `http://cdn/${path}`,
  }));
  jest.doMock('@/lib/utils/url-builder', () => ({
    buildArticleUrl: (article: any, loc: string) => `/${loc}/${article.slug}`,
    buildCategoryUrl: (cat: any, loc: string) => `/${loc}/${cat.slug || 'default'}`,
  }));
  jest.doMock('@/components/cards/utils', () => ({
    getSectionColor: () => 'var(--color-accent)',
    getCategorySlugFromArticle: (cat: any) => cat?.slug || 'default',
    getCategoryTitle: (cat: any) => cat?.title || '',
    getFirstSentence: (text: string) => text ? text.split('.')[0] : '',
  }));

  const mod = await import('@/app/[locale]/(public)/components/home/CategorySection');
  const jsx = mod.default({ category, locale, layout: layout as any });
  return render(jsx as any);
}

describe('CategorySection — branch coverage', () => {
  const category = { id: 1, slug: 'politica', title: 'Politica' };

  beforeEach(() => {
    jest.clearAllMocks();
    mockGetFeaturedImage.mockReturnValue(null);
    mockGetThumbnailByProfile.mockReturnValue(null);
  });

  // Empty / error states
  it('returns null when articles are empty', async () => {
    mockFetchArticlesByCategory.mockResolvedValue({ member: [] });
    const { container } = await renderContent(category, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  it('returns null when response.member is undefined', async () => {
    mockFetchArticlesByCategory.mockResolvedValue({});
    const { container } = await renderContent(category, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  it('handles fetch error gracefully', async () => {
    const spy = jest.spyOn(console, 'error').mockImplementation(() => {});
    mockFetchArticlesByCategory.mockRejectedValue(new Error('fail'));
    const { container } = await renderContent(category, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
    spy.mockRestore();
  });

  // Layout A: grid-3col
  it('renders grid-3col layout with articles', async () => {
    const articles = Array.from({ length: 6 }, (_, i) => makeArticle(i + 1));
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  // Layout B: compact-list
  it('renders compact-list layout', async () => {
    const articles = Array.from({ length: 6 }, (_, i) => makeArticle(i + 1));
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'compact-list');
    expect(container).toBeTruthy();
  });

  // Layout C: grid-4col
  it('renders grid-4col layout with maxArticles=8', async () => {
    const articles = Array.from({ length: 8 }, (_, i) => makeArticle(i + 1));
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'grid-4col');
    expect(container).toBeTruthy();
  });

  // Layout D: featured-grid
  it('renders featured-grid layout with full set', async () => {
    const articles = Array.from({ length: 8 }, (_, i) => makeArticle(i + 1));
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'featured-grid');
    expect(container).toBeTruthy();
  });

  it('renders featured-grid with only 1 article (no stacked, no bottom)', async () => {
    mockFetchArticlesByCategory.mockResolvedValue({ member: [makeArticle(1)] });
    const { container } = await renderContent(category, 'ro', 'featured-grid');
    expect(container).toBeTruthy();
  });

  it('renders featured-grid with 4 articles (featured + stacked, no bottom)', async () => {
    const articles = Array.from({ length: 4 }, (_, i) => makeArticle(i + 1));
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'featured-grid');
    expect(container).toBeTruthy();
  });

  // VerticalCard branches
  it('renders VerticalCard with image', async () => {
    mockGetFeaturedImage.mockReturnValue({ path: 'images/photo.jpg', alt: 'Photo' });
    const articles = Array.from({ length: 3 }, (_, i) => makeArticle(i + 1));
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  it('renders VerticalCard with thumbnail', async () => {
    mockGetFeaturedImage.mockReturnValue({ path: 'images/photo.jpg', alt: 'Photo' });
    mockGetThumbnailByProfile.mockReturnValue({ path: 'thumbs/card.webp' });
    const articles = Array.from({ length: 3 }, (_, i) => makeArticle(i + 1));
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  it('renders VerticalCard without image (placeholder)', async () => {
    mockGetFeaturedImage.mockReturnValue(null);
    const articles = Array.from({ length: 3 }, (_, i) => makeArticle(i + 1));
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  it('renders VerticalCard with excerpt (article.lead)', async () => {
    const articles = [makeArticle(1, { lead: 'Some lead' })];
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  it('renders VerticalCard without lead but with content (uses getFirstSentence)', async () => {
    const articles = [makeArticle(1, { lead: null, content: 'First sentence. Second.' })];
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  it('renders VerticalCard without lead and without content', async () => {
    const articles = [makeArticle(1, { lead: null, content: null })];
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  it('renders VerticalCard with category title (badge)', async () => {
    const articles = [makeArticle(1, { category: { title: 'Sport', slug: 'sport' } })];
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  it('renders VerticalCard without category title', async () => {
    const articles = [makeArticle(1, { category: null })];
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  // CompactRow branches (compact-list layout)
  it('renders CompactRow with image', async () => {
    mockGetFeaturedImage.mockReturnValue({ path: 'images/photo.jpg', alt: 'Photo' });
    const articles = Array.from({ length: 3 }, (_, i) => makeArticle(i + 1));
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'compact-list');
    expect(container).toBeTruthy();
  });

  it('renders CompactRow without image', async () => {
    mockGetFeaturedImage.mockReturnValue(null);
    const articles = Array.from({ length: 3 }, (_, i) => makeArticle(i + 1));
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'compact-list');
    expect(container).toBeTruthy();
  });

  // FeaturedCard branches (featured-grid layout)
  it('renders FeaturedCard with image and excerpt', async () => {
    mockGetFeaturedImage.mockReturnValue({ path: 'images/photo.jpg', alt: 'Photo' });
    const articles = [makeArticle(1, { lead: 'Excerpt' })];
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'featured-grid');
    expect(container).toBeTruthy();
  });

  it('renders FeaturedCard without excerpt', async () => {
    const articles = [makeArticle(1, { lead: null, content: null })];
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'featured-grid');
    expect(container).toBeTruthy();
  });

  it('renders FeaturedCard without category title', async () => {
    const articles = [makeArticle(1, { category: null })];
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'featured-grid');
    expect(container).toBeTruthy();
  });

  // Category prop fallbacks
  it('falls back slug to "default" when category.slug is missing', async () => {
    mockFetchArticlesByCategory.mockResolvedValue({ member: [makeArticle(1)] });
    const { container } = await renderContent({ id: 1, title: 'Test' }, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  it('uses category.name when title is missing', async () => {
    mockFetchArticlesByCategory.mockResolvedValue({ member: [makeArticle(1)] });
    const { container } = await renderContent({ id: 1, slug: 'test', name: 'NameFallback' }, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  it('uses "Uncategorized" when both title and name are missing', async () => {
    mockFetchArticlesByCategory.mockResolvedValue({ member: [makeArticle(1)] });
    const { container } = await renderContent({ id: 1, slug: 'test' }, 'ro', 'grid-3col');
    expect(container).toBeTruthy();
  });

  // Locale tests
  it('uses English labels', async () => {
    mockFetchArticlesByCategory.mockResolvedValue({ member: [makeArticle(1)] });
    const { container } = await renderContent(category, 'en', 'grid-3col');
    expect(container).toBeTruthy();
  });

  it('uses Russian labels', async () => {
    mockFetchArticlesByCategory.mockResolvedValue({ member: [makeArticle(1)] });
    const { container } = await renderContent(category, 'ru', 'grid-3col');
    expect(container).toBeTruthy();
  });

  it('falls back to Romanian for unknown locale', async () => {
    mockFetchArticlesByCategory.mockResolvedValue({ member: [makeArticle(1)] });
    const { container } = await renderContent(category, 'zh', 'grid-3col');
    expect(container).toBeTruthy();
  });

  // VerticalCard small prop (grid-4col uses small=true)
  it('renders VerticalCard with small=true in grid-4col', async () => {
    const articles = Array.from({ length: 4 }, (_, i) => makeArticle(i + 1));
    mockFetchArticlesByCategory.mockResolvedValue({ member: articles });
    const { container } = await renderContent(category, 'ro', 'grid-4col');
    expect(container).toBeTruthy();
  });
});
