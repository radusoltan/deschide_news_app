/**
 * HeroCard Component Tests
 */

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { HeroCard } from '@/components/cards/HeroCard';

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
  default: ({ src, alt, fill, priority, sizes, loading, fetchPriority, ...rest }: any) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={src} alt={alt} data-priority={priority ? 'true' : 'false'} {...rest} />
  ),
}));

jest.mock('@/lib/api/important-articles', () => ({
  getFeaturedImage: (images: any[]) =>
    images && images.length > 0 ? images[0].image || images[0] : null,
  getThumbnailByProfile: (image: any, profile: string) => null,
  buildImageUrl: (path: string) => `http://cdn.test/uploads/${path}`,
}));

jest.mock('@/lib/utils/url-builder', () => ({
  buildArticleUrl: (article: any, locale: string) =>
    `/${locale}/${article.category?.slug || 'news'}/${article.slug}`,
}));

jest.mock('@/components/cards/utils', () => ({
  getSectionColor: (slug: string) => 'var(--color-section-politics)',
  getCategorySlugFromArticle: (cat: any) =>
    typeof cat === 'object' && cat?.slug ? cat.slug : '',
  getCategoryTitle: (cat: any) =>
    typeof cat === 'object' && cat?.title ? cat.title : '',
  formatRelativeTime: (date: string, locale: string) => '2h',
  getLocalizedBadgeText: (badge: any, locale: string) =>
    badge === 'breaking' ? 'BREAKING' : badge === 'flash' ? 'FLASH' : null,
}));

const mockArticle = {
  '@id': '/api/articles/10',
  '@type': 'Article' as const,
  id: 10,
  title: 'Hero Article - Major Story of the Day',
  slug: 'hero-article',
  lead: 'This is the hero article lead for a very important story.',
  content: '<p>Full article content here.</p>',
  category: { id: 2, title: 'Politica', slug: 'politica' },
  authors: [],
  articleImages: [],
  tags: [],
  status: 'published' as const,
  viewCount: 5000,
  publishedAt: '2026-03-20T08:00:00Z',
  createdAt: '2026-03-20T08:00:00Z',
  updatedAt: '2026-03-20T08:00:00Z',
  badge: undefined as any,
};

describe('HeroCard', () => {
  it('renders without crashing', () => {
    render(<HeroCard article={mockArticle} locale="ro" />);
    expect(screen.getByText('Hero Article - Major Story of the Day')).toBeInTheDocument();
  });

  it('renders title in an h2 element', () => {
    const { container } = render(<HeroCard article={mockArticle} locale="ro" />);
    const h2 = container.querySelector('h2');
    expect(h2?.textContent).toContain('Hero Article - Major Story of the Day');
  });

  it('renders as a link to the article', () => {
    render(<HeroCard article={mockArticle} locale="ro" />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/ro/politica/hero-article');
  });

  it('renders category title', () => {
    render(<HeroCard article={mockArticle} locale="ro" />);
    expect(screen.getByText('Politica')).toBeInTheDocument();
  });

  it('renders lead text', () => {
    render(<HeroCard article={mockArticle} locale="ro" />);
    expect(
      screen.getByText('This is the hero article lead for a very important story.')
    ).toBeInTheDocument();
  });

  it('does not render lead when null', () => {
    const article = { ...mockArticle, lead: null as any };
    render(<HeroCard article={article} locale="ro" />);
    expect(screen.queryByText(/This is the hero article lead/)).not.toBeInTheDocument();
  });

  it('renders time when publishedAt is set', () => {
    render(<HeroCard article={mockArticle} locale="ro" />);
    expect(screen.getByRole('time')).toHaveTextContent('2h');
  });

  it('does not render time when no publishedAt', () => {
    const article = { ...mockArticle, publishedAt: undefined as any };
    render(<HeroCard article={article} locale="ro" />);
    expect(screen.queryByRole('time')).not.toBeInTheDocument();
  });

  it('renders gradient overlay background when no image', () => {
    const { container } = render(<HeroCard article={mockArticle} locale="ro" />);
    // When no image, renders gradient div
    const gradientDiv = container.querySelector('.absolute.inset-0.bg-gradient-to-br');
    expect(gradientDiv).toBeInTheDocument();
  });

  it('renders image when articleImages provided', () => {
    const articleWithImage = {
      ...mockArticle,
      articleImages: [
        {
          id: 1,
          image: { path: 'images/hero.jpg', alt: 'Hero image alt' },
          position: 0,
          isFeatured: true,
        },
      ],
    };
    render(<HeroCard article={articleWithImage} locale="ro" />);
    const img = screen.getByRole('img');
    expect(img).toHaveAttribute('src', 'http://cdn.test/uploads/images/hero.jpg');
  });

  it('renders breaking badge', () => {
    const article = { ...mockArticle, badge: 'breaking' as any };
    render(<HeroCard article={article} locale="ro" />);
    expect(screen.getByText('BREAKING')).toBeInTheDocument();
  });

  it('does not render badge when null', () => {
    render(<HeroCard article={mockArticle} locale="ro" />);
    expect(screen.queryByText('BREAKING')).not.toBeInTheDocument();
  });

  it('renders with priority=true by default', () => {
    const { container } = render(<HeroCard article={mockArticle} locale="ro" priority={true} />);
    // Just verify it renders
    expect(screen.getByText('Hero Article - Major Story of the Day')).toBeInTheDocument();
  });

  it('applies custom className', () => {
    const { container } = render(
      <HeroCard article={mockArticle} locale="ro" className="hero-custom" />
    );
    const article = container.querySelector('article');
    expect(article?.className).toContain('hero-custom');
  });

  it('renders with no category gracefully', () => {
    const article = { ...mockArticle, category: undefined as any };
    render(<HeroCard article={article} locale="ro" />);
    expect(screen.getByText('Hero Article - Major Story of the Day')).toBeInTheDocument();
  });
});
