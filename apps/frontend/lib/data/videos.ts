/**
 * Videos Data Access Layer
 * Cached data fetching with Next.js 16 `use cache` directive
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 2 Caching Alignment
 */

import { cacheTag } from 'next/cache';
import { cache } from 'react';
import { CACHE_TAGS } from './cache-config';
import type {
  VideoShow,
  VideoShowsListResponse,
  YouTubeVideo,
  YouTubeVideosListResponse,
} from '@/lib/types/video';

// ============================================================================
// Configuration
// ============================================================================

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

// ============================================================================
// Video Shows Data Functions
// ============================================================================

/**
 * Get all active video shows
 * Uses `use cache` directive for automatic caching with tags
 */
export async function getAllVideoShows(
  locale: string = 'ro'
): Promise<VideoShowsListResponse> {
  'use cache';
  cacheTag(CACHE_TAGS.videoShows, CACHE_TAGS.locale(locale));

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/video_shows`);
  url.searchParams.set('isActive', 'true');
  url.searchParams.set('itemsPerPage', '50');
  url.searchParams.set('order[position]', 'asc');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch video shows: ${response.status}`);
  }

  return response.json();
}

/**
 * Get video show by slug
 * Uses `use cache` directive with show-specific tag
 */
export async function getVideoShowBySlug(
  slug: string,
  locale: string = 'ro'
): Promise<VideoShow | null> {
  'use cache';
  cacheTag(CACHE_TAGS.videoShows, CACHE_TAGS.locale(locale), `show-${slug}`);

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/video_shows`);
  url.searchParams.set('slug', slug);

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch video show: ${response.status}`);
  }

  const data: VideoShowsListResponse = await response.json();
  return data.member[0] || null;
}

// ============================================================================
// YouTube Videos Data Functions
// ============================================================================

/**
 * Get homepage videos (for slider)
 * Uses `use cache` directive with homepage-videos tag
 */
export async function getHomepageVideos(
  locale: string = 'ro',
  limit: number = 12
): Promise<YouTubeVideosListResponse> {
  'use cache';
  cacheTag(
    CACHE_TAGS.homepageVideos,
    CACHE_TAGS.homepage,
    CACHE_TAGS.locale(locale)
  );

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/youtube_videos`);
  url.searchParams.set('isHidden', 'false');
  url.searchParams.set('itemsPerPage', limit.toString());
  url.searchParams.set('order[isFeatured]', 'desc');
  url.searchParams.set('order[publishedAt]', 'desc');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch homepage videos: ${response.status}`);
  }

  return response.json();
}

/**
 * Get videos by video show
 * Uses `use cache` directive with show-specific tag
 */
export async function getVideosByShow(
  showSlug: string,
  locale: string = 'ro',
  page: number = 1,
  limit: number = 12
): Promise<YouTubeVideosListResponse> {
  'use cache';
  cacheTag(CACHE_TAGS.videos, CACHE_TAGS.locale(locale), `show-${showSlug}`);

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/youtube_videos`);
  url.searchParams.set('videoShow.slug', showSlug);
  url.searchParams.set('isHidden', 'false');
  url.searchParams.set('page', page.toString());
  url.searchParams.set('itemsPerPage', limit.toString());
  url.searchParams.set('order[publishedAt]', 'desc');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch videos by show: ${response.status}`);
  }

  return response.json();
}

/**
 * Get all videos with pagination
 * Uses `use cache` directive with videos tag
 */
export async function getAllVideos(
  locale: string = 'ro',
  page: number = 1,
  limit: number = 12
): Promise<YouTubeVideosListResponse> {
  'use cache';
  cacheTag(CACHE_TAGS.videos, CACHE_TAGS.locale(locale));

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/youtube_videos`);
  url.searchParams.set('isHidden', 'false');
  url.searchParams.set('page', page.toString());
  url.searchParams.set('itemsPerPage', limit.toString());
  url.searchParams.set('order[publishedAt]', 'desc');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch videos: ${response.status}`);
  }

  return response.json();
}

/**
 * Get single video by ID
 * Uses `use cache` directive with video-specific tag
 */
export async function getVideoById(
  id: number,
  locale: string = 'ro'
): Promise<YouTubeVideo | null> {
  'use cache';
  cacheTag(CACHE_TAGS.videos, CACHE_TAGS.locale(locale), `video-${id}`);

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const response = await fetch(`${API_BASE_URL}/api/youtube_videos/${id}`, {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error(`Failed to fetch video: ${response.status}`);
  }

  return response.json();
}

// ============================================================================
// React cache() for Request-Level Deduplication
// ============================================================================

/**
 * Get all video shows with request-level deduplication
 */
export const getAllVideoShowsCached = cache(async (
  locale: string = 'ro'
): Promise<VideoShowsListResponse> => {
  return getAllVideoShows(locale);
});

/**
 * Get homepage videos with request-level deduplication
 */
export const getHomepageVideosCached = cache(async (
  locale: string = 'ro',
  limit: number = 12
): Promise<YouTubeVideosListResponse> => {
  return getHomepageVideos(locale, limit);
});

/**
 * Get video show by slug with request-level deduplication
 */
export const getVideoShowBySlugCached = cache(async (
  slug: string,
  locale: string = 'ro'
): Promise<VideoShow | null> => {
  return getVideoShowBySlug(slug, locale);
});

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Extract YouTube video ID from various URL formats
 */
export function extractYouTubeId(url: string): string | null {
  if (!url) return null;

  const patterns = [
    /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\s?]+)/,
    /^([a-zA-Z0-9_-]{11})$/, // Direct ID
  ];

  for (const pattern of patterns) {
    const match = url.match(pattern);
    if (match) return match[1];
  }

  return null;
}

/**
 * Get YouTube thumbnail URL
 */
export function getYouTubeThumbnail(
  videoId: string,
  quality: 'default' | 'medium' | 'high' | 'standard' | 'maxres' = 'high'
): string {
  const qualityMap = {
    default: 'default',
    medium: 'mqdefault',
    high: 'hqdefault',
    standard: 'sddefault',
    maxres: 'maxresdefault',
  };

  return `https://img.youtube.com/vi/${videoId}/${qualityMap[quality]}.jpg`;
}

/**
 * Get YouTube embed URL
 */
export function getYouTubeEmbedUrl(videoId: string, autoplay: boolean = false): string {
  const params = new URLSearchParams({
    rel: '0',
    modestbranding: '1',
    ...(autoplay ? { autoplay: '1' } : {}),
  });

  return `https://www.youtube.com/embed/${videoId}?${params.toString()}`;
}

/**
 * Format video duration from seconds to HH:MM:SS or MM:SS
 */
export function formatVideoDuration(seconds: number): string {
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = seconds % 60;

  if (hours > 0) {
    return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  }

  return `${minutes}:${secs.toString().padStart(2, '0')}`;
}
