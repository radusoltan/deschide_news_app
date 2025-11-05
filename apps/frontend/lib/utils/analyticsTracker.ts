/**
 * Analytics Tracker Utility
 *
 * Utilities for tracking user engagement with LiveText posts
 */

import { trackEngagement, trackView, trackRead, trackClick, trackShare } from '@/lib/api/analytics';
import type { EngagementType, TrackEngagementPayload } from '@/lib/types/analytics';

// ============================================================================
// Configuration
// ============================================================================

const TRACKING_ENABLED =
  typeof window !== 'undefined' && process.env.NEXT_PUBLIC_ANALYTICS_ENABLED !== 'false';

const VIEW_THRESHOLD = 1000; // 1 second
const READ_THRESHOLD = 10000; // 10 seconds
const SCROLL_DEPTH_THRESHOLD = 50; // 50%

// ============================================================================
// Core Tracking Functions
// ============================================================================

/**
 * Track generic engagement event
 */
export async function track(payload: TrackEngagementPayload): Promise<void> {
  if (!TRACKING_ENABLED) return;

  try {
    await trackEngagement(payload);
  } catch (error) {
    // Silently fail - don't interrupt user experience
    console.debug('Analytics tracking failed:', error);
  }
}

/**
 * Track post view
 */
export async function trackPostView(postId: number): Promise<void> {
  if (!TRACKING_ENABLED) return;

  try {
    await trackView(postId);
  } catch (error) {
    console.debug('View tracking failed:', error);
  }
}

/**
 * Track post read
 */
export async function trackPostRead(
  postId: number,
  timeSpent: number,
  scrollDepth: number
): Promise<void> {
  if (!TRACKING_ENABLED) return;

  try {
    await trackRead(postId, timeSpent, scrollDepth);
  } catch (error) {
    console.debug('Read tracking failed:', error);
  }
}

/**
 * Track post click
 */
export async function trackPostClick(postId: number, element: string): Promise<void> {
  if (!TRACKING_ENABLED) return;

  try {
    await trackClick(postId, element);
  } catch (error) {
    console.debug('Click tracking failed:', error);
  }
}

/**
 * Track post share
 */
export async function trackPostShare(postId: number, platform: string): Promise<void> {
  if (!TRACKING_ENABLED) return;

  try {
    await trackShare(postId, platform);
  } catch (error) {
    console.debug('Share tracking failed:', error);
  }
}

// ============================================================================
// Automatic Tracking Utilities
// ============================================================================

/**
 * Post visibility tracker
 * Automatically tracks when a post enters viewport
 */
export class PostVisibilityTracker {
  private observer: IntersectionObserver | null = null;
  private trackedPosts = new Set<number>();

  constructor(private onPostView: (postId: number) => void) {
    if (typeof window === 'undefined') return;

    this.observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const postId = parseInt(entry.target.getAttribute('data-post-id') || '0', 10);
            if (postId && !this.trackedPosts.has(postId)) {
              this.trackedPosts.add(postId);
              this.onPostView(postId);
            }
          }
        });
      },
      {
        threshold: 0.5, // 50% visible
      }
    );
  }

  observe(element: HTMLElement) {
    this.observer?.observe(element);
  }

  unobserve(element: HTMLElement) {
    this.observer?.unobserve(element);
  }

  disconnect() {
    this.observer?.disconnect();
  }
}

/**
 * Post read time tracker
 * Tracks time spent on each post
 */
export class PostReadTimeTracker {
  private startTime: number = 0;
  private postId: number = 0;
  private isReading: boolean = false;
  private scrollDepth: number = 0;
  private intervalId: NodeJS.Timeout | null = null;

  constructor(private onPostRead: (postId: number, timeSpent: number, scrollDepth: number) => void) {}

  startTracking(postId: number, element: HTMLElement) {
    if (this.isReading && this.postId === postId) return;

    this.stopTracking();

    this.postId = postId;
    this.startTime = Date.now();
    this.isReading = true;
    this.scrollDepth = 0;

    // Track scroll depth within post
    const updateScrollDepth = () => {
      const rect = element.getBoundingClientRect();
      const windowHeight = window.innerHeight;
      const elementHeight = rect.height;

      // Calculate how much of the element is visible
      const visibleTop = Math.max(0, -rect.top);
      const visibleBottom = Math.min(elementHeight, windowHeight - rect.top);
      const visibleHeight = visibleBottom - visibleTop;

      const depth = (visibleHeight / elementHeight) * 100;
      this.scrollDepth = Math.max(this.scrollDepth, Math.min(100, Math.round(depth)));
    };

    // Update scroll depth periodically
    this.intervalId = setInterval(updateScrollDepth, 1000);
    updateScrollDepth();
  }

  stopTracking() {
    if (!this.isReading) return;

    const timeSpent = Math.floor((Date.now() - this.startTime) / 1000);

    if (timeSpent >= READ_THRESHOLD / 1000) {
      this.onPostRead(this.postId, timeSpent, this.scrollDepth);
    }

    this.isReading = false;
    this.scrollDepth = 0;

    if (this.intervalId) {
      clearInterval(this.intervalId);
      this.intervalId = null;
    }
  }
}

/**
 * Click tracker
 * Tracks clicks on interactive elements within posts
 */
export function setupClickTracking(
  postElement: HTMLElement,
  postId: number,
  onPostClick: (postId: number, element: string) => void
) {
  const trackableSelectors = ['a', 'button', 'img', '[data-trackable]'];

  trackableSelectors.forEach((selector) => {
    const elements = postElement.querySelectorAll(selector);
    elements.forEach((element) => {
      element.addEventListener('click', (e) => {
        const target = e.currentTarget as HTMLElement;
        const elementType = target.tagName.toLowerCase();
        const elementId = target.id || target.className || elementType;
        onPostClick(postId, elementId);
      });
    });
  });
}

// ============================================================================
// Batched Tracking (Performance Optimization)
// ============================================================================

class AnalyticsBatcher {
  private queue: TrackEngagementPayload[] = [];
  private flushInterval: NodeJS.Timeout | null = null;
  private readonly BATCH_SIZE = 10;
  private readonly FLUSH_INTERVAL = 5000; // 5 seconds

  constructor() {
    if (typeof window !== 'undefined') {
      this.startAutoFlush();
    }
  }

  add(payload: TrackEngagementPayload) {
    this.queue.push(payload);

    if (this.queue.length >= this.BATCH_SIZE) {
      this.flush();
    }
  }

  async flush() {
    if (this.queue.length === 0) return;

    const batch = [...this.queue];
    this.queue = [];

    try {
      await Promise.all(batch.map((payload) => trackEngagement(payload)));
    } catch (error) {
      console.debug('Batch analytics tracking failed:', error);
    }
  }

  startAutoFlush() {
    this.flushInterval = setInterval(() => {
      this.flush();
    }, this.FLUSH_INTERVAL);
  }

  stop() {
    if (this.flushInterval) {
      clearInterval(this.flushInterval);
      this.flushInterval = null;
    }
    this.flush(); // Flush remaining
  }
}

export const analyticsBatcher = new AnalyticsBatcher();

// Flush on page unload
if (typeof window !== 'undefined') {
  window.addEventListener('beforeunload', () => {
    analyticsBatcher.flush();
  });
}

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Get scroll depth of entire page
 */
export function getPageScrollDepth(): number {
  if (typeof window === 'undefined') return 0;

  const windowHeight = window.innerHeight;
  const documentHeight = document.documentElement.scrollHeight;
  const scrollTop = window.scrollY;

  const depth = ((scrollTop + windowHeight) / documentHeight) * 100;
  return Math.min(100, Math.round(depth));
}

/**
 * Debounce function
 */
export function debounce<T extends (...args: any[]) => void>(
  func: T,
  wait: number
): (...args: Parameters<T>) => void {
  let timeout: NodeJS.Timeout | null = null;

  return function (...args: Parameters<T>) {
    if (timeout) clearTimeout(timeout);
    timeout = setTimeout(() => func(...args), wait);
  };
}

/**
 * Throttle function
 */
export function throttle<T extends (...args: any[]) => void>(
  func: T,
  limit: number
): (...args: Parameters<T>) => void {
  let inThrottle: boolean = false;

  return function (...args: Parameters<T>) {
    if (!inThrottle) {
      func(...args);
      inThrottle = true;
      setTimeout(() => (inThrottle = false), limit);
    }
  };
}
