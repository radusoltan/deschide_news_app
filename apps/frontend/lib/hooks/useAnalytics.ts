/**
 * Analytics Hooks
 *
 * React hooks for tracking user engagement
 */

'use client';

import { useEffect, useRef, useCallback } from 'react';
import {
  trackPostView,
  trackPostRead,
  trackPostClick,
  trackPostShare,
  PostVisibilityTracker,
  PostReadTimeTracker,
} from '@/lib/utils/analyticsTracker';

// ============================================================================
// Post View Tracking Hook
// ============================================================================

/**
 * Automatically track post views when element enters viewport
 */
export function usePostViewTracking(postId: number, enabled: boolean = true) {
  const elementRef = useRef<HTMLElement | null>(null);
  const trackerRef = useRef<PostVisibilityTracker | null>(null);

  useEffect(() => {
    if (!enabled || !postId) return;

    trackerRef.current = new PostVisibilityTracker((viewedPostId) => {
      trackPostView(viewedPostId);
    });

    if (elementRef.current) {
      trackerRef.current.observe(elementRef.current);
    }

    return () => {
      if (trackerRef.current) {
        trackerRef.current.disconnect();
      }
    };
  }, [postId, enabled]);

  return elementRef;
}

// ============================================================================
// Post Read Tracking Hook
// ============================================================================

/**
 * Track time spent reading a post
 */
export function usePostReadTracking(postId: number, enabled: boolean = true) {
  const elementRef = useRef<HTMLElement | null>(null);
  const trackerRef = useRef<PostReadTimeTracker | null>(null);
  const isTrackingRef = useRef(false);

  useEffect(() => {
    if (!enabled || !postId) return;

    trackerRef.current = new PostReadTimeTracker((postId, timeSpent, scrollDepth) => {
      trackPostRead(postId, timeSpent, scrollDepth);
    });

    const element = elementRef.current;
    if (!element) return;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting && !isTrackingRef.current) {
            isTrackingRef.current = true;
            trackerRef.current?.startTracking(postId, element);
          } else if (!entry.isIntersecting && isTrackingRef.current) {
            isTrackingRef.current = false;
            trackerRef.current?.stopTracking();
          }
        });
      },
      {
        threshold: 0.5,
      }
    );

    observer.observe(element);

    return () => {
      observer.disconnect();
      trackerRef.current?.stopTracking();
    };
  }, [postId, enabled]);

  return elementRef;
}

// ============================================================================
// Combined Tracking Hook
// ============================================================================

/**
 * Track both view and read time for a post
 */
export function usePostTracking(postId: number, enabled: boolean = true) {
  const elementRef = useRef<HTMLElement | null>(null);
  const viewTrackerRef = useRef<PostVisibilityTracker | null>(null);
  const readTrackerRef = useRef<PostReadTimeTracker | null>(null);
  const isTrackingRef = useRef(false);

  useEffect(() => {
    if (!enabled || !postId) return;

    // Initialize trackers
    viewTrackerRef.current = new PostVisibilityTracker((viewedPostId) => {
      trackPostView(viewedPostId);
    });

    readTrackerRef.current = new PostReadTimeTracker((postId, timeSpent, scrollDepth) => {
      trackPostRead(postId, timeSpent, scrollDepth);
    });

    const element = elementRef.current;
    if (!element) return;

    // View tracking
    viewTrackerRef.current.observe(element);

    // Read time tracking
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting && !isTrackingRef.current) {
            isTrackingRef.current = true;
            readTrackerRef.current?.startTracking(postId, element);
          } else if (!entry.isIntersecting && isTrackingRef.current) {
            isTrackingRef.current = false;
            readTrackerRef.current?.stopTracking();
          }
        });
      },
      {
        threshold: 0.5,
      }
    );

    observer.observe(element);

    return () => {
      viewTrackerRef.current?.disconnect();
      observer.disconnect();
      readTrackerRef.current?.stopTracking();
    };
  }, [postId, enabled]);

  return elementRef;
}

// ============================================================================
// Click Tracking Hook
// ============================================================================

/**
 * Track clicks on interactive elements within a post
 */
export function usePostClickTracking(postId: number, enabled: boolean = true) {
  const trackClick = useCallback(
    (element: string) => {
      if (enabled && postId) {
        trackPostClick(postId, element);
      }
    },
    [postId, enabled]
  );

  return trackClick;
}

// ============================================================================
// Share Tracking Hook
// ============================================================================

/**
 * Track social media shares
 */
export function usePostShareTracking(postId: number) {
  const trackShare = useCallback(
    (platform: string) => {
      if (postId) {
        trackPostShare(postId, platform);
      }
    },
    [postId]
  );

  return trackShare;
}

// ============================================================================
// A/B Test Variant Hook
// ============================================================================

/**
 * Get assigned A/B test variant for a test
 */
export function useAbTestVariant(testId: number | null) {
  const [variant, setVariant] = useState<string | null>(null);
  const [config, setConfig] = useState<Record<string, any> | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!testId) return;

    setLoading(true);

    import('@/lib/api/analytics')
      .then(({ getAssignedVariant }) => getAssignedVariant(testId))
      .then((result) => {
        setVariant(result.variant);
        setConfig(result.config.config);
        setLoading(false);
      })
      .catch((error) => {
        console.error('Failed to get A/B test variant:', error);
        setVariant('control');
        setLoading(false);
      });
  }, [testId]);

  return { variant, config, loading };
}

// Import useState for useAbTestVariant
import { useState } from 'react';
