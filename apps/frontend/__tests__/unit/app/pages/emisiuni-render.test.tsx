/**
 * Tests for emisiuni/page.tsx and emisiuni/[slug]/page.tsx — rendering branches
 * Covers: video grid, pagination, shows filter, empty state, formatViewCount, formatPublishedDate
 */

const mockNotFound = jest.fn();
jest.mock('next/navigation', () => ({
  notFound: (...args: any[]) => { mockNotFound(...args); throw new Error('NEXT_NOT_FOUND'); },
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

jest.mock('@/lib/api/video-shows', () => ({
  fetchAllVideos: jest.fn(),
  fetchVideoShows: jest.fn(),
  fetchVideosByShow: jest.fn(),
  fetchVideoShowBySlug: jest.fn(),
}));

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { fetchAllVideos, fetchVideoShows, fetchVideosByShow, fetchVideoShowBySlug } from '@/lib/api/video-shows';

const mockFetchAllVideos = fetchAllVideos as jest.Mock;
const mockFetchVideoShows = fetchVideoShows as jest.Mock;
const mockFetchVideosByShow = fetchVideosByShow as jest.Mock;
const mockFetchVideoShowBySlug = fetchVideoShowBySlug as jest.Mock;

function makeVideo(id: number, overrides: any = {}) {
  return {
    id,
    title: `Video ${id}`,
    thumbnailUrl: `http://img.youtube.com/vi/${id}/mqdefault.jpg`,
    thumbnailMedium: null,
    youtubeUrl: `https://www.youtube.com/watch?v=${id}`,
    description: `Description for video ${id}`,
    viewCount: 1500,
    publishedAt: '2026-01-15T10:00:00Z',
    durationFormatted: '10:30',
    isFeatured: false,
    videoShow: null,
    ...overrides,
  };
}

function makeShow(id: number, overrides: any = {}) {
  return {
    id,
    name: `Show ${id}`,
    slug: `show-${id}`,
    description: `Description for show ${id}`,
    videosCount: 10,
    color: 'var(--color-breaking)',
    thumbnailUrl: null,
    youtubeChannelId: null,
    ...overrides,
  };
}

describe('EmisiuniPage rendering', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockFetchAllVideos.mockResolvedValue({ member: [], totalItems: 0 });
    mockFetchVideoShows.mockResolvedValue({ member: [] });
  });

  it('renders video grid when videos exist', async () => {
    mockFetchAllVideos.mockResolvedValue({
      member: [makeVideo(1), makeVideo(2)],
      totalItems: 2,
    });
    mockFetchVideoShows.mockResolvedValue({ member: [] });

    const mod = await import('@/app/[locale]/(public)/emisiuni/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Video 1')).toBeInTheDocument();
    expect(screen.getByText('Video 2')).toBeInTheDocument();
  });

  it('renders empty state when no videos', async () => {
    const mod = await import('@/app/[locale]/(public)/emisiuni/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('No videos available at the moment.')).toBeInTheDocument();
  });

  it('renders page title', async () => {
    const mod = await import('@/app/[locale]/(public)/emisiuni/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Video Shows')).toBeInTheDocument();
  });

  it('renders show filter pills when shows exist', async () => {
    mockFetchVideoShows.mockResolvedValue({
      member: [makeShow(1, { name: 'Morning Show' }), makeShow(2, { name: 'Evening News' })],
    });

    const mod = await import('@/app/[locale]/(public)/emisiuni/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('All Shows')).toBeInTheDocument();
    expect(screen.getByText(/Morning Show/)).toBeInTheDocument();
    expect(screen.getByText(/Evening News/)).toBeInTheDocument();
  });

  it('renders pagination when totalPages > 1', async () => {
    mockFetchAllVideos.mockResolvedValue({
      member: [makeVideo(1)],
      totalItems: 25,
    });
    mockFetchVideoShows.mockResolvedValue({ member: [] });

    const mod = await import('@/app/[locale]/(public)/emisiuni/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({ page: '1' }),
    });
    render(<>{Component}</>);

    const nav = screen.getByRole('navigation', { name: 'Pagination' });
    expect(nav).toBeInTheDocument();
  });

  it('renders page info text', async () => {
    mockFetchAllVideos.mockResolvedValue({
      member: [makeVideo(1)],
      totalItems: 25,
    });
    mockFetchVideoShows.mockResolvedValue({ member: [] });

    const mod = await import('@/app/[locale]/(public)/emisiuni/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText(/Page 1 of/)).toBeInTheDocument();
  });

  it('formats view count with K suffix', async () => {
    mockFetchAllVideos.mockResolvedValue({
      member: [makeVideo(1, { viewCount: 5500 })],
      totalItems: 1,
    });
    mockFetchVideoShows.mockResolvedValue({ member: [] });

    const mod = await import('@/app/[locale]/(public)/emisiuni/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText(/5.5K views/)).toBeInTheDocument();
  });

  it('formats view count with M suffix for millions', async () => {
    mockFetchAllVideos.mockResolvedValue({
      member: [makeVideo(1, { viewCount: 2500000 })],
      totalItems: 1,
    });
    mockFetchVideoShows.mockResolvedValue({ member: [] });

    const mod = await import('@/app/[locale]/(public)/emisiuni/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText(/2.5M views/)).toBeInTheDocument();
  });

  it('renders duration badge on video card', async () => {
    mockFetchAllVideos.mockResolvedValue({
      member: [makeVideo(1, { durationFormatted: '15:42' })],
      totalItems: 1,
    });
    mockFetchVideoShows.mockResolvedValue({ member: [] });

    const mod = await import('@/app/[locale]/(public)/emisiuni/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('15:42')).toBeInTheDocument();
  });

  it('renders video show badge when video has show', async () => {
    mockFetchAllVideos.mockResolvedValue({
      member: [makeVideo(1, { videoShow: { name: 'Daily Report', color: '#ff0000' } })],
      totalItems: 1,
    });
    mockFetchVideoShows.mockResolvedValue({ member: [] });

    const mod = await import('@/app/[locale]/(public)/emisiuni/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Daily Report')).toBeInTheDocument();
  });

  it('calls notFound on fetch error', async () => {
    mockFetchAllVideos.mockRejectedValue(new Error('Network'));
    jest.spyOn(console, 'error').mockImplementation(() => {});

    const mod = await import('@/app/[locale]/(public)/emisiuni/page');
    try {
      await mod.default({
        params: Promise.resolve({ locale: 'en' }),
        searchParams: Promise.resolve({}),
      });
    } catch (e: any) {
      expect(e.message).toBe('NEXT_NOT_FOUND');
    }
    (console.error as jest.Mock).mockRestore();
  });
});

describe('ShowPage rendering', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockFetchVideoShowBySlug.mockResolvedValue(makeShow(1, { name: 'Test Show', slug: 'test-show' }));
    mockFetchVideosByShow.mockResolvedValue({ member: [], totalItems: 0 });
    mockFetchVideoShows.mockResolvedValue({ member: [] });
  });

  it('renders show name as h1', async () => {
    mockFetchVideosByShow.mockResolvedValue({
      member: [makeVideo(1)],
      totalItems: 1,
    });

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', slug: 'test-show' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Test Show')).toBeInTheDocument();
  });

  it('renders back to all shows link', async () => {
    mockFetchVideosByShow.mockResolvedValue({
      member: [makeVideo(1)],
      totalItems: 1,
    });

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', slug: 'test-show' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('All Shows')).toBeInTheDocument();
  });

  it('renders empty state when no videos for show', async () => {
    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', slug: 'test-show' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('No videos available for this show.')).toBeInTheDocument();
  });

  it('calls notFound when show not found', async () => {
    mockFetchVideoShowBySlug.mockResolvedValue(null);
    mockFetchVideosByShow.mockResolvedValue({ member: [], totalItems: 0 });

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    try {
      await mod.default({
        params: Promise.resolve({ locale: 'en', slug: 'nonexistent' }),
        searchParams: Promise.resolve({}),
      });
    } catch (e: any) {
      expect(e.message).toBe('NEXT_NOT_FOUND');
    }
    expect(mockNotFound).toHaveBeenCalled();
  });

  it('renders YouTube subscribe button when show has channelId', async () => {
    mockFetchVideoShowBySlug.mockResolvedValue(
      makeShow(1, { name: 'Test', slug: 'test', youtubeChannelId: 'UC123' })
    );
    mockFetchVideosByShow.mockResolvedValue({ member: [makeVideo(1)], totalItems: 1 });

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', slug: 'test' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('Subscribe on YouTube')).toBeInTheDocument();
  });

  it('renders show description', async () => {
    mockFetchVideoShowBySlug.mockResolvedValue(
      makeShow(1, { name: 'Test', slug: 'test', description: 'A great show about news' })
    );
    mockFetchVideosByShow.mockResolvedValue({ member: [makeVideo(1)], totalItems: 1 });

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', slug: 'test' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('A great show about news')).toBeInTheDocument();
  });

  it('renders episodes count', async () => {
    mockFetchVideoShowBySlug.mockResolvedValue(
      makeShow(1, { name: 'Test', slug: 'test', videosCount: 42 })
    );
    mockFetchVideosByShow.mockResolvedValue({ member: [makeVideo(1)], totalItems: 1 });

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const Component = await mod.default({
      params: Promise.resolve({ locale: 'en', slug: 'test' }),
      searchParams: Promise.resolve({}),
    });
    render(<>{Component}</>);

    expect(screen.getByText('42')).toBeInTheDocument();
    expect(screen.getByText('episodes')).toBeInTheDocument();
  });

  it('generateMetadata returns show name', async () => {
    mockFetchVideoShowBySlug.mockResolvedValue(
      makeShow(1, { name: 'My Show', description: 'About my show' })
    );

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'en', slug: 'my-show' }),
      searchParams: Promise.resolve({}),
    });
    expect(result.title).toBe('My Show');
  });

  it('generateMetadata returns "Show Not Found" when show is null', async () => {
    mockFetchVideoShowBySlug.mockResolvedValue(null);

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const result = await mod.generateMetadata({
      params: Promise.resolve({ locale: 'en', slug: 'nonexistent' }),
      searchParams: Promise.resolve({}),
    });
    expect(result.title).toBe('Show Not Found');
  });

  it('generateStaticParams returns locales x shows', async () => {
    mockFetchVideoShows.mockResolvedValue({
      member: [makeShow(1, { slug: 'show-a' }), makeShow(2, { slug: 'show-b' })],
    });

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const params = await mod.generateStaticParams();
    expect(params.length).toBe(6); // 3 locales x 2 shows
    expect(params).toContainEqual({ locale: 'ro', slug: 'show-a' });
    expect(params).toContainEqual({ locale: 'en', slug: 'show-b' });
  });

  it('generateStaticParams returns empty on error', async () => {
    mockFetchVideoShows.mockRejectedValue(new Error('API error'));

    const mod = await import('@/app/[locale]/(public)/emisiuni/[slug]/page');
    const params = await mod.generateStaticParams();
    expect(params).toEqual([]);
  });
});
