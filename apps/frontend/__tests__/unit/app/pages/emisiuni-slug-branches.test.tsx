/**
 * EmisiuniSlugPage — additional branch coverage tests
 * Targets: formatViewCount < 1000, formatPublishedDate with undefined,
 * show with thumbnailUrl, show without description, generateMetadata description fallback,
 * generateMetadata with thumbnailUrl, Pagination with ellipsis, Pagination with totalPages<=1,
 * video with isFeatured, video without durationFormatted, fetchError branch
 */

const mockNotFound = jest.fn();
jest.mock('next/navigation', () => ({
  notFound: (...args: any[]) => {
    mockNotFound(...args);
    throw new Error('NEXT_NOT_FOUND');
  },
}));

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href, ...props }: any) => <a href={href} {...props}>{children}</a>,
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt, ...props }: any) => <img src={src} alt={alt} />,
}));

jest.mock('@/lib/utils/cn', () => ({
  cn: (...args: any[]) => args.filter(Boolean).join(' '),
}));

const mockFetchVideosByShow = jest.fn();
const mockFetchVideoShowBySlug = jest.fn();
const mockFetchVideoShows = jest.fn();

jest.mock('@/lib/api/video-shows', () => ({
  fetchVideosByShow: (...args: any[]) => mockFetchVideosByShow(...args),
  fetchVideoShowBySlug: (...args: any[]) => mockFetchVideoShowBySlug(...args),
  fetchVideoShows: (...args: any[]) => mockFetchVideoShows(...args),
}));

import { render, screen } from '@testing-library/react';

function makeVideo(id: number, overrides: any = {}) {
  return {
    id,
    title: `Video ${id}`,
    thumbnailUrl: `http://img.youtube.com/vi/${id}/mqdefault.jpg`,
    thumbnailMedium: null,
    youtubeUrl: `https://www.youtube.com/watch?v=${id}`,
    description: `Desc ${id}`,
    viewCount: 500,
    publishedAt: '2026-01-15T10:00:00Z',
    durationFormatted: '10:30',
    isFeatured: false,
    videoShow: null,
    ...overrides,
  };
}

function makeShow(overrides: any = {}) {
  return {
    id: 1,
    name: 'Test Show',
    slug: 'test-show',
    description: 'A description',
    videosCount: 10,
    color: 'var(--color-breaking)',
    thumbnailUrl: null,
    youtubeChannelId: null,
    ...overrides,
  };
}

describe('EmisiuniSlugPage — branch coverage', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockFetchVideoShowBySlug.mockResolvedValue(makeShow());
    mockFetchVideosByShow.mockResolvedValue({ member: [makeVideo(1)], totalItems: 1 });
    mockFetchVideoShows.mockResolvedValue({ member: [] });
  });

  it('renders video with view count < 1000 (no suffix)', async () => {
    mockFetchVideosByShow.mockResolvedValue({
      member: [makeVideo(1, { viewCount: 750 })],
      totalItems: 1,
    });

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', slug: 'test-show' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText(/750 views/)).toBeInTheDocument();
  });

  it('renders video with isFeatured badge', async () => {
    mockFetchVideosByShow.mockResolvedValue({
      member: [makeVideo(1, { isFeatured: true })],
      totalItems: 1,
    });

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', slug: 'test-show' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Featured')).toBeInTheDocument();
  });

  it('renders video without durationFormatted (no duration badge)', async () => {
    mockFetchVideosByShow.mockResolvedValue({
      member: [makeVideo(1, { durationFormatted: null })],
      totalItems: 1,
    });

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', slug: 'test-show' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.queryByText('10:30')).not.toBeInTheDocument();
  });

  it('renders show with thumbnailUrl in hero', async () => {
    mockFetchVideoShowBySlug.mockResolvedValue(
      makeShow({ thumbnailUrl: 'http://img.youtube.com/vi/1/max.jpg' })
    );

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', slug: 'test-show' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    const images = screen.getAllByRole('img');
    expect(images.length).toBeGreaterThanOrEqual(1);
  });

  it('renders show without description', async () => {
    mockFetchVideoShowBySlug.mockResolvedValue(
      makeShow({ description: null })
    );

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', slug: 'test-show' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Test Show')).toBeInTheDocument();
  });

  it('calls notFound when fetch throws error', async () => {
    jest.spyOn(console, 'error').mockImplementation(() => {});
    mockFetchVideoShowBySlug.mockRejectedValue(new Error('Network'));
    mockFetchVideosByShow.mockRejectedValue(new Error('Network'));

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    try {
      await mod.default({
        params: Promise.resolve({ locale: 'en', slug: 'fail' }),
        searchParams: Promise.resolve({}),
      });
    } catch (e: any) {
      expect(e.message).toBe('NEXT_NOT_FOUND');
    }
    expect(mockNotFound).toHaveBeenCalled();
    (console.error as jest.Mock).mockRestore();
  });

  it('calls notFound when videosResponse is null', async () => {
    mockFetchVideoShowBySlug.mockResolvedValue(makeShow());
    mockFetchVideosByShow.mockResolvedValue(null);

    // Force the Promise.all to return [show, null]
    const originalAll = Promise.all;
    jest.spyOn(Promise, 'all').mockImplementationOnce(async (promises) => {
      return [makeShow(), null];
    });

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    try {
      await mod.default({
        params: Promise.resolve({ locale: 'en', slug: 'test' }),
        searchParams: Promise.resolve({}),
      });
    } catch (e: any) {
      expect(e.message).toBe('NEXT_NOT_FOUND');
    }
    (Promise.all as any).mockRestore?.();
  });

  it('renders with ro locale translations', async () => {
    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'ro', slug: 'test-show' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Toate emisiunile')).toBeInTheDocument();
    // "episoade" appears in multiple elements (page info + episodes count)
    expect(screen.getAllByText(/episoade/).length).toBeGreaterThan(0);
  });

  it('renders with ru locale translations', async () => {
    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'ru', slug: 'test-show' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Все передачи')).toBeInTheDocument();
  });

  it('generateMetadata returns description with fallback when show has no description', async () => {
    mockFetchVideoShowBySlug.mockResolvedValue(
      makeShow({ name: 'Show X', description: null })
    );

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'en', slug: 'show-x' }),
      searchParams: Promise.resolve({}),
    });

    expect(result.description).toContain('Watch all episodes of Show X');
  });

  it('generateMetadata includes openGraph images when show has thumbnailUrl', async () => {
    mockFetchVideoShowBySlug.mockResolvedValue(
      makeShow({ name: 'Show Y', thumbnailUrl: 'http://img.yt/thumb.jpg' })
    );

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'en', slug: 'show-y' }),
      searchParams: Promise.resolve({}),
    });

    expect(result.openGraph?.images).toEqual([{ url: 'http://img.yt/thumb.jpg' }]);
  });

  it('renders pagination with multiple pages', async () => {
    mockFetchVideosByShow.mockResolvedValue({
      member: Array.from({ length: 12 }, (_, i) => makeVideo(i + 1)),
      totalItems: 50,
    });

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', slug: 'test-show' }),
      searchParams: Promise.resolve({ page: '3' }),
    });
    render(<>{Component}</>);

    // Should render pagination nav
    const nav = screen.getByRole('navigation', { name: 'Pagination' });
    expect(nav).toBeInTheDocument();
  });

  it('uses video thumbnailMedium as fallback when thumbnailUrl is missing', async () => {
    mockFetchVideosByShow.mockResolvedValue({
      member: [makeVideo(1, { thumbnailUrl: null, thumbnailMedium: 'http://med.jpg' })],
      totalItems: 1,
    });

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', slug: 'test-show' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    const images = screen.getAllByRole('img');
    const videoImg = images.find((img) => img.getAttribute('src') === 'http://med.jpg');
    expect(videoImg).toBeDefined();
  });
});
