'use client';

import { useEffect, useRef, useState } from 'react';
import { trackLiveTextView, generateSessionId } from '@/lib/api/livetext-analytics';

/**
 * Options for useViewerTracking hook
 */
interface UseViewerTrackingOptions {
  /**
   * Interval in milliseconds for sending heartbeat
   * @default 30000 (30 seconds)
   */
  heartbeatInterval?: number;

  /**
   * Whether to track views automatically
   * @default true
   */
  enabled?: boolean;

  /**
   * Custom session ID (if not provided, generates new one)
   */
  sessionId?: string;

  /**
   * Callback when tracking fails
   */
  onError?: (error: Error) => void;

  /**
   * Callback when tracking succeeds
   */
  onSuccess?: (currentViewers: number) => void;
}

/**
 * Result of useViewerTracking hook
 */
interface UseViewerTrackingResult {
  /**
   * Current session ID
   */
  sessionId: string;

  /**
   * Current viewer count (last known from tracking response)
   */
  currentViewers: number;

  /**
   * Whether tracking is active
   */
  isTracking: boolean;

  /**
   * Last error encountered
   */
  error: Error | null;
}

/**
 * Custom hook for tracking LiveText views with automatic heartbeat
 *
 * Features:
 * - Automatic session management with localStorage
 * - Periodic heartbeat to track time spent
 * - Cleanup on unmount
 * - Error handling
 *
 * @param liveTextId - The LiveText ID to track
 * @param options - Configuration options
 * @returns Tracking state and session info
 *
 * @example
 * ```tsx
 * function LiveTextViewer({ liveTextId }: { liveTextId: number }) {
 *   const { sessionId, currentViewers, isTracking } = useViewerTracking(liveTextId, {
 *     heartbeatInterval: 30000,
 *     onSuccess: (viewers) => console.log(`${viewers} watching`),
 *     onError: (error) => console.error('Tracking failed:', error)
 *   });
 *
 *   return (
 *     <div>
 *       {isTracking && <span>{currentViewers} viewers</span>}
 *     </div>
 *   );
 * }
 * ```
 */
export function useViewerTracking(
  liveTextId: number,
  options: UseViewerTrackingOptions = {}
): UseViewerTrackingResult {
  const {
    heartbeatInterval = 30000, // 30 seconds
    enabled = true,
    sessionId: providedSessionId,
    onError,
    onSuccess
  } = options;

  const [sessionId, setSessionId] = useState<string>('');
  const [currentViewers, setCurrentViewers] = useState<number>(0);
  const [isTracking, setIsTracking] = useState<boolean>(false);
  const [error, setError] = useState<Error | null>(null);

  const intervalRef = useRef<NodeJS.Timeout | null>(null);
  const startTimeRef = useRef<number>(Date.now());
  const lastTrackTimeRef = useRef<number>(Date.now());

  // Initialize session ID
  useEffect(() => {
    if (!enabled) return;

    let sid = providedSessionId;

    if (!sid) {
      // Try to get from localStorage
      if (typeof window !== 'undefined') {
        const stored = localStorage.getItem(`livetext_session_${liveTextId}`);
        if (stored) {
          sid = stored;
        } else {
          // Generate new session ID
          sid = generateSessionId();
          localStorage.setItem(`livetext_session_${liveTextId}`, sid);
        }
      } else {
        // Fallback if localStorage not available
        sid = generateSessionId();
      }
    }

    setSessionId(sid);
  }, [liveTextId, providedSessionId, enabled]);

  // Track initial view
  useEffect(() => {
    if (!enabled || !sessionId) return;

    let mounted = true;

    const trackInitialView = async () => {
      try {
        setIsTracking(true);
        startTimeRef.current = Date.now();
        lastTrackTimeRef.current = Date.now();

        const response = await trackLiveTextView(liveTextId, { sessionId });

        if (mounted) {
          setCurrentViewers(response.currentViewers);
          setError(null);
          onSuccess?.(response.currentViewers);
        }
      } catch (err) {
        const error = err instanceof Error ? err : new Error('Failed to track view');
        if (mounted) {
          setError(error);
          setIsTracking(false);
          onError?.(error);
        }
      }
    };

    trackInitialView();

    return () => {
      mounted = false;
    };
  }, [liveTextId, sessionId, enabled, onError, onSuccess]);

  // Setup heartbeat interval
  useEffect(() => {
    if (!enabled || !sessionId || !isTracking) return;

    const sendHeartbeat = async () => {
      try {
        const now = Date.now();
        const timeSpent = Math.floor((now - lastTrackTimeRef.current) / 1000); // seconds

        lastTrackTimeRef.current = now;

        const response = await trackLiveTextView(liveTextId, {
          sessionId,
          timeSpent
        });

        setCurrentViewers(response.currentViewers);
        setError(null);
        onSuccess?.(response.currentViewers);
      } catch (err) {
        const error = err instanceof Error ? err : new Error('Failed to send heartbeat');
        setError(error);
        onError?.(error);
      }
    };

    // Set up interval for heartbeat
    intervalRef.current = setInterval(sendHeartbeat, heartbeatInterval);

    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
        intervalRef.current = null;
      }
    };
  }, [liveTextId, sessionId, isTracking, enabled, heartbeatInterval, onError, onSuccess]);

  // Cleanup on unmount - send final heartbeat
  useEffect(() => {
    return () => {
      if (sessionId && isTracking) {
        const now = Date.now();
        const finalTimeSpent = Math.floor((now - lastTrackTimeRef.current) / 1000);

        // Send final heartbeat (don't await, fire-and-forget)
        trackLiveTextView(liveTextId, {
          sessionId,
          timeSpent: finalTimeSpent
        }).catch(() => {
          // Ignore errors on cleanup
        });
      }
    };
  }, [liveTextId, sessionId, isTracking]);

  return {
    sessionId,
    currentViewers,
    isTracking,
    error
  };
}
