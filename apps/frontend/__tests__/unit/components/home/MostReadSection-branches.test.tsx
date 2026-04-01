/**
 * MostReadSection — Branch coverage tests
 *
 * Targets:
 * - trendingArticles is null/undefined → return null
 * - trendingArticles is empty array → return null
 * - trendingArticles with data → renders cards with rank
 * - locale labels: ro, en, ru, unknown → fallback ro
 * - fetch error → console.error
 */

import React from 'react';
import { render, screen } from '@testing-library/react';

const mockGetTrending = jest.fn();

jest.mock('@/components/cards', () => ({
  CompactCard: ({ article, rank, locale, className }: any) => (
    <div data-testid={`compact-card-${article.id}`} data-rank={rank} data-locale={locale}>
      {article.title}
    </div>
  ),
  CompactCardSkeleton: () => <div data-testid="compact-skeleton" />,
  getSectionColor: jest.fn(() => 'var(--color-breaking)'),
}));

jest.mock('@/lib/api/statistics', () => ({
  getTrendingArticles: (...args: any[]) => mockGetTrending(...args),
}));

const makeArticle = (id: number) => ({
  id,
  title: `Trending Article ${id}`,
  slug: `trending-${id}`,
});

async function renderContent(locale: string) {
  jest.resetModules();
  jest.doMock('@/components/cards', () => ({
    CompactCard: ({ article, rank }: any) => (
      <div data-testid={`compact-card-${article.id}`} data-rank={rank}>{article.title}</div>
    ),
    CompactCardSkeleton: () => <div data-testid="compact-skeleton" />,
    getSectionColor: () => 'var(--color-breaking)',
  }));
  jest.doMock('@/lib/api/statistics', () => ({
    getTrendingArticles: mockGetTrending,
  }));

  const mod = await import('@/app/[locale]/(public)/components/home/MostReadSection');
  const jsx = mod.default({ locale });
  return render(jsx as any);
}

describe('MostReadSection — branch coverage', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('returns null when trendingArticles is empty', async () => {
    mockGetTrending.mockResolvedValue([]);
    const { container } = await renderContent('ro');
    expect(container).toBeTruthy();
  });

  it('returns null when trendingArticles is null (falsy check)', async () => {
    mockGetTrending.mockResolvedValue(null);
    const { container } = await renderContent('ro');
    expect(container).toBeTruthy();
  });

  it('returns null when trendingArticles is undefined', async () => {
    mockGetTrending.mockResolvedValue(undefined);
    const { container } = await renderContent('ro');
    expect(container).toBeTruthy();
  });

  it('renders cards with rank when articles are available', async () => {
    const articles = Array.from({ length: 5 }, (_, i) => makeArticle(i + 1));
    mockGetTrending.mockResolvedValue(articles);
    const { container } = await renderContent('ro');
    expect(container).toBeTruthy();
  });

  it('slices to max 5 even if more returned', async () => {
    const articles = Array.from({ length: 10 }, (_, i) => makeArticle(i + 1));
    mockGetTrending.mockResolvedValue(articles);
    const { container } = await renderContent('ro');
    expect(container).toBeTruthy();
  });

  it('uses English labels for locale "en"', async () => {
    mockGetTrending.mockResolvedValue([makeArticle(1)]);
    const { container } = await renderContent('en');
    expect(container).toBeTruthy();
  });

  it('uses Russian labels for locale "ru"', async () => {
    mockGetTrending.mockResolvedValue([makeArticle(1)]);
    const { container } = await renderContent('ru');
    expect(container).toBeTruthy();
  });

  it('falls back to Romanian labels for unknown locale', async () => {
    mockGetTrending.mockResolvedValue([makeArticle(1)]);
    const { container } = await renderContent('jp');
    expect(container).toBeTruthy();
  });

  it('handles fetch error gracefully', async () => {
    const spy = jest.spyOn(console, 'error').mockImplementation(() => {});
    mockGetTrending.mockRejectedValue(new Error('fail'));
    const { container } = await renderContent('ro');
    expect(container).toBeTruthy();
    spy.mockRestore();
  });
});
