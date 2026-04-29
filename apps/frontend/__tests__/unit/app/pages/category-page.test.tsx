/**
 * Tests for [categorySlug]/page.tsx — generateMetadata + internal helpers
 */

jest.mock('next/navigation', () => ({
  notFound: jest.fn(() => { throw new Error('NOTFOUND'); }),
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
  fetchCategories: jest.fn(),
}));

jest.mock('@/lib/api/articles', () => ({
  fetchArticlesByCategory: jest.fn(),
  fetchRelatedArticles: jest.fn(),
}));

jest.mock('@/lib/api/statistics', () => ({
  getTrendingArticles: jest.fn(),
}));

jest.mock('@/lib/constants/reserved-slugs', () => ({
  isReservedSlug: jest.fn(() => false),
}));

jest.mock('@/lib/seo/meta-tags', () => ({
  generateCategoryMetadata: jest.fn((title: string, slug: string, locale: string) => ({
    title: `${title} | Deschide`,
    description: `Category: ${slug}`,
  })),
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

import { fetchCategories } from '@/lib/api/categories';
import { isReservedSlug } from '@/lib/constants/reserved-slugs';
import { generateCategoryMetadata } from '@/lib/seo/meta-tags';

const mockFetchCategories = fetchCategories as jest.Mock;
const mockIsReservedSlug = isReservedSlug as jest.Mock;
const mockGenerateCategoryMetadata = generateCategoryMetadata as jest.Mock;

describe('CategoryPage — generateMetadata', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('exports default function', async () => {
    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    expect(typeof mod.default).toBe('function');
  });

  it('exports generateMetadata function', async () => {
    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    expect(typeof mod.generateMetadata).toBe('function');
  });

  it('returns Page Not Found title for reserved slug', async () => {
    mockIsReservedSlug.mockReturnValue(true);
    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'ro', categorySlug: 'api' }),
      searchParams: Promise.resolve({}),
    });
    expect(result.title).toBe('Page Not Found');
  });

  it('returns Category Not Found when category missing', async () => {
    mockIsReservedSlug.mockReturnValue(false);
    mockFetchCategories.mockResolvedValue({ member: [] });
    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'ro', categorySlug: 'unknown-cat' }),
      searchParams: Promise.resolve({}),
    });
    expect(result.title).toBe('Category Not Found');
  });

  it('returns category metadata when category found', async () => {
    mockIsReservedSlug.mockReturnValue(false);
    mockFetchCategories.mockResolvedValue({
      member: [{ id: 1, slug: 'politica', title: 'Politică', description: 'Politics' }],
    });
    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'ro', categorySlug: 'politica' }),
      searchParams: Promise.resolve({}),
    });
    expect(mockGenerateCategoryMetadata).toHaveBeenCalledWith(
      'Politică', 'politica', 'ro', 'Politics'
    );
  });

  it('falls back on fetch error', async () => {
    mockIsReservedSlug.mockReturnValue(false);
    mockFetchCategories.mockRejectedValue(new Error('Network error'));
    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'ro', categorySlug: 'politica' }),
      searchParams: Promise.resolve({}),
    });
    expect(result.title).toBe('Category Not Found');
  });

  it('normalizes unknown locale to ro', async () => {
    mockIsReservedSlug.mockReturnValue(false);
    mockFetchCategories.mockResolvedValue({
      member: [{ id: 1, slug: 'politica', title: 'Politică', description: '' }],
    });
    const mod = await import('@/app/[locale]/(public)/[categorySlug]/page');
    await mod.generateMetadata({
      params: Promise.resolve({ locale: 'xx', categorySlug: 'politica' }),
      searchParams: Promise.resolve({}),
    });
    // Should call fetchCategories with 'ro' (normalized)
    expect(mockFetchCategories).toHaveBeenCalledWith('ro');
  });
});
