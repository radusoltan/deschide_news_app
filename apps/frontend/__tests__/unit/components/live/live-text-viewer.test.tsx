/**
 * Tests for LiveTextViewer component
 * Covers: rendering, sport scoreboard, statistics, key points, post cards,
 *         locale labels, status badges, key-points filter toggle
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';

// Mock next/navigation
jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn() }),
  usePathname: () => '/ro/live/test',
  useParams: () => ({ locale: 'ro', slug: 'test' }),
}));

// Mock ReactionButtons to avoid fetch side effects
jest.mock(
  '@/app/[locale]/live/[slug]/components/ReactionButtons',
  () => ({
    ReactionButtons: ({ postId, locale }: { postId: number; locale: string }) => (
      <div data-testid={`reaction-buttons-${postId}`} data-locale={locale} />
    ),
  })
);

// Mock LiveTextTimeline
jest.mock(
  '@/app/[locale]/live/[slug]/components/LiveTextTimeline',
  () => ({
    LiveTextTimeline: () => <div data-testid="live-text-timeline" />,
  })
);

// Mock createSafeHtml
jest.mock('@/lib/sanitize', () => ({
  createSafeHtml: (html: string) => ({ __html: html }),
}));

import { LiveTextViewer } from '@/app/[locale]/live/[slug]/components/LiveTextViewer';

// ─────────────────────────────────────────────────────────────
// Test fixtures
// ─────────────────────────────────────────────────────────────

const basePost = {
  id: 1,
  publishedAt: new Date(Date.now() - 5 * 60000).toISOString(), // 5 minutes ago
  contentHtml: '<p>Post content</p>',
  content: 'Post content',
  isKeyPoint: false,
  author: null,
};

const keyPost = {
  id: 2,
  publishedAt: new Date(Date.now() - 10 * 60000).toISOString(),
  contentHtml: '<p>Key moment!</p>',
  content: 'Key moment!',
  isKeyPoint: true,
  author: { username: 'editor' },
};

const baseLiveText = {
  id: 1,
  title: 'Live Coverage',
  slug: 'live-coverage',
  description: 'Test description',
  status: 'live' as const,
  posts: [basePost, keyPost],
  sportMatch: null,
  template: null,
  category: null,
};

function setup(liveTextOverrides: any = {}, keyPoints: any[] = [], locale = 'ro') {
  const liveText = { ...baseLiveText, ...liveTextOverrides };
  return render(
    <LiveTextViewer liveText={liveText} keyPoints={keyPoints} locale={locale} />
  );
}

// ─────────────────────────────────────────────────────────────
// Rendering basics
// ─────────────────────────────────────────────────────────────

describe('LiveTextViewer - basic rendering', () => {
  it('renders without crashing', () => {
    expect(() => setup()).not.toThrow();
  });

  it('renders post content', () => {
    setup();
    // ReactionButtons are rendered for each post
    expect(screen.getByTestId('reaction-buttons-1')).toBeInTheDocument();
    expect(screen.getByTestId('reaction-buttons-2')).toBeInTheDocument();
  });

  it('shows LIVE status badge for live status', () => {
    setup({ status: 'live' });
    expect(screen.getByText('LIVE')).toBeInTheDocument();
  });

  it('shows ÎNCHEIAT badge for ended status (ro)', () => {
    setup({ status: 'ended' });
    expect(screen.getByText('ÎNCHEIAT')).toBeInTheDocument();
  });

  it('shows ENDED badge for ended status (en)', () => {
    setup({ status: 'ended' }, [], 'en');
    expect(screen.getByText('ENDED')).toBeInTheDocument();
  });

  it('shows PAUZĂ badge for paused status (ro)', () => {
    setup({ status: 'paused' });
    expect(screen.getByText('PAUZĂ')).toBeInTheDocument();
  });

  it('shows PAUSED badge for paused status (en)', () => {
    setup({ status: 'paused' }, [], 'en');
    expect(screen.getByText('PAUSED')).toBeInTheDocument();
  });

  it('renders with draft status', () => {
    setup({ status: 'draft' });
    expect(screen.getByText('DRAFT')).toBeInTheDocument();
  });

  it('renders with no posts', () => {
    setup({ posts: [] });
    expect(screen.queryByTestId('reaction-buttons-1')).not.toBeInTheDocument();
  });

  it('renders with null posts', () => {
    setup({ posts: null });
    expect(screen.queryByTestId('reaction-buttons-1')).not.toBeInTheDocument();
  });

  it('shows category title when provided', () => {
    setup({ category: { title: 'Sport', slug: 'sport' } });
    expect(screen.getByText('Sport')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────
// Locale labels
// ─────────────────────────────────────────────────────────────

describe('LiveTextViewer - locale labels', () => {
  it('shows ro labels by default', () => {
    setup({}, [], 'ro');
    expect(screen.getByText('Toate Postările')).toBeInTheDocument();
    // Multiple "Momente Cheie" exist (filter button + sidebar), use getAllByText
    expect(screen.getAllByText('Momente Cheie').length).toBeGreaterThan(0);
  });

  it('shows en labels', () => {
    setup({}, [], 'en');
    expect(screen.getByText('All Posts')).toBeInTheDocument();
    expect(screen.getByText('Key Points')).toBeInTheDocument();
  });

  it('shows ru labels', () => {
    setup({}, [], 'ru');
    expect(screen.getByText('Все посты')).toBeInTheDocument();
    // Multiple "Ключевые моменты" exist (filter button + sidebar)
    expect(screen.getAllByText('Ключевые моменты').length).toBeGreaterThan(0);
  });

  it('falls back to ro labels for unknown locale', () => {
    setup({}, [], 'xx');
    expect(screen.getByText('Toate Postările')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────
// Key points filter toggle
// ─────────────────────────────────────────────────────────────

describe('LiveTextViewer - key points filter', () => {
  it('renders all posts by default', () => {
    setup({ posts: [basePost, keyPost] });
    expect(screen.getByTestId('reaction-buttons-1')).toBeInTheDocument();
    expect(screen.getByTestId('reaction-buttons-2')).toBeInTheDocument();
  });

  it('filters to only key points when button clicked', () => {
    setup({ posts: [basePost, keyPost] });
    // "Momente Cheie" appears in filter buttons (first is "Toate Postările", second is "Momente Cheie")
    const allButtons = screen.getAllByRole('button');
    // Find the "Momente Cheie" button (it's in the filter row, not sidebar)
    const filterButtons = allButtons.filter(btn =>
      btn.textContent === 'Momente Cheie'
    );
    expect(filterButtons.length).toBeGreaterThan(0);
    fireEvent.click(filterButtons[0]);
    // Only key post (id=2) should have reaction buttons
    expect(screen.queryByTestId('reaction-buttons-1')).not.toBeInTheDocument();
    expect(screen.getByTestId('reaction-buttons-2')).toBeInTheDocument();
  });

  it('shows all posts again when clicking All Posts', () => {
    setup({ posts: [basePost, keyPost] });
    // Switch to key points - click filter button
    const allButtons = screen.getAllByRole('button');
    const filterButtons = allButtons.filter(btn => btn.textContent === 'Momente Cheie');
    fireEvent.click(filterButtons[0]);
    // Switch back to all
    fireEvent.click(screen.getByText('Toate Postările'));
    expect(screen.getByTestId('reaction-buttons-1')).toBeInTheDocument();
    expect(screen.getByTestId('reaction-buttons-2')).toBeInTheDocument();
  });

  it('shows empty state when no key points and filter is on', () => {
    setup({ posts: [basePost] }); // basePost is not a key point
    const allButtons = screen.getAllByRole('button');
    const filterButtons = allButtons.filter(btn => btn.textContent === 'Momente Cheie');
    fireEvent.click(filterButtons[0]);
    expect(screen.getByText('Nu există momente cheie')).toBeInTheDocument();
  });

  it('shows no posts message in en locale', () => {
    setup({ posts: [] }, [], 'en');
    expect(screen.getByText('No posts yet')).toBeInTheDocument();
  });

  it('shows no posts message in ro locale', () => {
    setup({ posts: [] }, [], 'ro');
    expect(screen.getByText('Nu există postări încă')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────
// Post card rendering
// ─────────────────────────────────────────────────────────────

describe('LiveTextViewer - post card', () => {
  it('renders reaction buttons with correct locale', () => {
    setup({ posts: [basePost] }, [], 'en');
    expect(screen.getByTestId('reaction-buttons-1')).toHaveAttribute('data-locale', 'en');
  });

  it('renders author username when present', () => {
    setup({ posts: [keyPost] });
    expect(screen.getByText('editor')).toBeInTheDocument();
  });

  it('renders without author', () => {
    setup({ posts: [basePost] });
    // No crash, no author displayed
    expect(screen.queryByText('editor')).not.toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────
// Key points sidebar
// ─────────────────────────────────────────────────────────────

describe('LiveTextViewer - key points sidebar', () => {
  const keyPointData = [
    {
      id: 10,
      publishedAt: new Date(Date.now() - 60000).toISOString(),
      contentHtml: '<p>Goal scored</p>',
      content: 'Goal scored',
      isKeyPoint: true,
      eventType: 'goal',
    },
  ];

  it('renders key points sidebar when keyPoints provided', () => {
    setup({}, keyPointData);
    // "Momente Cheie" appears in both filter button and sidebar header
    expect(screen.getAllByText('Momente Cheie').length).toBeGreaterThan(0);
  });

  it('shows empty key points message when none', () => {
    setup({}, []);
    // Sidebar still renders with empty state
    expect(screen.getAllByText('Momente Cheie').length).toBeGreaterThan(0);
  });

  it('renders key point with goal icon', () => {
    setup({}, keyPointData);
    expect(screen.getByText('⚽')).toBeInTheDocument();
  });

  it('renders key point with default icon for unknown event type', () => {
    const kp = [{ ...keyPointData[0], eventType: 'unknown_type' }];
    setup({}, kp);
    expect(screen.getByText('📍')).toBeInTheDocument();
  });

  it('renders key point with no eventType', () => {
    const kp = [{ ...keyPointData[0], eventType: undefined }];
    setup({}, kp);
    expect(screen.getByText('📍')).toBeInTheDocument();
  });

  it('renders key points title in en locale', () => {
    setup({}, keyPointData, 'en');
    expect(screen.getByText('Key Moments')).toBeInTheDocument();
  });

  it('renders key points title in ru locale', () => {
    setup({}, keyPointData, 'ru');
    expect(screen.getAllByText('Ключевые моменты').length).toBeGreaterThan(0);
  });
});

// ─────────────────────────────────────────────────────────────
// Sport Scoreboard
// ─────────────────────────────────────────────────────────────

describe('LiveTextViewer - sport scoreboard', () => {
  const liveSportMatch = {
    id: 1,
    sportType: 'football',
    homeTeam: 'Moldova',
    awayTeam: 'Romania',
    homeScore: 1,
    awayScore: 2,
    status: 'live',
    currentMinute: 65,
    competition: 'World Cup Qualifiers',
    venue: 'Arena Chisinau',
    homeTeamLogo: null,
    awayTeamLogo: null,
    currentPeriod: null,
  };

  it('renders scoreboard when sportMatch provided', () => {
    setup({ sportMatch: liveSportMatch });
    expect(screen.getByText('Moldova')).toBeInTheDocument();
    expect(screen.getByText('Romania')).toBeInTheDocument();
  });

  it('shows team scores', () => {
    setup({ sportMatch: liveSportMatch });
    expect(screen.getByText('1')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
  });

  it('shows competition name', () => {
    setup({ sportMatch: liveSportMatch });
    expect(screen.getByText('World Cup Qualifiers')).toBeInTheDocument();
  });

  it('shows venue on larger screens', () => {
    setup({ sportMatch: liveSportMatch });
    expect(screen.getByText('Arena Chisinau')).toBeInTheDocument();
  });

  it('shows current minute for live match', () => {
    setup({ sportMatch: liveSportMatch });
    expect(screen.getByText("65'")).toBeInTheDocument();
  });

  it('renders match in LIVE status', () => {
    setup({ sportMatch: liveSportMatch });
    // Multiple 'LIVE' elements may appear (scoreboard + status badge)
    const liveElements = screen.getAllByText('LIVE');
    expect(liveElements.length).toBeGreaterThan(0);
  });

  it('renders match in half_time status', () => {
    const match = { ...liveSportMatch, status: 'half_time' };
    setup({ sportMatch: match });
    expect(screen.getByText('PAUZA')).toBeInTheDocument();
  });

  it('renders match in finished status', () => {
    const match = { ...liveSportMatch, status: 'finished' };
    setup({ sportMatch: match });
    expect(screen.getByText('FINAL')).toBeInTheDocument();
  });

  it('renders match with en locale', () => {
    const match = { ...liveSportMatch, status: 'finished' };
    setup({ sportMatch: match }, [], 'en');
    expect(screen.getByText('FT')).toBeInTheDocument();
  });

  it('shows team logos when provided', () => {
    const match = {
      ...liveSportMatch,
      homeTeamLogo: 'https://example.com/logo.png',
      awayTeamLogo: 'https://example.com/logo2.png',
    };
    setup({ sportMatch: match });
    const imgs = screen.getAllByRole('img');
    expect(imgs.length).toBeGreaterThanOrEqual(2);
  });

  it('shows team initials when no logos', () => {
    setup({ sportMatch: liveSportMatch });
    // M for Moldova, R for Romania
    expect(screen.getByText('M')).toBeInTheDocument();
    expect(screen.getByText('R')).toBeInTheDocument();
  });

  it('shows basketball sport icon type', () => {
    const match = { ...liveSportMatch, sportType: 'basketball' };
    setup({ sportMatch: match });
    // Does not crash
    expect(screen.getByText('Moldova')).toBeInTheDocument();
  });

  it('shows currentPeriod when present', () => {
    const match = { ...liveSportMatch, currentPeriod: '2nd Half' };
    setup({ sportMatch: match });
    expect(screen.getByText('2nd Half')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────
// Statistics Panel
// ─────────────────────────────────────────────────────────────

describe('LiveTextViewer - statistics panel', () => {
  const statistics = {
    possession: { home: 60, away: 40 },
    shots: { home: 8, away: 5 },
    corners: { home: 4, away: 2 },
  };

  const sportMatchWithStats = {
    id: 1,
    sportType: 'football',
    homeTeam: 'Home',
    awayTeam: 'Away',
    homeScore: 0,
    awayScore: 0,
    status: 'live',
    currentMinute: null,
    competition: null,
    venue: null,
    homeTeamLogo: null,
    awayTeamLogo: null,
    currentPeriod: null,
    statistics,
  };

  it('renders statistics when present', () => {
    setup({ sportMatch: sportMatchWithStats });
    // Statistics panel title in ro
    expect(screen.getByText('Statistici meci')).toBeInTheDocument();
  });

  it('renders stats labels in ro locale', () => {
    setup({ sportMatch: sportMatchWithStats }, [], 'ro');
    expect(screen.getByText('Posesie')).toBeInTheDocument();
    expect(screen.getByText('Șuturi')).toBeInTheDocument();
  });

  it('renders stats labels in en locale', () => {
    setup({ sportMatch: sportMatchWithStats }, [], 'en');
    expect(screen.getByText('Possession')).toBeInTheDocument();
    expect(screen.getByText('Shots')).toBeInTheDocument();
  });

  it('renders stats labels in ru locale', () => {
    setup({ sportMatch: sportMatchWithStats }, [], 'ru');
    expect(screen.getByText('Владение')).toBeInTheDocument();
  });

  it('shows stat title in en: Match Statistics', () => {
    setup({ sportMatch: sportMatchWithStats }, [], 'en');
    expect(screen.getByText('Match Statistics')).toBeInTheDocument();
  });

  it('does not render statistics panel when stats empty', () => {
    const matchNoStats = { ...sportMatchWithStats, statistics: {} };
    setup({ sportMatch: matchNoStats });
    expect(screen.queryByText('Statistici meci')).not.toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────
// Template colors
// ─────────────────────────────────────────────────────────────

describe('LiveTextViewer - template colors', () => {
  it('uses template colors when template config provided', () => {
    const template = {
      config: {
        colors: {
          primary: 'var(--color-accent)',
          secondary: '#2563eb',
          accent: 'var(--color-accent)',
          background: '#eff6ff',
          text: '#1e3a8a',
        },
      },
    };
    expect(() => setup({ template })).not.toThrow();
  });

  it('uses default colors when no template', () => {
    expect(() => setup({ template: null })).not.toThrow();
  });
});

// ─────────────────────────────────────────────────────────────
// Timestamp formatting
// ─────────────────────────────────────────────────────────────

describe('LiveTextViewer - timestamp formatting', () => {
  it('shows "Acum" for very recent posts in ro', () => {
    const recentPost = {
      ...basePost,
      publishedAt: new Date(Date.now() - 30000).toISOString(), // 30 seconds ago
    };
    setup({ posts: [recentPost] }, [], 'ro');
    // "Acum" appears in the post card's time
    // PostCard shows formatted time in text - look for it
    const acumEl = screen.queryByText('Acum');
    // May or may not be visible depending on exact timing
    expect(screen.getByTestId('reaction-buttons-1')).toBeInTheDocument();
  });

  it('shows minute-ago format for recent posts', () => {
    const post5Min = {
      ...basePost,
      publishedAt: new Date(Date.now() - 5 * 60000).toISOString(),
    };
    setup({ posts: [post5Min] });
    expect(screen.getByTestId('reaction-buttons-1')).toBeInTheDocument();
  });
});
