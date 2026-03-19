/**
 * Video Shows and YouTube Videos Types
 */

export interface VideoShow {
  '@id': string;
  '@type': string;
  id: number;
  name: string;
  slug: string;
  description?: string;
  youtubePlaylistId?: string;
  youtubeChannelId?: string;
  thumbnailUrl?: string;
  color?: string;
  isActive: boolean;
  position: number;
  videosCount: number;
  createdAt: string;
  updatedAt: string;
}

export interface YouTubeVideo {
  '@id': string;
  '@type': string;
  id: number;
  youtubeId: string;
  title: string;
  description?: string;
  thumbnailUrl?: string;
  thumbnailMedium?: string;
  durationSeconds?: number;
  durationFormatted?: string;
  publishedAt?: string;
  viewCount: number;
  likeCount: number;
  isFeatured: boolean;
  isHidden: boolean;
  position?: number;
  syncedAt?: string;
  createdAt: string;
  updatedAt: string;
  youtubeUrl: string;
  embedUrl: string;
  videoShow?: {
    '@id': string;
    id: number;
    name: string;
    slug: string;
    thumbnailUrl?: string;
    color?: string;
  };
}

export interface VideoShowsListResponse {
  '@context': string;
  '@id': string;
  '@type': string;
  member: VideoShow[];
  totalItems: number;
}

export interface YouTubeVideosListResponse {
  '@context': string;
  '@id': string;
  '@type': string;
  member: YouTubeVideo[];
  totalItems: number;
  view?: {
    '@id': string;
    '@type': string;
    first: string;
    last: string;
    next?: string;
    previous?: string;
  };
}
