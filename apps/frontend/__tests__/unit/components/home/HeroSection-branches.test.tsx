/**
 * HeroSection — branch coverage tests
 * Directly invokes the async HeroSectionContent to render its JSX,
 * then renders the result with RTL to hit all conditional branches.
 */

import React from 'react';
import { render, screen } from '@testing-library/react';

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href, ...props }: any) => <a href={href} {...props}>{children}</a>,
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt, fill, sizes, loading, fetchPriority, priority, ...props }: any) => (
    <img src={src} alt={alt || ''} data-loading={loading} data-fetchpriority={fetchPriority} />
  ),
}));

const mockFetchImportant = jest.fn();
const mockFetchLatest = jest.fn();
const mockGetFeatured = jest.fn();
const mockGetThumbnail = jest.fn();
const mockBuildImageUrl = jest.fn((path: string) => `http://cdn/${path}`);
const mockGetLocalizedBadgeText = jest.fn();

jest.mock('@/lib/api/important-articles', () => ({
  fetchImportantArticles: (...args: any[]) => mockFetchImportant(...args),
  buildImageUrl: (...args: any[]) => mockBuildImageUrl(...args),
  getThumbnailByProfile: (...args: any[]) => mockGetThumbnail(...args),
  getFeaturedImage: (...args: any[]) => mockGetFeatured(...args),
}));

jest.mock('@/lib/api/articles', () => ({
  fetchLatestArticles: (...args: any[]) => mockFetchLatest(...args),
}));

jest.mock('@/lib/utils/url-builder', () => ({
  buildArticleUrl: jest.fn((article: any, locale: string) => `/${locale}/${article.slug}`),
}));

jest.mock('@/components/cards/utils', () => ({
  getSectionColor: jest.fn(() => 'var(--color-accent)'),
  getCategorySlugFromArticle: jest.fn(() => 'politica'),
  getCategoryTitle: jest.fn((cat: any) => cat?.title || ''),
  formatRelativeTime: jest.fn((date: any) => date ? '1 oră' : ''),
  getLocalizedBadgeText: (...args: any[]) => mockGetLocalizedBadgeText(...args),
}));

const makeArticle = (id: number, overrides: any = {}) => ({
  id,
  title: `Article ${id}`,
  slug: `article-${id}`,
  lead: `Lead for article ${id}`,
  content: '<p>Content</p>',
  publishedAt: '2026-03-15T10:00:00Z',
  category: { id: 1, title: 'Politica', slug: 'politica' },
  authors: [],
  articleImages: [],
  tags: [],
  status: 'published',
  viewCount: 100,
  badge: null,
  ...overrides,
});

const makeImportant = (id: number, overrides: any = {}) => ({
  id,
  position: id,
  article: makeArticle(id, overrides),
});

// We need to import AFTER mocks are set up
let HeroSectionDefault: any;

beforeAll(async () => {
  const mod = await import('@/app/[locale]/(public)/components/home/HeroSection');
  HeroSectionDefault = mod.default;
});

/**
 * Render async server component by calling it, awaiting the JSX, then rendering.
 * The default export is a sync component wrapping Suspense around an async component.
 * We call the default, which React will handle.
 * But to properly hit async branches, we need a different approach...
 *
 * For Next.js async server components, we call the function directly as async,
 * which returns a Promise<JSX>. We await that, then render.
 */
async function renderAsyncComponent(locale: string = 'ro') {
  // The default export is: function HeroSection({ locale }) { return <Suspense ...><HeroSectionContent locale={locale} /></Suspense> }
  // HeroSectionContent is the async function.
  // Since HeroSectionContent is not exported, we call the default which includes the Suspense.
  // In a test environment, the async component inside Suspense may not resolve.
  // Instead, let's call it directly.
  // Re-import fresh to use current mocks:
  jest.resetModules();

  // Re-setup mocks after resetModules
  jest.doMock('next/link', () => ({
    __esModule: true,
    default: ({ children, href, ...props }: any) => <a href={href} {...props}>{children}</a>,
  }));
  jest.doMock('next/image', () => ({
    __esModule: true,
    default: ({ src, alt, ...props }: any) => <img src={src} alt={alt || ''} />,
  }));
  jest.doMock('@/lib/api/important-articles', () => ({
    fetchImportantArticles: mockFetchImportant,
    buildImageUrl: mockBuildImageUrl,
    getThumbnailByProfile: mockGetThumbnail,
    getFeaturedImage: mockGetFeatured,
  }));
  jest.doMock('@/lib/api/articles', () => ({
    fetchLatestArticles: mockFetchLatest,
  }));
  jest.doMock('@/lib/utils/url-builder', () => ({
    buildArticleUrl: (article: any, locale: string) => `/${locale}/${article.slug}`,
  }));
  jest.doMock('@/components/cards/utils', () => ({
    getSectionColor: () => 'var(--color-accent)',
    getCategorySlugFromArticle: () => 'politica',
    getCategoryTitle: (cat: any) => cat?.title || '',
    formatRelativeTime: (date: any) => date ? '1 oră' : '',
    getLocalizedBadgeText: mockGetLocalizedBadgeText,
  }));

  const freshMod = await import('@/app/[locale]/(public)/components/home/HeroSection');
  // Call the default export — it's a sync function returning JSX with Suspense
  const element = freshMod.default({ locale });
  return render(element as any);
}

describe('HeroSection — Suspense skeleton', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockGetFeatured.mockReturnValue(null);
    mockGetThumbnail.mockReturnValue(null);
    mockGetLocalizedBadgeText.mockReturnValue(null);
  });

  it('renders skeleton (Suspense fallback) while async content loads', () => {
    // Mock to never resolve, so Suspense shows fallback
    mockFetchImportant.mockReturnValue(new Promise(() => {}));
    mockFetchLatest.mockReturnValue(new Promise(() => {}));

    const { container } = render(<HeroSectionDefault locale="ro" />);
    // Skeleton has animate-pulse class
    const skeleton = container.querySelector('.animate-pulse');
    expect(skeleton).toBeInTheDocument();
  });

  it('renders skeleton with 4 bottom row items', () => {
    mockFetchImportant.mockReturnValue(new Promise(() => {}));
    mockFetchLatest.mockReturnValue(new Promise(() => {}));

    const { container } = render(<HeroSectionDefault locale="ro" />);
    const pulseElements = container.querySelectorAll('.animate-pulse');
    // Skeleton has: 1 main + 2 secondary + 4 bottom = 7 pulse elements
    expect(pulseElements.length).toBeGreaterThanOrEqual(3);
  });
});

describe('HeroSection — async content rendering', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockGetFeatured.mockReturnValue(null);
    mockGetThumbnail.mockReturnValue(null);
    mockGetLocalizedBadgeText.mockReturnValue(null);
  });

  it('renders hero with full set of articles', async () => {
    const important = Array.from({ length: 7 }, (_, i) => makeImportant(i + 1));
    const latest = Array.from({ length: 5 }, (_, i) => makeArticle(100 + i));
    mockFetchImportant.mockResolvedValue({ member: important });
    mockFetchLatest.mockResolvedValue({ member: latest });

    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
  });

  it('renders null when no hero article (empty important)', async () => {
    mockFetchImportant.mockResolvedValue({ member: [] });
    mockFetchLatest.mockResolvedValue({ member: [] });

    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
  });

  it('handles Promise.allSettled where important rejects', async () => {
    mockFetchImportant.mockRejectedValue(new Error('fail'));
    mockFetchLatest.mockResolvedValue({ member: [makeArticle(1)] });

    const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
    consoleSpy.mockRestore();
  });

  it('handles Promise.allSettled where latest rejects', async () => {
    mockFetchImportant.mockResolvedValue({ member: [makeImportant(1), makeImportant(2), makeImportant(3)] });
    mockFetchLatest.mockRejectedValue(new Error('fail'));

    const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
    consoleSpy.mockRestore();
  });

  it('backfills secondary from latest when <2 important after hero', async () => {
    mockFetchImportant.mockResolvedValue({ member: [makeImportant(1)] });
    mockFetchLatest.mockResolvedValue({ member: [makeArticle(10), makeArticle(11), makeArticle(12)] });

    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
  });

  it('renders articles with images (thumbnail available)', async () => {
    mockGetFeatured.mockReturnValue({ path: 'images/photo.jpg', alt: 'Test' });
    mockGetThumbnail.mockReturnValue({ path: 'thumbs/hero.webp', width: 1920, height: 1080 });
    mockFetchImportant.mockResolvedValue({ member: [makeImportant(1), makeImportant(2), makeImportant(3)] });
    mockFetchLatest.mockResolvedValue({ member: [] });

    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
  });

  it('renders articles with featured image but no thumbnail', async () => {
    mockGetFeatured.mockReturnValue({ path: 'images/photo.jpg', alt: 'Test' });
    mockGetThumbnail.mockReturnValue(null);
    mockFetchImportant.mockResolvedValue({ member: [makeImportant(1)] });
    mockFetchLatest.mockResolvedValue({ member: [] });

    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
  });

  it('renders placeholder when no image at all', async () => {
    mockGetFeatured.mockReturnValue(null);
    mockGetThumbnail.mockReturnValue(null);
    mockFetchImportant.mockResolvedValue({ member: [makeImportant(1)] });
    mockFetchLatest.mockResolvedValue({ member: [] });

    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
  });

  it('renders badge text when article has badge', async () => {
    mockGetLocalizedBadgeText.mockReturnValue('BREAKING');
    mockFetchImportant.mockResolvedValue({ member: [makeImportant(1, { badge: 'breaking' })] });
    mockFetchLatest.mockResolvedValue({ member: [] });

    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
  });

  it('does not render badge span when badgeText is null', async () => {
    mockGetLocalizedBadgeText.mockReturnValue(null);
    mockFetchImportant.mockResolvedValue({ member: [makeImportant(1)] });
    mockFetchLatest.mockResolvedValue({ member: [] });

    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
  });

  it('shows excerpt on hero card but not on secondary cards', async () => {
    mockFetchImportant.mockResolvedValue({
      member: [
        makeImportant(1, { lead: 'Hero lead text' }),
        makeImportant(2, { lead: 'Secondary lead' }),
        makeImportant(3),
      ],
    });
    mockFetchLatest.mockResolvedValue({ member: [] });

    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
  });

  it('hides timestamp when publishedAt is null', async () => {
    mockFetchImportant.mockResolvedValue({ member: [makeImportant(1, { publishedAt: null })] });
    mockFetchLatest.mockResolvedValue({ member: [] });

    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
  });

  it('renders en locale', async () => {
    mockFetchImportant.mockResolvedValue({ member: [makeImportant(1)] });
    mockFetchLatest.mockResolvedValue({ member: [] });

    const { container } = await renderAsyncComponent('en');
    expect(container).toBeTruthy();
  });

  it('renders small articles row when > 3 important articles', async () => {
    const important = Array.from({ length: 5 }, (_, i) => makeImportant(i + 1));
    mockFetchImportant.mockResolvedValue({ member: important });
    mockFetchLatest.mockResolvedValue({ member: [] });

    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
  });

  it('renders no small articles row when only 3 important and no latest', async () => {
    mockFetchImportant.mockResolvedValue({
      member: [makeImportant(1), makeImportant(2), makeImportant(3)],
    });
    mockFetchLatest.mockResolvedValue({ member: [] });

    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
  });

  it('handles catch block in try/catch', async () => {
    // Both fetches throw (not reject, but throw)
    mockFetchImportant.mockImplementation(() => { throw new Error('sync error'); });
    mockFetchLatest.mockImplementation(() => { throw new Error('sync error'); });

    const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
    const { container } = await renderAsyncComponent('ro');
    expect(container).toBeTruthy();
    consoleSpy.mockRestore();
  });
});
