/**
 * Tests for MostReadSection component
 */

import React from 'react';
import { render, screen } from '@testing-library/react';

jest.mock('@/components/cards', () => ({
  CompactCard: ({ article, rank }: any) => (
    <div data-testid={`compact-card-${article.id}`}>
      {rank}. {article.title}
    </div>
  ),
  CompactCardSkeleton: () => <div data-testid="compact-skeleton" />,
  getSectionColor: jest.fn(() => 'var(--color-accent)'),
}));

jest.mock('@/lib/api/statistics', () => ({
  getTrendingArticles: jest.fn().mockResolvedValue([]),
}));

import MostReadSection from '@/app/[locale]/(public)/components/home/MostReadSection';

describe('MostReadSection', () => {
  it('renders without crashing', () => {
    const { container } = render(<MostReadSection locale="ro" />);
    expect(container).toBeTruthy();
  });

  it('shows skeleton while loading', () => {
    render(<MostReadSection locale="en" />);
    // Since Suspense renders fallback synchronously in test
    const skeletons = screen.getAllByTestId('compact-skeleton');
    expect(skeletons.length).toBe(5);
  });

  it('renders with different locales without error', () => {
    const { container: roContainer } = render(<MostReadSection locale="ro" />);
    expect(roContainer).toBeTruthy();

    const { container: ruContainer } = render(<MostReadSection locale="ru" />);
    expect(ruContainer).toBeTruthy();
  });
});
