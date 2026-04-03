'use client';

import { useEffect, useRef, useCallback } from 'react';

const TOPIC = 'deschide_news/articles';
const MAX_RECONNECT = 5;
const RECONNECT_DELAY = 3000;

interface ArticleUpdateEvent {
  type: string;
  articleId: number;
  badge: string | null;
  isFeatured: boolean;
  status: string;
  timestamp: string;
}

/**
 * Hook that subscribes to Mercure SSE for article update events.
 * Calls `onUpdate` whenever an article is created/updated/deleted.
 */
export function useArticleUpdates(onUpdate: (event: ArticleUpdateEvent) => void) {
  const onUpdateRef = useRef(onUpdate);
  onUpdateRef.current = onUpdate;
  const reconnectCount = useRef(0);
  const esRef = useRef<EventSource | null>(null);
  const reconnectTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const connect = useCallback(() => {
    if (typeof window === 'undefined' || typeof EventSource === 'undefined') return;

    const mercureUrl = process.env.NEXT_PUBLIC_MERCURE_URL;
    if (!mercureUrl) return;

    let url: URL;

    try {
      url = new URL(mercureUrl);
    } catch {
      return;
    }

    url.searchParams.append('topic', TOPIC);

    reconnectTimeoutRef.current = null;
    const es = new EventSource(url.toString());
    esRef.current = es;

    es.onmessage = (event) => {
      try {
        const data: ArticleUpdateEvent = JSON.parse(event.data);
        reconnectCount.current = 0;
        onUpdateRef.current(data);
      } catch {
        // ignore malformed events
      }
    };

    es.onerror = () => {
      es.close();
      esRef.current = null;
      if (reconnectCount.current < MAX_RECONNECT) {
        reconnectCount.current++;
        reconnectTimeoutRef.current = setTimeout(connect, RECONNECT_DELAY);
      }
    };
  }, []);

  useEffect(() => {
    connect();
    return () => {
      if (reconnectTimeoutRef.current) {
        clearTimeout(reconnectTimeoutRef.current);
        reconnectTimeoutRef.current = null;
      }
      esRef.current?.close();
      esRef.current = null;
    };
  }, [connect]);
}
