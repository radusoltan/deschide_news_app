/**
 * FeatureCard Component Tests
 */

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { FeatureCard } from '@/components/cards/FeatureCard';

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
    <img src={src} alt={alt} {...rest} />
  ),
}));

jest.mock('@/lib/api/important-articles', () => ({
  getFeaturedImage: (images: any[]) => (images && images.length > 0 ? images[0].image || images[0] : null),
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
    typeof cat === 'object' && cat?.slug ? cat.slug : typeof cat === 'string' ? cat : '',
  getCategoryTitle: (cat: any) =>
    typeof cat === 'object' && cat?.title ? cat.title : typeof cat === 'string' ? cat : '',
  formatRelativeTime: (date: string, locale: string) => '5 min',
  getLocalizedBadgeText: (badge: any, locale: string) =>
    badge === 'breaking' ? 'BREAKING' : badge === 'alert' ? 'ALERT' : null,
  getFirstSentence: (text: string) => text.substring(0, 100),
}));

const mockArticle = {
  '@id': '/api/articles/1',
  '@type': 'Article' as const,
  id: 1,
  title: 'Feature Article Title',
  slug: 'feature-article',
  lead: 'This is the article lead text',
  content: '<p>Article content here</p>',
  category: { id: 1, title: 'Politica', slug: 'politica' },
  authors: [],
  articleImages: [],
  tags: [],
  status: 'published' as const,
  viewCount: 100,
  publishedAt: '2026-03-20T10:00:00Z',
  createdAt: '2026-03-20T10:00:00Z',
  updatedAt: '2026-03-20T10:00:00Z',
  badge: undefined as any,
};

describe('FeatureCard', () => {
  it('renders without crashing', () => {
    render(<FeatureCard article={mockArticle} locale="ro" />);
    expect(screen.getByText('Feature Article Title')).toBeInTheDocument();
  });

  it('renders as a link to the article', () => {
    render(<FeatureCard article={mockArticle} locale="ro" />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/ro/politica/feature-article');
  });

  it('renders category title', () => {
    render(<FeatureCard article={mockArticle} locale="ro" />);
    expect(screen.getByText('Politica')).toBeInTheDocument();
  });

  it('renders relative time for publishedAt', () => {
    render(<FeatureCard article={mockArticle} locale="ro" />);
    expect(screen.getByRole('time')).toBeInTheDocument();
    expect(screen.getByRole('time')).toHaveTextContent('5 min');
  });

  it('renders excerpt when showExcerpt is true', () => {
    render(<FeatureCard article={mockArticle} locale="ro" showExcerpt={true} />);
    expect(screen.getByText('This is the article lead text')).toBeInTheDocument();
  });

  it('does not render excerpt when showExcerpt is false', () => {
    render(<FeatureCard article={mockArticle} locale="ro" showExcerpt={false} />);
    expect(screen.queryByText('This is the article lead text')).not.toBeInTheDocument();
  });

  it('renders fallback when no image', () => {
    render(<FeatureCard article={mockArticle} locale="ro" />);
    // Should render "No image" placeholder when no images
    expect(screen.getByText('No image')).toBeInTheDocument();
  });

  it('renders image when articleImages provided', () => {
    const articleWithImage = {
      ...mockArticle,
      articleImages: [
        {
          id: 1,
          image: { path: 'images/test.jpg', alt: 'Test image' },
          position: 0,
          isFeatured: true,
        },
      ],
    };
    render(<FeatureCard article={articleWithImage} locale="ro" />);
    const img = screen.getByRole('img');
    expect(img).toHaveAttribute('src', 'http://cdn.test/uploads/images/test.jpg');
  });

  it('renders breaking badge when badge is set', () => {
    const articleWithBadge = { ...mockArticle, badge: 'breaking' as any };
    render(<FeatureCard article={articleWithBadge} locale="ro" />);
    expect(screen.getByText('BREAKING')).toBeInTheDocument();
  });

  it('does not render badge when badge is null', () => {
    render(<FeatureCard article={mockArticle} locale="ro" />);
    expect(screen.queryByText('BREAKING')).not.toBeInTheDocument();
    expect(screen.queryByText('ALERT')).not.toBeInTheDocument();
  });

  it('applies custom className', () => {
    const { container } = render(
      <FeatureCard article={mockArticle} locale="ro" className="custom-card" />
    );
    const article = container.querySelector('article');
    expect(article?.className).toContain('custom-card');
  });

  it('renders without publishedAt gracefully', () => {
    const article = { ...mockArticle, publishedAt: undefined as any };
    render(<FeatureCard article={article} locale="ro" />);
    expect(screen.getByText('Feature Article Title')).toBeInTheDocument();
    // No time element if no publishedAt
    expect(screen.queryByRole('time')).not.toBeInTheDocument();
  });

  it('renders with English locale', () => {
    render(<FeatureCard article={mockArticle} locale="en" />);
    expect(screen.getByText('Feature Article Title')).toBeInTheDocument();
  });

  it('renders with Russian locale', () => {
    render(<FeatureCard article={mockArticle} locale="ru" />);
    expect(screen.getByText('Feature Article Title')).toBeInTheDocument();
  });

  it('renders without lead text falls back to content excerpt', () => {
    const article = { ...mockArticle, lead: undefined };
    render(<FeatureCard article={article} locale="ro" showExcerpt={true} />);
    // Should not crash
    expect(screen.getByText('Feature Article Title')).toBeInTheDocument();
  });

  it('renders with no category gracefully', () => {
    const article = { ...mockArticle, category: undefined as any };
    render(<FeatureCard article={article} locale="ro" />);
    expect(screen.getByText('Feature Article Title')).toBeInTheDocument();
  });
});
