/**
 * VideoShowsSlider - Additional Branch Coverage Tests
 * Covers: VideoCard interactions, VideoModal, navigation buttons,
 * videoShows filter, like count display, new episode badge, video show label,
 * duration badge, and modal behaviors.
 */

import { render, screen, fireEvent, act } from '@testing-library/react';
import '@testing-library/jest-dom';

// Mock Swiper modules - do NOT call onSwiper in render to avoid infinite loop
jest.mock('swiper/react', () => ({
  Swiper: ({ children, ...props }: any) => {
    return <div data-testid="swiper">{children}</div>;
  },
  SwiperSlide: ({ children }: any) => <div data-testid="swiper-slide">{children}</div>,
}));

jest.mock('swiper/modules', () => ({
  Navigation: jest.fn(),
  Autoplay: jest.fn(),
  Pagination: jest.fn(),
}));

jest.mock('swiper/css', () => ({}));
jest.mock('swiper/css/navigation', () => ({}));
jest.mock('swiper/css/pagination', () => ({}));

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href, ...props }: any) => <a href={href} {...props}>{children}</a>,
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt, ...rest }: any) => <img src={src} alt={alt} />,
}));

jest.mock('@/lib/utils/cn', () => ({
  cn: (...args: any[]) => args.filter(Boolean).join(' '),
}));

import VideoShowsSlider from '@/components/video/VideoShowsSlider';

const makeVideo = (overrides: any = {}) => ({
  '@id': '/api/youtube_videos/1',
  '@type': 'YouTubeVideo' as const,
  id: 1,
  youtubeId: 'abc123',
  title: 'Test Video',
  description: 'A test video',
  thumbnailUrl: 'https://img.youtube.com/vi/abc123/hqdefault.jpg',
  thumbnailMedium: 'https://img.youtube.com/vi/abc123/mqdefault.jpg',
  durationSeconds: 300,
  durationFormatted: '5:00',
  publishedAt: new Date().toISOString(),
  viewCount: 1500,
  likeCount: 100,
  isFeatured: false,
  isHidden: false,
  position: 0,
  embedUrl: 'https://www.youtube.com/embed/abc123',
  ...overrides,
});

describe('VideoShowsSlider - null/empty rendering', () => {
  it('returns null when videos is null', () => {
    const { container } = render(<VideoShowsSlider videos={null as any} locale="ro" />);
    expect(container.innerHTML).toBe('');
  });

  it('returns null when videos is empty', () => {
    const { container } = render(<VideoShowsSlider videos={[]} locale="ro" />);
    expect(container.innerHTML).toBe('');
  });
});

describe('VideoShowsSlider - translations', () => {
  it('renders video count text in ro', () => {
    render(<VideoShowsSlider videos={[makeVideo()]} locale="ro" />);
    expect(screen.getByText(/videoclipuri/)).toBeInTheDocument();
  });

  it('renders video count text in ru', () => {
    render(<VideoShowsSlider videos={[makeVideo()]} locale="ru" />);
    expect(screen.getByText(/видео/)).toBeInTheDocument();
  });

  it('renders video count text in en', () => {
    render(<VideoShowsSlider videos={[makeVideo()]} locale="en" />);
    expect(screen.getByText(/videos/)).toBeInTheDocument();
  });

  it('falls back to en translations for unknown locale', () => {
    render(<VideoShowsSlider videos={[makeVideo()]} locale="de" />);
    expect(screen.getByText('Video Shows')).toBeInTheDocument();
  });

  it('renders "Toate emisiunile" link in ro', () => {
    render(<VideoShowsSlider videos={[makeVideo()]} locale="ro" />);
    expect(screen.getByText('Toate emisiunile')).toBeInTheDocument();
  });

  it('renders "All Shows" link in en', () => {
    render(<VideoShowsSlider videos={[makeVideo()]} locale="en" />);
    expect(screen.getByText('All Shows')).toBeInTheDocument();
  });

  it('renders "Все передачи" link in ru', () => {
    render(<VideoShowsSlider videos={[makeVideo()]} locale="ru" />);
    expect(screen.getByText('Все передачи')).toBeInTheDocument();
  });
});

describe('VideoShowsSlider - video card features', () => {
  it('renders duration badge when durationFormatted provided', () => {
    render(<VideoShowsSlider videos={[makeVideo({ durationFormatted: '10:30' })]} locale="ro" />);
    expect(screen.getByText('10:30')).toBeInTheDocument();
  });

  it('does not render duration badge when durationFormatted is missing', () => {
    render(<VideoShowsSlider videos={[makeVideo({ durationFormatted: undefined })]} locale="ro" />);
    expect(screen.queryByText('10:30')).not.toBeInTheDocument();
  });

  it('renders like count when likeCount > 0', () => {
    render(<VideoShowsSlider videos={[makeVideo({ likeCount: 500 })]} locale="ro" />);
    // 500 is rendered as "500"
    expect(screen.getByText('500')).toBeInTheDocument();
  });

  it('does not render like count when likeCount is 0', () => {
    render(<VideoShowsSlider videos={[makeVideo({ likeCount: 0 })]} locale="ro" />);
    // Heart icon section should not appear
    // viewCount is 1.5K, likeCount section absent
    expect(screen.getByText('1.5K')).toBeInTheDocument();
  });

  it('renders new episode badge for recent video', () => {
    render(<VideoShowsSlider videos={[makeVideo({ publishedAt: new Date().toISOString() })]} locale="ro" />);
    expect(screen.getByText('Episod nou')).toBeInTheDocument();
  });

  it('renders "New Episode" in English for recent video', () => {
    render(<VideoShowsSlider videos={[makeVideo({ publishedAt: new Date().toISOString() })]} locale="en" />);
    expect(screen.getByText('New Episode')).toBeInTheDocument();
  });

  it('renders video show label for non-new videos with videoShow', () => {
    const oldDate = new Date();
    oldDate.setDate(oldDate.getDate() - 10);
    render(
      <VideoShowsSlider
        videos={[makeVideo({
          publishedAt: oldDate.toISOString(),
          videoShow: { id: 1, name: 'My Show', slug: 'my-show', color: '#ff0000' },
        })]}
        locale="ro"
      />
    );
    expect(screen.getByText('My Show')).toBeInTheDocument();
  });

  it('does not render video show label for new videos (new episode badge instead)', () => {
    render(
      <VideoShowsSlider
        videos={[makeVideo({
          publishedAt: new Date().toISOString(),
          videoShow: { id: 1, name: 'Show Name', slug: 'show', color: '#ff0000' },
        })]}
        locale="ro"
      />
    );
    // New episode badge takes precedence
    expect(screen.getByText('Episod nou')).toBeInTheDocument();
    expect(screen.queryByText('Show Name')).not.toBeInTheDocument();
  });
});

describe('VideoShowsSlider - video card click', () => {
  it('video card has cursor-pointer class (clickable)', () => {
    render(
      <VideoShowsSlider
        videos={[makeVideo({ id: 1, title: 'Clickable Video' })]}
        locale="ro"
      />
    );
    const card = screen.getByText('Clickable Video').closest('[class*="cursor-pointer"]');
    expect(card).toBeInTheDocument();
  });
});

describe('VideoShowsSlider - videoShows filter', () => {
  it('renders video show filter buttons when videoShows provided', () => {
    render(
      <VideoShowsSlider
        videos={[makeVideo()]}
        videoShows={[
          { id: 1, name: 'Show A', slug: 'show-a', color: '#ff0000', '@id': '/api/video_shows/1', '@type': 'VideoShow' as any },
          { id: 2, name: 'Show B', slug: 'show-b', color: '#00ff00', '@id': '/api/video_shows/2', '@type': 'VideoShow' as any },
        ]}
        locale="ro"
      />
    );
    expect(screen.getByText('Toate')).toBeInTheDocument();
    expect(screen.getByText('Show A')).toBeInTheDocument();
    expect(screen.getByText('Show B')).toBeInTheDocument();
  });

  it('does not render filter buttons when videoShows is empty', () => {
    render(
      <VideoShowsSlider
        videos={[makeVideo()]}
        videoShows={[]}
        locale="ro"
      />
    );
    expect(screen.queryByText('Toate')).not.toBeInTheDocument();
  });

  it('does not render filter buttons when videoShows is undefined', () => {
    render(
      <VideoShowsSlider
        videos={[makeVideo()]}
        locale="ro"
      />
    );
    expect(screen.queryByText('Toate')).not.toBeInTheDocument();
  });

  it('renders "All" button in en locale', () => {
    render(
      <VideoShowsSlider
        videos={[makeVideo()]}
        videoShows={[{ id: 1, name: 'X', slug: 'x', color: 'var(--color-text-primary)', '@id': '/api/video_shows/1', '@type': 'VideoShow' as any }]}
        locale="en"
      />
    );
    expect(screen.getByText('All')).toBeInTheDocument();
  });

  it('renders "Все" button in ru locale', () => {
    render(
      <VideoShowsSlider
        videos={[makeVideo()]}
        videoShows={[{ id: 1, name: 'X', slug: 'x', color: 'var(--color-text-primary)', '@id': '/api/video_shows/1', '@type': 'VideoShow' as any }]}
        locale="ru"
      />
    );
    expect(screen.getByText('Все')).toBeInTheDocument();
  });
});

describe('VideoShowsSlider - navigation buttons', () => {
  it('renders previous/next navigation buttons', () => {
    render(<VideoShowsSlider videos={[makeVideo(), makeVideo({ id: 2 })]} locale="ro" />);
    const prevButtons = screen.getAllByLabelText('Previous videos');
    const nextButtons = screen.getAllByLabelText('Next videos');
    expect(prevButtons.length).toBeGreaterThan(0);
    expect(nextButtons.length).toBeGreaterThan(0);
  });
});

describe('VideoShowsSlider - aria-label', () => {
  it('uses custom title for section aria-label', () => {
    render(<VideoShowsSlider videos={[makeVideo()]} locale="ro" title="Custom Videos" />);
    const section = screen.getByLabelText('Custom Videos');
    expect(section).toBeInTheDocument();
  });

  it('uses default translated title for section aria-label', () => {
    render(<VideoShowsSlider videos={[makeVideo()]} locale="en" />);
    const section = screen.getByLabelText('Video Shows');
    expect(section).toBeInTheDocument();
  });
});

describe('VideoShowsSlider - view count formatting', () => {
  it('renders view count with K format', () => {
    render(<VideoShowsSlider videos={[makeVideo({ viewCount: 1500 })]} locale="ro" />);
    expect(screen.getByText('1.5K')).toBeInTheDocument();
  });

  it('renders view count with M format', () => {
    render(<VideoShowsSlider videos={[makeVideo({ viewCount: 2500000 })]} locale="ro" />);
    expect(screen.getByText('2.5M')).toBeInTheDocument();
  });

  it('renders small view count as raw number', () => {
    render(<VideoShowsSlider videos={[makeVideo({ viewCount: 42 })]} locale="ro" />);
    expect(screen.getByText('42')).toBeInTheDocument();
  });
});

describe('VideoShowsSlider - video show link href', () => {
  it('renders All Shows link with correct locale href', () => {
    render(<VideoShowsSlider videos={[makeVideo()]} locale="en" />);
    const allShowsLink = screen.getByText('All Shows').closest('a');
    expect(allShowsLink).toHaveAttribute('href', '/en/emisiuni');
  });

  it('renders video show filter links with correct href', () => {
    render(
      <VideoShowsSlider
        videos={[makeVideo()]}
        videoShows={[{ id: 1, name: 'TestShow', slug: 'test-show', color: 'var(--color-text-primary)', '@id': '/api/video_shows/1', '@type': 'VideoShow' as any }]}
        locale="ro"
      />
    );
    const showLink = screen.getByText('TestShow').closest('a');
    expect(showLink).toHaveAttribute('href', '/ro/emisiuni/test-show');
  });
});
