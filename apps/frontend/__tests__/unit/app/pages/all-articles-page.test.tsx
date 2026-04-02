/**
 * Tests for the all-articles page generateMetadata
 */

jest.mock('next/navigation', () => ({
  notFound: jest.fn(),
  redirect: jest.fn(),
}));

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href, ...props }: any) => <a href={href} {...props}>{children}</a>,
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt, ...props }: any) => <img src={src} alt={alt} {...props} />,
}));

jest.mock('@/lib/api/categories', () => ({
  fetchCategories: jest.fn().mockResolvedValue({ member: [] }),
}));

jest.mock('@/lib/api/statistics', () => ({
  getTrendingArticles: jest.fn().mockResolvedValue([]),
}));

jest.mock('@/lib/api/important-articles', () => ({
  buildImageUrl: jest.fn((path: string) => `http://cdn/${path}`),
  getThumbnailByProfile: jest.fn(() => null),
  getFeaturedImage: jest.fn(() => null),
}));

jest.mock('@/lib/utils/url-builder', () => ({
  buildArticleUrl: jest.fn((article: any, locale: string) => `/${locale}/${article.slug}`),
}));

jest.mock('@/components/cards/utils', () => ({
  getSectionColor: jest.fn(() => 'var(--color-accent)'),
  getCategorySlugFromArticle: jest.fn(() => 'politica'),
}));

global.fetch = jest.fn().mockResolvedValue({
  ok: false,
  json: jest.fn().mockResolvedValue(null),
});

describe('AllArticlesPage', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('exports default function', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    expect(typeof mod.default).toBe('function');
  });

  it('exports generateMetadata', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    expect(typeof mod.generateMetadata).toBe('function');
  });

  it('generateMetadata returns ro title', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'ro' }),
      searchParams: Promise.resolve({}),
    });
    expect(result.title).toContain('Toate articolele');
  });

  it('generateMetadata returns en title', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    expect(result.title).toContain('All articles');
  });

  it('generateMetadata returns ru title', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'ru' }),
      searchParams: Promise.resolve({}),
    });
    expect(result.title).toContain('Vse stati');
  });

  it('generateMetadata falls back to ro for unknown locale', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'xx' }),
      searchParams: Promise.resolve({}),
    });
    expect(result.title).toContain('Toate articolele');
  });

  it('generateMetadata includes canonical alternates', async () => {
    const mod = await import('@/app/[locale]/(public)/all/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'ro' }),
      searchParams: Promise.resolve({}),
    });
    expect(result.alternates?.canonical).toBe('/ro/all');
  });
});
