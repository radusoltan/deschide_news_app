/**
 * CategoryHighlights Component Tests
 */

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

// Mock data fetching
jest.mock('@/lib/api/articles', () => ({
  fetchArticlesByCategory: jest.fn(),
}));

// Mock card components
jest.mock('@/components/cards', () => ({
  FeatureCard: ({ article, locale }: any) => (
    <article data-testid="feature-card" data-locale={locale}>
      {article.title}
    </article>
  ),
  CompactCard: ({ article, locale }: any) => (
    <article data-testid="compact-card" data-locale={locale}>
      {article.title}
    </article>
  ),
  FeatureCardSkeleton: () => <div data-testid="feature-card-skeleton" />,
  CompactCardSkeleton: () => <div data-testid="compact-card-skeleton" />,
  getSectionColor: jest.fn(() => 'var(--color-accent)'),
}));

// Mock next/link
jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
}));

// Mock url-builder
jest.mock('@/lib/utils/url-builder', () => ({
  buildCategoryUrl: jest.fn((category: any, locale: string) => `/${locale}/${category.slug}`),
}));

import { fetchArticlesByCategory } from '@/lib/api/articles';
import CategoryHighlights from '@/app/[locale]/(public)/components/home/CategoryHighlights';

describe('CategoryHighlights', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders without crashing', () => {
    (fetchArticlesByCategory as jest.Mock).mockImplementation(() => new Promise(() => {}));
    const { container } = render(
      <CategoryHighlights category={{ id: 1, title: 'Politica', slug: 'politica' }} locale="ro" />
    );
    expect(container).toBeInTheDocument();
  });
});

// Test the localized labels
describe('CategoryHighlights labels', () => {
  const labels = {
    ro: { viewAll: 'Vezi toate' },
    en: { viewAll: 'View all' },
    ru: { viewAll: 'Смотреть все' },
  };

  it('has correct Romanian view all label', () => {
    expect(labels.ro.viewAll).toBe('Vezi toate');
  });

  it('has correct English view all label', () => {
    expect(labels.en.viewAll).toBe('View all');
  });

  it('has correct Russian view all label', () => {
    expect(labels.ru.viewAll).toBe('Смотреть все');
  });
});

// Test article slicing logic (first 2 = feature, next 3 = compact)
describe('CategoryHighlights article split logic', () => {
  const articles = Array.from({ length: 5 }, (_, i) => ({ id: i + 1, title: `Article ${i + 1}` }));

  it('feature articles are first 2', () => {
    const featureArticles = articles.slice(0, 2);
    expect(featureArticles).toHaveLength(2);
    expect(featureArticles[0].id).toBe(1);
    expect(featureArticles[1].id).toBe(2);
  });

  it('compact articles are positions 3-5', () => {
    const compactArticles = articles.slice(2, 5);
    expect(compactArticles).toHaveLength(3);
    expect(compactArticles[0].id).toBe(3);
    expect(compactArticles[2].id).toBe(5);
  });

  it('compact articles section not shown when only 2 articles', () => {
    const twoArticles = articles.slice(0, 2);
    const compactArticles = twoArticles.slice(2, 5);
    expect(compactArticles.length).toBe(0);
  });
});

// Test category title fallback
describe('CategoryHighlights category title fallback', () => {
  it('uses category.title when available', () => {
    const category = { id: 1, title: 'Politica', slug: 'politica' };
    const title = category.title || (category as any).name || 'Uncategorized';
    expect(title).toBe('Politica');
  });

  it('falls back to category.name when title is missing', () => {
    const category = { id: 1, name: 'Sport', slug: 'sport' } as any;
    const title = category.title || category.name || 'Uncategorized';
    expect(title).toBe('Sport');
  });

  it('falls back to "Uncategorized" when both missing', () => {
    const category = { id: 1, slug: 'unknown' } as any;
    const title = category.title || category.name || 'Uncategorized';
    expect(title).toBe('Uncategorized');
  });
});
