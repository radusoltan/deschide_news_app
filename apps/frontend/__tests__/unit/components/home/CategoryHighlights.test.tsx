/**
 * Tests for CategoryHighlights component
 */

import React from 'react';
import { render, screen } from '@testing-library/react';

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href, ...props }: any) => <a href={href} {...props}>{children}</a>,
}));

jest.mock('@/components/cards', () => ({
  FeatureCard: ({ article }: any) => <div data-testid={`feature-card-${article.id}`}>{article.title}</div>,
  CompactCard: ({ article }: any) => <div data-testid={`compact-card-${article.id}`}>{article.title}</div>,
  FeatureCardSkeleton: () => <div data-testid="feature-skeleton" />,
  CompactCardSkeleton: () => <div data-testid="compact-skeleton" />,
  getSectionColor: jest.fn(() => 'var(--color-accent)'),
}));

jest.mock('@/lib/api/articles', () => ({
  fetchArticlesByCategory: jest.fn(),
}));

jest.mock('@/lib/utils/url-builder', () => ({
  buildCategoryUrl: jest.fn((cat: any, locale: string) => `/${locale}/${cat.slug}`),
}));

import CategoryHighlights from '@/app/[locale]/(public)/components/home/CategoryHighlights';

describe('CategoryHighlights', () => {
  it('renders without crashing', () => {
    const category = { id: 1, slug: 'politica', title: 'Politică' };
    const { container } = render(<CategoryHighlights category={category} locale="ro" />);
    expect(container).toBeTruthy();
  });

  it('renders Suspense with skeleton fallback by default', () => {
    const category = { id: 1, slug: 'economie', title: 'Economie' };
    render(<CategoryHighlights category={category} locale="en" />);
    // The Suspense will show skeleton during async resolution
    // The component itself should not throw
    expect(screen.queryAllByTestId('feature-skeleton').length).toBeGreaterThan(0);
  });

  it('shows skeletons for feature cards', () => {
    const category = { id: 2, slug: 'sport', title: 'Sport' };
    render(<CategoryHighlights category={category} locale="ro" />);
    expect(screen.getAllByTestId('feature-skeleton').length).toBeGreaterThanOrEqual(1);
  });

  it('shows skeletons for compact cards', () => {
    const category = { id: 3, slug: 'cultura', title: 'Cultură' };
    render(<CategoryHighlights category={category} locale="ru" />);
    expect(screen.getAllByTestId('compact-skeleton').length).toBeGreaterThanOrEqual(1);
  });
});
