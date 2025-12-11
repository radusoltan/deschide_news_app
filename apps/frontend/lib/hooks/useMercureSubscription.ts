'use client';

import { useEffect, useState, useRef, useCallback } from 'react';
import type { MercureEvent } from '@/lib/types/livetext';

export interface UseMercureSubscriptionOptions {
  /**
   * Enable automatic reconnection on connection errors
   * @default true
   */
  autoReconnect?: boolean;

  /**
   * Maximum number of reconnection attempts
   * @default 5
   */
  maxReconnectAttempts?: number;

  /**
   * Delay between reconnection attempts in milliseconds
   * @default 3000
   */
  reconnectDelay?: number;

  /**
   * Enable debug logging
   * @default false
   */
  debug?: boolean;
}

export interface UseMercureSubscriptionResult {
  /**
   * The latest Mercure event received
   */
  latestEvent: MercureEvent | null;

  /**
   * Connection status
   */
  status: 'connecting' | 'connected' | 'disconnected' | 'error';

  /**
   * Error message if status is 'error'
   */
  error: string | null;

  /**
   * Manual reconnect function
   */
  reconnect: () => void;

  /**
   * Number of reconnection attempts made
   */
  reconnectAttempts: number;
}

/**
 * Custom React hook for subscribing to Mercure events for a specific LiveText
 *
 * @param liveTextId - The ID of the LiveText to subscribe to
 * @param options - Configuration options
 * @returns Subscription result with latest event and connection status
 *
 * @example
 * ```tsx
 * const { latestEvent, status, error } = useMercureSubscription(liveTextId, {
 *   autoReconnect: true,
 *   debug: true
 * });
 *
 * useEffect(() => {
 *   if (latestEvent?.type === 'post.created') {
 *     // Handle new post
 *   }
 * }, [latestEvent]);
 * ```
 */
export function useMercureSubscription(
  liveTextId: number | null,
  options: UseMercureSubscriptionOptions = {}
): UseMercureSubscriptionResult {
  const {
    autoReconnect = true,
    maxReconnectAttempts = 5,
    reconnectDelay = 3000,
    debug = false
  } = options;

  const [latestEvent, setLatestEvent] = useState<MercureEvent | null>(null);
  const [status, setStatus] = useState<'connecting' | 'connected' | 'disconnected' | 'error'>('disconnected');
  const [error, setError] = useState<string | null>(null);
  const [reconnectAttempts, setReconnectAttempts] = useState(0);

  const eventSourceRef = useRef<EventSource | null>(null);
  const reconnectTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const isMountedRef = useRef(true);
  // Ref to store connect function for self-referential calls
  const connectRef = useRef<() => void>(() => {});

  const log = useCallback((...args: unknown[]) => {
    if (debug) {
      console.log('[useMercureSubscription]', ...args);
    }
  }, [debug]);

  const cleanup = useCallback(() => {
    log('Cleaning up connection');

    if (eventSourceRef.current) {
      eventSourceRef.current.close();
      eventSourceRef.current = null;
    }

    if (reconnectTimeoutRef.current) {
      clearTimeout(reconnectTimeoutRef.current);
      reconnectTimeoutRef.current = null;
    }
  }, [log]);

  const connect = useCallback(() => {
    if (!liveTextId || !isMountedRef.current) {
      log('Skipping connection - no liveTextId or component unmounted');
      return;
    }

    // Clean up existing connection
    cleanup();

    const mercureUrl = process.env.NEXT_PUBLIC_MERCURE_URL;
    if (!mercureUrl) {
      setError('NEXT_PUBLIC_MERCURE_URL is not configured');
      setStatus('error');
      log('Error: Mercure URL not configured');
      return;
    }

    // Build topic URL
    const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';
    const topic = `${apiUrl}/api/live_texts/${liveTextId}`;
    const url = `${mercureUrl}?topic=${encodeURIComponent(topic)}`;

    log('Connecting to Mercure:', { liveTextId, topic, url });
    setStatus('connecting');
    setError(null);

    try {
      const eventSource = new EventSource(url);
      eventSourceRef.current = eventSource;

      eventSource.onopen = () => {
        if (!isMountedRef.current) return;

        log('Connection opened');
        setStatus('connected');
        setError(null);
        setReconnectAttempts(0);
      };

      eventSource.onmessage = (event) => {
        if (!isMountedRef.current) return;

        try {
          const data = JSON.parse(event.data) as MercureEvent;
          log('Event received:', data);
          setLatestEvent(data);
        } catch (err) {
          log('Error parsing event data:', err);
          console.error('Failed to parse Mercure event:', err, event.data);
        }
      };

      eventSource.onerror = (err) => {
        if (!isMountedRef.current) return;

        log('Connection error:', err);
        setStatus('error');
        setError('Connection to Mercure failed');

        // Close the connection
        eventSource.close();
        eventSourceRef.current = null;

        // Attempt reconnection if enabled
        if (autoReconnect && reconnectAttempts < maxReconnectAttempts) {
          const nextAttempt = reconnectAttempts + 1;
          log(`Scheduling reconnection attempt ${nextAttempt}/${maxReconnectAttempts} in ${reconnectDelay}ms`);

          setReconnectAttempts(nextAttempt);
          reconnectTimeoutRef.current = setTimeout(() => {
            if (isMountedRef.current) {
              // Use ref to call connect to avoid circular dependency
              connectRef.current();
            }
          }, reconnectDelay);
        } else if (reconnectAttempts >= maxReconnectAttempts) {
          log('Max reconnection attempts reached');
          setError(`Connection failed after ${maxReconnectAttempts} attempts`);
        }
      };
    } catch (err) {
      log('Error creating EventSource:', err);
      setError(err instanceof Error ? err.message : 'Unknown error');
      setStatus('error');
    }
  }, [liveTextId, autoReconnect, maxReconnectAttempts, reconnectDelay, reconnectAttempts, cleanup, log]);

  // Update ref whenever connect changes - must be in useEffect to avoid "cannot update ref during render"
  useEffect(() => {
    connectRef.current = connect;
  }, [connect]);

  const reconnect = useCallback(() => {
    log('Manual reconnection triggered');
    setReconnectAttempts(0);
    connect();
  }, [connect, log]);

  // Initial connection and cleanup
  useEffect(() => {
    isMountedRef.current = true;

    // Wrap in function to avoid direct setState in effect body
    const initConnection = () => {
      if (liveTextId) {
        connect();
      } else {
        setStatus('disconnected');
      }
    };
    initConnection();

    return () => {
      isMountedRef.current = false;
      cleanup();
    };
  }, [liveTextId, connect, cleanup]);

  return {
    latestEvent,
    status,
    error,
    reconnect,
    reconnectAttempts
  };
}
