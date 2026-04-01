/**
 * LatestNewsSection Component Tests
 */

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import LatestNewsSection from '@/app/[locale]/(public)/components/home/LatestNewsSection';

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href, ...props }: any) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt, fill, sizes, priority, loading, ...rest }: any) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={src} alt={alt} {...rest} />
  ),
}));

jest.mock('@/lib/api/important-articles', () => ({
  getFeaturedImage: (images: any[]) =>
    images && images.length > 0 ? images[0].image || images[0] : null,
  getThumbnailByProfile: () => null,
  buildImageUrl: (path: string) => `http://cdn.test/uploads/${path}`,
}));

jest.mock('@/lib/utils/url-builder', () => ({
  buildArticleUrl: (article: any, locale: string) =>
    `/${locale}/${article.category?.slug || 'news'}/${article.slug}`,
  buildLocalizedUrl: (path: string, locale: string) => `/${locale}${path}`,
}));

jest.mock('@/components/cards/utils', () => ({
  getSectionColor: () => 'var(--color-section-politics)',
  getCategorySlugFromArticle: (cat: any) =>
    typeof cat === 'object' && cat?.slug ? cat.slug : '',
  getCategoryTitle: (cat: any) =>
    typeof cat === 'object' && cat?.title ? cat.title : '',
  formatRelativeTime: () => '2h',
}));

const makeArticle = (id: number, title: string, lead?: string) => ({
  '@id': `/api/articles/${id}`,
  '@type': 'Article' as const,
  id,
  title,
  slug: `article-${id}`,
  lead: lead || null,
  content: '<p>Content</p>',
  category: { id: 1, title: 'Politica', slug: 'politica' },
  authors: [],
  articleImages: [],
  tags: [],
  status: 'published' as const,
  viewCount: 100,
  publishedAt: '2026-03-20T10:00:00Z',
  createdAt: '2026-03-20T10:00:00Z',
  updatedAt: '2026-03-20T10:00:00Z',
});

const mockArticles = [
  makeArticle(1, 'Featured Article', 'Featured lead text'),
  makeArticle(2, 'Grid Article One'),
  makeArticle(3, 'Grid Article Two'),
  makeArticle(4, 'Grid Article Three'),
  makeArticle(5, 'Grid Article Four'),
  makeArticle(6, 'Grid Article Five'),
  makeArticle(7, 'Grid Article Six'),
];

const mockPopularArticles = [
  { id: 101, title: 'Popular One', slug: 'popular-one', category: { slug: 'politica', name: 'Politică' } },
  { id: 102, title: 'Popular Two', slug: 'popular-two', category: { slug: 'sport', name: 'Sport' } },
];

describe('LatestNewsSection', () => {
  it('renders without crashing', () => {
    render(
      <LatestNewsSection
        articles={mockArticles}
        popularArticles={mockPopularArticles}
        locale="ro"
      />
    );
    expect(screen.getByText('Featured Article')).toBeInTheDocument();
  });

  it('renders null when no articles', () => {
    const { container } = render(
      <LatestNewsSection articles={[]} popularArticles={[]} locale="ro" />
    );
    expect(container.querySelector('section')).not.toBeInTheDocument();
  });

  it('renders section header with Romanian label', () => {
    render(
      <LatestNewsSection articles={mockArticles} popularArticles={[]} locale="ro" />
    );
    expect(screen.getByText('Ultimele știri')).toBeInTheDocument();
  });

  it('renders section header with English label', () => {
    render(
      <LatestNewsSection articles={mockArticles} popularArticles={[]} locale="en" />
    );
    expect(screen.getByText('Latest news')).toBeInTheDocument();
  });

  it('renders section header with Russian label', () => {
    render(
      <LatestNewsSection articles={mockArticles} popularArticles={[]} locale="ru" />
    );
    expect(screen.getByText('Последние новости')).toBeInTheDocument();
  });

  it('renders featured article as the first article', () => {
    render(
      <LatestNewsSection articles={mockArticles} popularArticles={[]} locale="ro" />
    );
    expect(screen.getByText('Featured Article')).toBeInTheDocument();
  });

  it('renders lead for featured article', () => {
    render(
      <LatestNewsSection articles={mockArticles} popularArticles={[]} locale="ro" />
    );
    expect(screen.getByText('Featured lead text')).toBeInTheDocument();
  });

  it('renders grid articles', () => {
    render(
      <LatestNewsSection articles={mockArticles} popularArticles={[]} locale="ro" />
    );
    expect(screen.getByText('Grid Article One')).toBeInTheDocument();
    expect(screen.getByText('Grid Article Two')).toBeInTheDocument();
  });

  it('renders sidebar trend widget when popularArticles provided', () => {
    render(
      <LatestNewsSection
        articles={mockArticles}
        popularArticles={mockPopularArticles}
        locale="ro"
      />
    );
    expect(screen.getByText('În trend')).toBeInTheDocument();
    expect(screen.getByText('Popular One')).toBeInTheDocument();
  });

  it('renders Trending in English', () => {
    render(
      <LatestNewsSection
        articles={mockArticles}
        popularArticles={mockPopularArticles}
        locale="en"
      />
    );
    expect(screen.getByText('Trending')).toBeInTheDocument();
  });

  it('does not render trend section when no popular articles', () => {
    render(
      <LatestNewsSection articles={mockArticles} popularArticles={[]} locale="ro" />
    );
    expect(screen.queryByText('În trend')).not.toBeInTheDocument();
  });

  it('renders InTrend widget', () => {
    render(
      <LatestNewsSection
        articles={mockArticles}
        popularArticles={mockPopularArticles}
        locale="ro"
      />
    );
    expect(screen.getByText('În trend')).toBeInTheDocument();
  });

  it('renders InTrend in English', () => {
    render(
      <LatestNewsSection
        articles={mockArticles}
        popularArticles={mockPopularArticles}
        locale="en"
      />
    );
    expect(screen.getByText('Trending')).toBeInTheDocument();
  });

  it('renders InTrend in Russian', () => {
    render(
      <LatestNewsSection
        articles={mockArticles}
        popularArticles={mockPopularArticles}
        locale="ru"
      />
    );
    expect(screen.getByText('В тренде')).toBeInTheDocument();
  });

  it('renders ad placeholder for Romanian', () => {
    render(
      <LatestNewsSection articles={mockArticles} popularArticles={[]} locale="ro" />
    );
    expect(screen.getByText('Publicitate')).toBeInTheDocument();
  });

  it('renders ad placeholder for English', () => {
    render(
      <LatestNewsSection articles={mockArticles} popularArticles={[]} locale="en" />
    );
    expect(screen.getByText('Advertisement')).toBeInTheDocument();
  });

  it('renders ad placeholder for Russian', () => {
    render(
      <LatestNewsSection articles={mockArticles} popularArticles={[]} locale="ru" />
    );
    expect(screen.getByText('Реклама')).toBeInTheDocument();
  });

  it('renders "View all" link for Romanian', () => {
    render(
      <LatestNewsSection articles={mockArticles} popularArticles={[]} locale="ro" />
    );
    expect(screen.getByRole('link', { name: /Vezi toate/ })).toBeInTheDocument();
  });

  it('renders "View all" link for English', () => {
    render(
      <LatestNewsSection articles={mockArticles} popularArticles={[]} locale="en" />
    );
    expect(screen.getByRole('link', { name: /View all/ })).toBeInTheDocument();
  });

  it('renders trend items with category colored dots', () => {
    render(
      <LatestNewsSection
        articles={mockArticles}
        popularArticles={mockPopularArticles}
        locale="ro"
      />
    );
    const politicaItems = screen.getAllByText('Politică');
    expect(politicaItems.length).toBeGreaterThan(0);
  });

  it('renders with a single article', () => {
    render(
      <LatestNewsSection
        articles={[makeArticle(1, 'Only Article')]}
        popularArticles={[]}
        locale="ro"
      />
    );
    expect(screen.getByText('Only Article')).toBeInTheDocument();
    // Grid should not render when no grid articles
    expect(screen.queryByText('Grid Article One')).not.toBeInTheDocument();
  });
});
