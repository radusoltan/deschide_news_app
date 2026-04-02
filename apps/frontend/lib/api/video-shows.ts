/**
 * Video Shows and YouTube Videos API Service
 */

import {
  VideoShow,
  VideoShowsListResponse,
  YouTubeVideo,
  YouTubeVideosListResponse,
} from '../types/video';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

// ============================================================================
// Video Shows API
// ============================================================================

/**
 * Fetch all active video shows
 * GET /api/video_shows
 *
 * @param locale - Language locale (ro, en, ru)
 * @returns All active video shows ordered by position
 */
export async function fetchVideoShows(
  locale?: string
): Promise<VideoShowsListResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/video_shows`);
  url.searchParams.set('isActive', 'true');
  url.searchParams.set('itemsPerPage', '50');
  url.searchParams.set('order[position]', 'asc');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 300, // Revalidate every 5 minutes
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch video shows: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch a single video show by slug
 * GET /api/video_shows?slug=xxx
 *
 * @param slug - Video show slug
 * @param locale - Language locale (ro, en, ru)
 * @returns Video show or null if not found
 */
export async function fetchVideoShowBySlug(
  slug: string,
  locale?: string
): Promise<VideoShow | null> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/video_shows`);
  url.searchParams.set('slug', slug);

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 300,
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch video show: ${response.status} ${response.statusText}`
    );
  }

  const data: VideoShowsListResponse = await response.json();
  return data.member[0] || null;
}

// ============================================================================
// YouTube Videos API
// ============================================================================

/**
 * Fetch videos for homepage slider
 * GET /api/youtube_videos
 *
 * @param limit - Number of videos to fetch (default: 12)
 * @param locale - Language locale (ro, en, ru)
 * @returns List of videos for homepage slider
 */
export async function fetchHomepageVideos(
  limit: number = 12,
  locale?: string
): Promise<YouTubeVideosListResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/youtube_videos`);
  url.searchParams.set('isHidden', 'false');
  url.searchParams.set('itemsPerPage', limit.toString());
  url.searchParams.set('order[isFeatured]', 'desc');
  url.searchParams.set('order[publishedAt]', 'desc');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 300, // Revalidate every 5 minutes
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch videos: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch videos by video show
 * GET /api/youtube_videos?videoShow.slug=xxx
 *
 * @param showSlug - Video show slug
 * @param page - Page number (default: 1)
 * @param limit - Items per page (default: 12)
 * @param locale - Language locale (ro, en, ru)
 * @returns Paginated list of videos
 */
export async function fetchVideosByShow(
  showSlug: string,
  page: number = 1,
  limit: number = 12,
  locale?: string
): Promise<YouTubeVideosListResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/youtube_videos`);
  url.searchParams.set('videoShow.slug', showSlug);
  url.searchParams.set('isHidden', 'false');
  url.searchParams.set('page', page.toString());
  url.searchParams.set('itemsPerPage', limit.toString());
  url.searchParams.set('order[publishedAt]', 'desc');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 300,
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch videos: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch all videos with pagination
 * GET /api/youtube_videos
 *
 * @param page - Page number (default: 1)
 * @param limit - Items per page (default: 12)
 * @param locale - Language locale (ro, en, ru)
 * @returns Paginated list of all videos
 */
export async function fetchAllVideos(
  page: number = 1,
  limit: number = 12,
  locale?: string
): Promise<YouTubeVideosListResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/youtube_videos`);
  url.searchParams.set('isHidden', 'false');
  url.searchParams.set('page', page.toString());
  url.searchParams.set('itemsPerPage', limit.toString());
  url.searchParams.set('order[publishedAt]', 'desc');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 300,
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch videos: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch a single video by ID
 * GET /api/youtube_videos/{id}
 *
 * @param id - Video ID
 * @param locale - Language locale (ro, en, ru)
 * @returns Single video or null
 */
export async function fetchVideoById(
  id: number,
  locale?: string
): Promise<YouTubeVideo | null> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const response = await fetch(`${API_BASE_URL}/api/youtube_videos/${id}`, {
    method: 'GET',
    headers,
    next: {
      revalidate: 300,
    },
  });

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error(
      `Failed to fetch video: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}
