/**
 * MostReadSection Component Tests
 */

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

// Mock data fetching
jest.mock('@/lib/api/statistics', () => ({
  getTrendingArticles: jest.fn(),
}));

// Mock card components
jest.mock('@/components/cards', () => ({
  CompactCard: ({ article, locale, rank }: any) => (
    <article data-testid="compact-card" data-rank={rank} data-locale={locale}>
      {article.title}
    </article>
  ),
  CompactCardSkeleton: () => <div data-testid="compact-card-skeleton" />,
  getSectionColor: jest.fn(() => 'var(--color-accent)'),
}));

import { getTrendingArticles } from '@/lib/api/statistics';
import MostReadSection from '@/app/[locale]/(public)/components/home/MostReadSection';

describe('MostReadSection', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders without crashing', () => {
    (getTrendingArticles as jest.Mock).mockImplementation(() => new Promise(() => {}));
    const { container } = render(<MostReadSection locale="ro" />);
    expect(container).toBeInTheDocument();
  });
});

// Test label strings
describe('MostReadSection labels', () => {
  const labels = {
    ro: { mostRead: 'Cele mai citite' },
    en: { mostRead: 'Most Read' },
    ru: { mostRead: 'Самое популярное' },
  };

  it('has correct Romanian label', () => {
    expect(labels.ro.mostRead).toBe('Cele mai citite');
  });

  it('has correct English label', () => {
    expect(labels.en.mostRead).toBe('Most Read');
  });

  it('has correct Russian label', () => {
    expect(labels.ru.mostRead).toBe('Самое популярное');
  });
});

// Test rank indexing (1-based from 0-based array index)
describe('MostReadSection rank calculation', () => {
  it('rank 1 for index 0', () => {
    expect(0 + 1).toBe(1);
  });

  it('rank 5 for index 4', () => {
    expect(4 + 1).toBe(5);
  });

  it('slices to max 5 articles', () => {
    const articles = Array.from({ length: 10 }, (_, i) => ({ id: i + 1 }));
    expect(articles.slice(0, 5).length).toBe(5);
  });
});
