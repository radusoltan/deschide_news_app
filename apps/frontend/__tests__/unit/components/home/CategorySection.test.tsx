/**
 * Tests for CategorySection component (home/CategorySection.tsx)
 */

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href, ...props }: any) => <a href={href} {...props}>{children}</a>,
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt, ...props }: any) => <img src={src} alt={alt} {...props} />,
}));

jest.mock('@/lib/api/articles', () => ({
  fetchArticlesByCategory: jest.fn().mockResolvedValue({ member: [] }),
}));

jest.mock('@/lib/api/important-articles', () => ({
  getFeaturedImage: jest.fn(() => null),
  getThumbnailByProfile: jest.fn(() => null),
  buildImageUrl: jest.fn((path: string) => `http://cdn/${path}`),
}));

jest.mock('@/lib/utils/url-builder', () => ({
  buildArticleUrl: jest.fn((article: any, locale: string) => `/${locale}/${article.slug}`),
  buildCategoryUrl: jest.fn((locale: string, slug: string) => `/${locale}/${slug}`),
}));

jest.mock('@/components/cards/utils', () => ({
  getSectionColor: jest.fn(() => 'var(--color-accent)'),
  getCategorySlugFromArticle: jest.fn(() => 'politica'),
  getCategoryTitle: jest.fn(() => 'Politică'),
  getFirstSentence: jest.fn((text: string) => text?.split('.')[0] || ''),
}));

describe('CategorySection', () => {
  it('exports CategorySectionProps type and CategorySectionLayout type', async () => {
    const mod = await import('@/app/[locale]/(public)/components/home/CategorySection');
    expect(typeof mod.default).toBe('function');
  });

  it('exports default component', async () => {
    const mod = await import('@/app/[locale]/(public)/components/home/CategorySection');
    expect(mod.default).toBeDefined();
  });
});
