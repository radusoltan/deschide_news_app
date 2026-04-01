/**
 * CompactCard Component Tests
 */

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { CompactCard } from '@/components/cards/CompactCard';

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
  default: ({ src, alt, fill, sizes, loading, ...rest }: any) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={src} alt={alt} {...rest} />
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
    typeof cat === 'object' && cat?.slug ? cat.slug : typeof cat === 'string' ? cat : '',
  formatRelativeTime: (date: string, locale: string) => '10 min',
}));

const mockArticle = {
  '@id': '/api/articles/2',
  '@type': 'Article' as const,
  id: 2,
  title: 'Compact Article Title',
  slug: 'compact-article',
  lead: 'Compact article lead',
  content: '<p>Some content</p>',
  category: { id: 1, title: 'Sport', slug: 'sport' },
  authors: [],
  articleImages: [],
  tags: [],
  status: 'published' as const,
  viewCount: 50,
  publishedAt: '2026-03-20T12:00:00Z',
  createdAt: '2026-03-20T12:00:00Z',
  updatedAt: '2026-03-20T12:00:00Z',
};

describe('CompactCard', () => {
  it('renders without crashing', () => {
    render(<CompactCard article={mockArticle} locale="ro" />);
    expect(screen.getByText('Compact Article Title')).toBeInTheDocument();
  });

  it('renders as a link', () => {
    render(<CompactCard article={mockArticle} locale="ro" />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/ro/sport/compact-article');
  });

  it('renders title in h4', () => {
    const { container } = render(<CompactCard article={mockArticle} locale="ro" />);
    const h4 = container.querySelector('h4');
    expect(h4).toBeInTheDocument();
    expect(h4?.textContent).toContain('Compact Article Title');
  });

  it('renders relative time', () => {
    render(<CompactCard article={mockArticle} locale="ro" />);
    expect(screen.getByRole('time')).toHaveTextContent('10 min');
  });

  it('renders without time when no publishedAt', () => {
    const article = { ...mockArticle, publishedAt: undefined as any };
    render(<CompactCard article={article} locale="ro" />);
    expect(screen.queryByRole('time')).not.toBeInTheDocument();
  });

  it('renders rank number when provided', () => {
    render(<CompactCard article={mockArticle} locale="ro" rank={3} />);
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('does not render rank when not provided', () => {
    render(<CompactCard article={mockArticle} locale="ro" />);
    expect(screen.queryByText('1')).not.toBeInTheDocument();
    expect(screen.queryByText('2')).not.toBeInTheDocument();
  });

  it('renders section color indicator when no rank and has category', () => {
    const { container } = render(<CompactCard article={mockArticle} locale="ro" />);
    // Color indicator div appears when no rank
    const colorDiv = container.querySelector('.w-8.h-0\\.5');
    expect(colorDiv).toBeInTheDocument();
  });

  it('does not render color indicator when rank is provided', () => {
    const { container } = render(<CompactCard article={mockArticle} locale="ro" rank={1} />);
    const colorDiv = container.querySelector('.w-8.h-0\\.5');
    expect(colorDiv).not.toBeInTheDocument();
  });

  it('renders thumbnail when image is provided', () => {
    const articleWithImage = {
      ...mockArticle,
      articleImages: [
        {
          id: 1,
          image: { path: 'images/compact.jpg', alt: 'Compact image' },
          position: 0,
          isFeatured: true,
        },
      ],
    };
    render(<CompactCard article={articleWithImage} locale="ro" />);
    const img = screen.getByRole('img');
    expect(img).toHaveAttribute('src', 'http://cdn.test/uploads/images/compact.jpg');
  });

  it('does not render thumbnail figure when no images', () => {
    const { container } = render(<CompactCard article={mockArticle} locale="ro" />);
    expect(container.querySelector('figure')).not.toBeInTheDocument();
  });

  it('applies custom className', () => {
    const { container } = render(
      <CompactCard article={mockArticle} locale="ro" className="my-custom-class" />
    );
    const article = container.querySelector('article');
    expect(article?.className).toContain('my-custom-class');
  });

  it('renders with English locale', () => {
    render(<CompactCard article={mockArticle} locale="en" />);
    expect(screen.getByText('Compact Article Title')).toBeInTheDocument();
  });
});
