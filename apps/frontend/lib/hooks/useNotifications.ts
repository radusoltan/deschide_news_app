'use client';

import { useEffect, useState, useRef, useCallback } from 'react';
import type { AdminNotification } from '@/lib/types/notification';
import {
  fetchUnreadCount,
  fetchNotifications,
  markNotificationAsRead,
  markAllNotificationsAsRead,
} from '@/app/actions/notifications';

interface UseNotificationsResult {
  notifications: AdminNotification[];
  unreadCount: number;
  isLoading: boolean;
  markAsRead: (id: string) => Promise<void>;
  markAllRead: () => Promise<void>;
  refresh: () => Promise<void>;
}

export function useNotifications(username: string): UseNotificationsResult {
  const [notifications, setNotifications] = useState<AdminNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const eventSourceRef = useRef<EventSource | null>(null);
  const isMountedRef = useRef(true);

  // Fetch initial data
  const loadInitialData = useCallback(async () => {
    try {
      setIsLoading(true);
      const [countData, listData] = await Promise.all([
        fetchUnreadCount(),
        fetchNotifications(1, 10),
      ]);
      if (isMountedRef.current) {
        setUnreadCount(countData.count);
        setNotifications(listData.items);
      }
    } catch (err) {
      console.warn('[Notifications] Failed to fetch initial data:', err);
    } finally {
      if (isMountedRef.current) {
        setIsLoading(false);
      }
    }
  }, []);

  // SSE connection to Mercure
  useEffect(() => {
    isMountedRef.current = true;

    if (!username) return;

    const mercureUrl = process.env.NEXT_PUBLIC_MERCURE_URL;
    if (!mercureUrl) {
      console.warn('[Notifications] NEXT_PUBLIC_MERCURE_URL not configured');
      return;
    }

    const topic = `deschide_news/admin/notifications/${username}`;
    const url = `${mercureUrl}?topic=${encodeURIComponent(topic)}`;

    const eventSource = new EventSource(url, { withCredentials: true });
    eventSourceRef.current = eventSource;

    eventSource.onmessage = (event: MessageEvent) => {
      if (!isMountedRef.current) return;

      try {
        const notification: AdminNotification = JSON.parse(event.data);
        setNotifications((prev) => [notification, ...prev.slice(0, 49)]);
        setUnreadCount((prev) => prev + 1);

        // Browser Notification API
        if ('Notification' in window && Notification.permission === 'granted') {
          new Notification(notification.title, {
            body: notification.message ?? '',
            icon: '/favicon.ico',
            tag: notification.id,
          });
        }
      } catch (err) {
        console.warn('[Notifications] Failed to parse SSE event:', err);
      }
    };

    let errorCount = 0;
    eventSource.onerror = () => {
      errorCount++;
      if (errorCount === 1) {
        console.warn('[Notifications] SSE connection lost. Mercure may not be running.');
      }
      // Stop retrying after 3 failures to avoid console spam
      if (errorCount >= 3) {
        eventSource.close();
        eventSourceRef.current = null;
      }
    };

    return () => {
      isMountedRef.current = false;
      if (eventSourceRef.current) {
        eventSourceRef.current.close();
        eventSourceRef.current = null;
      }
    };
  }, [username]);

  // Initial fetch
  useEffect(() => {
    loadInitialData();
  }, [loadInitialData]);

  const markAsRead = useCallback(async (id: string) => {
    try {
      await markNotificationAsRead(id);
      setNotifications((prev) =>
        prev.map((n) =>
          n.id === id ? { ...n, isRead: true, readAt: new Date().toISOString() } : n
        )
      );
      setUnreadCount((prev) => Math.max(0, prev - 1));
    } catch (err) {
      console.error('[Notifications] Failed to mark as read:', err);
    }
  }, []);

  const markAllRead = useCallback(async () => {
    try {
      await markAllNotificationsAsRead();
      setNotifications((prev) => prev.map((n) => ({ ...n, isRead: true })));
      setUnreadCount(0);
    } catch (err) {
      console.error('[Notifications] Failed to mark all as read:', err);
    }
  }, []);

  return {
    notifications,
    unreadCount,
    isLoading,
    markAsRead,
    markAllRead,
    refresh: loadInitialData,
  };
}
