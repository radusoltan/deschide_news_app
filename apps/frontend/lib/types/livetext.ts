/**
 * LiveText Types and Interfaces
 */

import type { SportMercureEvent } from './sport';

export type LiveTextStatus = 'draft' | 'live' | 'paused' | 'ended';

/**
 * Simplified SportMatch for API response (without nested liveText)
 */
export interface LiveTextSportMatch {
  id: number;
  sportType: string;
  homeTeam: string;
  awayTeam: string;
  homeTeamLogo?: string;
  awayTeamLogo?: string;
  homeScore: number;
  awayScore: number;
  status: string;
  currentMinute?: number;
  currentPeriod?: string;
  venue?: string;
  competition?: string;
}

export type TemplateType = 'breaking_news' | 'sport' | 'conference' | 'election';

export interface TemplateConfig {
  colors: {
    primary: string;
    secondary: string;
    accent: string;
    background: string;
    text: string;
  };
  layout: {
    headerStyle: 'bold' | 'minimal' | 'banner';
    postStyle: 'card' | 'compact' | 'full';
    showTimeline: boolean;
    sidebarPosition: 'left' | 'right';
  };
  features: {
    enableReactions: boolean;
    enableKeyPoints: boolean;
    enableTimeline: boolean;
    autoRefresh: boolean;
    refreshInterval: number;
  };
}

export interface LiveTextTemplate {
  id: number;
  name: string;
  description: string;
  type: TemplateType;
  config: TemplateConfig;
  isSystem: boolean;
}

export interface LiveTextAuthor {
  id: number;
  username: string;
  email: string;
}

export interface LiveTextCategory {
  id: number;
  title: string;
  slug: string;
}

export interface LiveTextCollaborator {
  id: number;
  role: 'editor' | 'contributor';
  user: {
    id: number;
    username: string;
  };
  createdAt: string;
}

export interface LiveTextPost {
  id: number;
  content: string;
  contentHtml: string;
  isKeyPoint: boolean;
  position: number;
  publishedAt: string;
  author: LiveTextAuthor;
  createdAt: string;
  updatedAt: string;
}

export interface LiveText {
  id: number;
  title: string;
  slug: string;
  description: string | null;
  status: LiveTextStatus;
  startTime: string | null;
  endTime: string | null;
  locale: string;
  author: LiveTextAuthor;
  category: LiveTextCategory | null;
  template: LiveTextTemplate | null;
  sportMatch?: LiveTextSportMatch | null;
  collaborators: LiveTextCollaborator[];
  posts: LiveTextPost[];
  createdAt: string;
  updatedAt: string;
}

export interface LiveTextListItem {
  id: number;
  title: string;
  slug: string;
  description: string | null;
  status: LiveTextStatus;
  startTime: string | null;
  endTime: string | null;
  author: LiveTextAuthor;
  category: LiveTextCategory | null;
  sportMatch?: LiveTextSportMatch | null;
  collaborators: LiveTextCollaborator[];
  createdAt: string;
  updatedAt: string;
}

/**
 * Mercure Event Types
 */

export interface MercureEventBase {
  type: string;
  liveTextId: number;
  timestamp: string;
}

export interface PostCreatedEvent extends MercureEventBase {
  type: 'post.created';
  post: LiveTextPost;
}

export interface PostUpdatedEvent extends MercureEventBase {
  type: 'post.updated';
  post: Partial<LiveTextPost> & { id: number };
}

export interface PostDeletedEvent extends MercureEventBase {
  type: 'post.deleted';
  postId: number;
}

export interface StatusChangedEvent extends MercureEventBase {
  type: 'status.changed';
  status: LiveTextStatus;
  title: string;
}

export interface ViewersCountEvent extends MercureEventBase {
  type: 'viewers.count';
  count: number;
}

export type MercureEvent =
  | PostCreatedEvent
  | PostUpdatedEvent
  | PostDeletedEvent
  | StatusChangedEvent
  | ViewersCountEvent
  | SportMercureEvent;

/**
 * Analytics Types
 */

export interface ViewsOverTimeData {
  hour: string;
  count: number;
}

export interface PostEngagementData {
  postId: number;
  content: string;
  reactionsCount: number;
  publishedAt: string;
}

export interface ViewersByPlatformData {
  platform: string;
  count: number;
}

export interface LiveTextAnalytics {
  liveTextId: number;
  totalViews: number;
  uniqueViewers: number;
  averageTimeSpent: number;
  peakConcurrentViewers: number;
  currentViewers: number;
  totalPosts: number;
  totalReactions: number;
  viewsOverTime?: ViewsOverTimeData[];
  postEngagement?: PostEngagementData[];
  viewersByPlatform?: ViewersByPlatformData[];
}

export interface LiveTextViewerCount {
  liveTextId: number;
  currentViewers: number;
}

export interface TrackViewRequest {
  sessionId: string;
  timeSpent?: number;
}

export interface TrackViewResponse {
  success: boolean;
  viewId: number;
  currentViewers: number;
}

/**
 * API Response Types
 */

export interface LiveTextApiResponse {
  '@context': string;
  '@id': string;
  '@type': string;
  id: number;
  title: string;
  slug: string;
  description: string | null;
  status: LiveTextStatus;
  startTime: string | null;
  endTime: string | null;
  locale: string;
  author: any;
  category: any;
  sportMatch?: any;
  collaborators: any[];
  posts: any[];
  createdAt: string;
  updatedAt: string;
}

export interface LiveTextCollectionResponse {
  '@context': string;
  '@id': string;
  '@type': string;
  member: LiveTextApiResponse[];
  totalItems?: number;
  view?: {
    '@id': string;
    '@type': string;
    'hydra:first'?: string;
    'hydra:last'?: string;
    'hydra:next'?: string;
    'hydra:previous'?: string;
  };
}
