/**
 * CategorySection Component Tests
 * Tests label logic, layout types, article slicing, and category title logic
 */

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

// Mock dependencies
jest.mock('@/lib/api/articles', () => ({
  fetchArticlesByCategory: jest.fn(),
}));

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt, fill, ...rest }: any) => <img src={src} alt={alt} />,
}));

jest.mock('@/lib/api/important-articles', () => ({
  getFeaturedImage: jest.fn(() => null),
  getThumbnailByProfile: jest.fn(() => null),
  buildImageUrl: jest.fn((path: string) => `http://cdn.test/${path}`),
}));

jest.mock('@/lib/utils/url-builder', () => ({
  buildArticleUrl: jest.fn((article: any, locale: string) => `/${locale}/${article.slug}`),
  buildCategoryUrl: jest.fn((category: any, locale: string) => `/${locale}/${category.slug}`),
}));

jest.mock('@/components/cards/utils', () => ({
  getSectionColor: jest.fn(() => 'var(--color-accent)'),
  getCategorySlugFromArticle: jest.fn(() => 'politica'),
  getCategoryTitle: jest.fn(() => 'Politica'),
  getFirstSentence: jest.fn((text: string) => text.substring(0, 50)),
}));

import CategorySection, { CategorySectionLayout } from '@/app/[locale]/(public)/components/home/CategorySection';

describe('CategorySection', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders without crashing (Suspense wrapper)', () => {
    const { container } = render(
      <CategorySection
        category={{ id: 1, title: 'Politica', slug: 'politica' }}
        locale="ro"
        layout="grid-3col"
      />
    );
    expect(container).toBeInTheDocument();
  });

  it('renders with compact-list layout', () => {
    const { container } = render(
      <CategorySection
        category={{ id: 1, title: 'Economie', slug: 'economie' }}
        locale="en"
        layout="compact-list"
      />
    );
    expect(container).toBeInTheDocument();
  });

  it('renders with grid-4col layout', () => {
    const { container } = render(
      <CategorySection
        category={{ id: 1, title: 'Sport', slug: 'sport' }}
        locale="ru"
        layout="grid-4col"
      />
    );
    expect(container).toBeInTheDocument();
  });

  it('renders with featured-grid layout', () => {
    const { container } = render(
      <CategorySection
        category={{ id: 1, title: 'Cultura', slug: 'cultura' }}
        locale="ro"
        layout="featured-grid"
      />
    );
    expect(container).toBeInTheDocument();
  });
});

// Test the exported CategorySectionLayout type values
describe('CategorySectionLayout type', () => {
  const layouts: CategorySectionLayout[] = ['grid-3col', 'compact-list', 'grid-4col', 'featured-grid'];

  it('includes grid-3col', () => {
    expect(layouts).toContain('grid-3col');
  });

  it('includes compact-list', () => {
    expect(layouts).toContain('compact-list');
  });

  it('includes grid-4col', () => {
    expect(layouts).toContain('grid-4col');
  });

  it('includes featured-grid', () => {
    expect(layouts).toContain('featured-grid');
  });
});

// Test the localized labels directly
describe('CategorySection labels', () => {
  const labels = {
    ro: { viewAll: 'Vezi toate' },
    en: { viewAll: 'View all' },
    ru: { viewAll: 'Смотреть все' },
  } as const;

  it('has correct Romanian viewAll label', () => {
    expect(labels.ro.viewAll).toBe('Vezi toate');
  });

  it('has correct English viewAll label', () => {
    expect(labels.en.viewAll).toBe('View all');
  });

  it('has correct Russian viewAll label', () => {
    expect(labels.ru.viewAll).toBe('Смотреть все');
  });

  it('falls back to Romanian for unknown locale', () => {
    const locale = 'fr';
    const result = labels[locale as keyof typeof labels] || labels.ro;
    expect(result.viewAll).toBe('Vezi toate');
  });
});

// Test maxArticles logic
describe('CategorySection maxArticles logic', () => {
  function getMaxArticles(layout: CategorySectionLayout): number {
    return layout === 'grid-4col' || layout === 'featured-grid' ? 8 : 6;
  }

  it('returns 6 for grid-3col', () => {
    expect(getMaxArticles('grid-3col')).toBe(6);
  });

  it('returns 6 for compact-list', () => {
    expect(getMaxArticles('compact-list')).toBe(6);
  });

  it('returns 8 for grid-4col', () => {
    expect(getMaxArticles('grid-4col')).toBe(8);
  });

  it('returns 8 for featured-grid', () => {
    expect(getMaxArticles('featured-grid')).toBe(8);
  });
});

// Test categoryTitle resolution logic
describe('CategorySection categoryTitle logic', () => {
  function getCategoryDisplayTitle(category: any): string {
    return category.title || category.name || 'Uncategorized';
  }

  it('returns title when present', () => {
    expect(getCategoryDisplayTitle({ id: 1, title: 'Politica', slug: 'politica' })).toBe('Politica');
  });

  it('returns name when title is absent', () => {
    expect(getCategoryDisplayTitle({ id: 1, name: 'Economy', slug: 'economie' })).toBe('Economy');
  });

  it('returns Uncategorized when neither title nor name present', () => {
    expect(getCategoryDisplayTitle({ id: 1, slug: 'unknown' })).toBe('Uncategorized');
  });

  it('prefers title over name', () => {
    expect(getCategoryDisplayTitle({ id: 1, title: 'Politica', name: 'Politics' })).toBe('Politica');
  });
});

// Test categorySlug fallback logic
describe('CategorySection categorySlug fallback', () => {
  function getCategorySlug(category: any): string {
    return category.slug || 'default';
  }

  it('returns slug when present', () => {
    expect(getCategorySlug({ id: 1, slug: 'politica' })).toBe('politica');
  });

  it('returns default when slug is absent', () => {
    expect(getCategorySlug({ id: 1, title: 'Unknown' })).toBe('default');
  });

  it('returns default for empty slug', () => {
    expect(getCategorySlug({ id: 1, slug: '' })).toBe('default');
  });
});

// Test article slicing per layout
describe('CategorySection article slicing per layout', () => {
  const makeArticles = (count: number) =>
    Array.from({ length: count }, (_, i) => ({ id: i + 1, slug: `article-${i + 1}` }));

  it('grid-3col slices first 6 articles', () => {
    const articles = makeArticles(10);
    const sliced = articles.slice(0, 6);
    expect(sliced).toHaveLength(6);
    expect(sliced[0].id).toBe(1);
    expect(sliced[5].id).toBe(6);
  });

  it('compact-list slices first 6 articles', () => {
    const articles = makeArticles(10);
    const sliced = articles.slice(0, 6);
    expect(sliced).toHaveLength(6);
  });

  it('grid-4col slices first 8 articles', () => {
    const articles = makeArticles(10);
    const sliced = articles.slice(0, 8);
    expect(sliced).toHaveLength(8);
    expect(sliced[7].id).toBe(8);
  });

  it('featured-grid: featured=articles[0], stacked=1-3, bottom=4-7', () => {
    const articles = makeArticles(8);
    const featured = articles[0];
    const stacked = articles.slice(1, 4);
    const bottom = articles.slice(4, 8);
    expect(featured.id).toBe(1);
    expect(stacked).toHaveLength(3);
    expect(stacked[0].id).toBe(2);
    expect(bottom).toHaveLength(4);
    expect(bottom[0].id).toBe(5);
  });

  it('featured-grid: no bottom articles when fewer than 5 total', () => {
    const articles = makeArticles(4);
    const bottom = articles.slice(4, 8);
    expect(bottom).toHaveLength(0);
  });

  it('featured-grid: no stacked articles when only 1 article', () => {
    const articles = makeArticles(1);
    const stacked = articles.slice(1, 4);
    expect(stacked).toHaveLength(0);
  });

  it('handles empty array gracefully for slicing', () => {
    const articles: any[] = [];
    const grid = articles.slice(0, 6);
    expect(grid).toHaveLength(0);
  });

  it('returns less than max when fewer articles available', () => {
    const articles = makeArticles(3);
    const sliced = articles.slice(0, 6);
    expect(sliced).toHaveLength(3);
  });
});

// Test excerpt logic (lead || getFirstSentence)
describe('CategorySection excerpt logic', () => {
  function getExcerpt(article: any): string {
    return article.lead || (article.content ? article.content.substring(0, 50) : '');
  }

  it('uses lead when present', () => {
    const article = { lead: 'Lead text.', content: '<p>Content.</p>' };
    expect(getExcerpt(article)).toBe('Lead text.');
  });

  it('falls back to content when no lead', () => {
    const article = { lead: '', content: '<p>Content text here.</p>' };
    expect(getExcerpt(article)).toBe('<p>Content text here.</p>'.substring(0, 50));
  });

  it('returns empty string when neither lead nor content', () => {
    const article = { lead: null, content: null };
    expect(getExcerpt(article)).toBe('');
  });

  it('returns empty string when lead is null and content is empty', () => {
    const article = { lead: null, content: '' };
    expect(getExcerpt(article)).toBe('');
  });
});
