/**
 * All Articles Page Tests (server component)
 */

jest.mock('next/headers', () => ({
  cookies: () => ({ get: jest.fn() }),
  headers: () => new Map([['accept-language', 'ro']]),
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt, ...rest }: any) => <img src={src} alt={alt} />,
}));

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href, ...props }: any) => <a href={href} {...props}>{children}</a>,
}));

jest.mock('@/lib/api/categories', () => ({
  fetchCategories: jest.fn().mockResolvedValue([]),
}));

jest.mock('@/lib/api/statistics', () => ({
  getTrendingArticles: jest.fn().mockResolvedValue([]),
}));

jest.mock('@/lib/api/important-articles', () => ({
  buildImageUrl: jest.fn(() => 'http://test.com/image.jpg'),
  getThumbnailByProfile: jest.fn(() => null),
  getFeaturedImage: jest.fn(() => null),
}));

jest.mock('@/lib/utils/url-builder', () => ({
  buildArticleUrl: jest.fn((locale: string, category: string, slug: string) => `/${locale}/${category}/${slug}`),
}));

jest.mock('@/components/cards/utils', () => ({
  getSectionColor: jest.fn(() => 'var(--color-text-primary)'),
  getCategorySlugFromArticle: jest.fn(() => 'general'),
}));

describe('AllArticlesPage (server component)', () => {
  it('exports default function', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    expect(typeof mod.default).toBe('function');
  });

  it('exports generateMetadata', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    expect(typeof mod.generateMetadata).toBe('function');
  });

  it('generateMetadata returns title for ro', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const meta = await mod.generateMetadata({ params: Promise.resolve({ locale: 'ro' }), searchParams: Promise.resolve({}) });
    expect(meta.title).toContain('Toate articolele');
  });

  it('generateMetadata returns title for en', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const meta = await mod.generateMetadata({ params: Promise.resolve({ locale: 'en' }), searchParams: Promise.resolve({}) });
    expect(meta.title).toContain('All articles');
  });

  it('generateMetadata includes alternates', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const meta = await mod.generateMetadata({ params: Promise.resolve({ locale: 'ro' }), searchParams: Promise.resolve({}) });
    expect(meta.alternates).toBeDefined();
  });

  it('generateMetadata falls back to ro for unknown locale', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const meta = await mod.generateMetadata({ params: Promise.resolve({ locale: 'fr' }), searchParams: Promise.resolve({}) });
    expect(meta.title).toContain('Toate articolele');
  });
});
