/**
 * TextOnlyCard Component Tests
 */

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { TextOnlyCard } from '@/components/cards/TextOnlyCard';

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

jest.mock('@/lib/utils/url-builder', () => ({
  buildArticleUrl: (article: any, locale: string) =>
    `/${locale}/${article.category?.slug || 'news'}/${article.slug}`,
}));

jest.mock('@/components/cards/utils', () => ({
  getSectionColor: (slug: string) => 'var(--color-section-economy)',
  getCategorySlugFromArticle: (cat: any) =>
    typeof cat === 'object' && cat?.slug ? cat.slug : '',
  getCategoryTitle: (cat: any) =>
    typeof cat === 'object' && cat?.title ? cat.title : '',
  formatRelativeTime: (date: string, locale: string) => '15 min',
  getLocalizedBadgeText: (badge: any, locale: string) =>
    badge === 'breaking' ? 'BREAKING' : badge === 'alert' ? 'ALERT' : null,
  getFirstSentence: (text: string, max?: number) => 'First sentence from content.',
}));

const mockArticle = {
  '@id': '/api/articles/5',
  '@type': 'Article' as const,
  id: 5,
  title: 'Text Only Article Title',
  slug: 'text-only-article',
  lead: 'The lead text for this article.',
  content: '<p>First sentence from content. More text here.</p>',
  category: { id: 3, title: 'Economie', slug: 'economie' },
  authors: [{ id: 1, fullName: 'Ion Author', slug: 'ion-author' }],
  articleImages: [],
  tags: [],
  status: 'published' as const,
  viewCount: 200,
  publishedAt: '2026-03-20T09:00:00Z',
  createdAt: '2026-03-20T09:00:00Z',
  updatedAt: '2026-03-20T09:00:00Z',
  badge: undefined as any,
};

describe('TextOnlyCard', () => {
  it('renders without crashing', () => {
    render(<TextOnlyCard article={mockArticle} locale="ro" />);
    expect(screen.getByText('Text Only Article Title')).toBeInTheDocument();
  });

  it('renders as a link to the article', () => {
    render(<TextOnlyCard article={mockArticle} locale="ro" />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/ro/economie/text-only-article');
  });

  it('renders category title', () => {
    render(<TextOnlyCard article={mockArticle} locale="ro" />);
    expect(screen.getByText('Economie')).toBeInTheDocument();
  });

  it('renders relative time', () => {
    render(<TextOnlyCard article={mockArticle} locale="ro" />);
    expect(screen.getByRole('time')).toHaveTextContent('15 min');
  });

  it('does not render time when no publishedAt', () => {
    const article = { ...mockArticle, publishedAt: undefined as any };
    render(<TextOnlyCard article={article} locale="ro" />);
    expect(screen.queryByRole('time')).not.toBeInTheDocument();
  });

  it('renders lead text as excerpt', () => {
    render(<TextOnlyCard article={mockArticle} locale="ro" />);
    expect(screen.getByText('The lead text for this article.')).toBeInTheDocument();
  });

  it('renders content excerpt when no lead', () => {
    const article = { ...mockArticle, lead: undefined };
    render(<TextOnlyCard article={article} locale="ro" />);
    expect(screen.getByText('First sentence from content.')).toBeInTheDocument();
  });

  it('renders BREAKING badge when badge is set', () => {
    const article = { ...mockArticle, badge: 'breaking' as any };
    render(<TextOnlyCard article={article} locale="ro" />);
    expect(screen.getByText('BREAKING')).toBeInTheDocument();
  });

  it('renders ALERT badge when badge is alert', () => {
    const article = { ...mockArticle, badge: 'alert' as any };
    render(<TextOnlyCard article={article} locale="ro" />);
    expect(screen.getByText('ALERT')).toBeInTheDocument();
  });

  it('does not render badge when null', () => {
    render(<TextOnlyCard article={mockArticle} locale="ro" />);
    expect(screen.queryByText('BREAKING')).not.toBeInTheDocument();
  });

  it('renders author section when authorName and authorAvatar provided', () => {
    render(
      <TextOnlyCard
        article={mockArticle}
        locale="ro"
        authorName="Ion Author"
        authorAvatar="http://cdn.test/avatars/ion.jpg"
      />
    );
    expect(screen.getByText('Ion Author')).toBeInTheDocument();
    const img = screen.getByRole('img');
    expect(img).toHaveAttribute('src', 'http://cdn.test/avatars/ion.jpg');
  });

  it('renders author name without avatar', () => {
    render(
      <TextOnlyCard article={mockArticle} locale="ro" authorName="Ion Author" />
    );
    expect(screen.getByText('Ion Author')).toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('renders avatar without author name', () => {
    render(
      <TextOnlyCard
        article={mockArticle}
        locale="ro"
        authorAvatar="http://cdn.test/avatars/ion.jpg"
      />
    );
    const img = screen.getByRole('img');
    expect(img).toBeInTheDocument();
    // alt should fall back to 'Author'
    expect(img).toHaveAttribute('alt', 'Author');
  });

  it('does not render author section when neither authorName nor authorAvatar provided', () => {
    const { container } = render(
      <TextOnlyCard article={mockArticle} locale="ro" />
    );
    // No border-t separator for author section
    expect(container.querySelector('.border-t')).not.toBeInTheDocument();
  });

  it('applies custom className', () => {
    const { container } = render(
      <TextOnlyCard article={mockArticle} locale="ro" className="my-text-card" />
    );
    const article = container.querySelector('article');
    expect(article?.className).toContain('my-text-card');
  });

  it('renders title in h3', () => {
    const { container } = render(<TextOnlyCard article={mockArticle} locale="ro" />);
    const h3 = container.querySelector('h3');
    expect(h3?.textContent).toContain('Text Only Article Title');
  });
});
