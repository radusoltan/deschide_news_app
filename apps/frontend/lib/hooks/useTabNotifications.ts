'use client';

import { useEffect, useRef, useState } from 'react';

/**
 * Options for tab notifications
 */
interface UseTabNotificationsOptions {
  /**
   * Original page title
   */
  originalTitle: string;

  /**
   * Enable favicon badge
   * @default true
   */
  enableFaviconBadge?: boolean;

  /**
   * Badge background color
   * @default '#ef4444' (red-500)
   */
  badgeColor?: string;

  /**
   * Badge text color
   * @default '#ffffff'
   */
  badgeTextColor?: string;
}

/**
 * Result of useTabNotifications hook
 */
interface UseTabNotificationsResult {
  /**
   * Set number of unread/new items
   */
  setUnreadCount: (count: number) => void;

  /**
   * Clear unread count
   */
  clearUnread: () => void;

  /**
   * Current unread count
   */
  unreadCount: number;
}

/**
 * Custom hook for managing browser tab notifications
 *
 * Features:
 * - Updates page title with unread count
 * - Adds badge to favicon
 * - Resets on tab focus
 *
 * @example
 * ```tsx
 * const { setUnreadCount, clearUnread, unreadCount } = useTabNotifications({
 *   originalTitle: 'Live Text - Breaking News'
 * });
 *
 * // When new post arrives
 * setUnreadCount(unreadCount + 1);
 *
 * // When user views posts
 * clearUnread();
 * ```
 */
export function useTabNotifications(options: UseTabNotificationsOptions): UseTabNotificationsResult {
  const {
    originalTitle,
    enableFaviconBadge = true,
    badgeColor = '#ef4444',
    badgeTextColor = '#ffffff'
  } = options;

  const [unreadCount, setUnreadCountState] = useState<number>(0);
  const originalFaviconRef = useRef<string | null>(null);
  const faviconLinkRef = useRef<HTMLLinkElement | null>(null);

  // Store original favicon on mount
  useEffect(() => {
    if (typeof window === 'undefined') return;

    const link = document.querySelector<HTMLLinkElement>("link[rel~='icon']");
    if (link) {
      originalFaviconRef.current = link.href;
      faviconLinkRef.current = link;
    }

    return () => {
      // Restore original on unmount
      if (link && originalFaviconRef.current) {
        link.href = originalFaviconRef.current;
      }
      document.title = originalTitle;
    };
  }, [originalTitle]);

  /**
   * Update page title with unread count
   */
  useEffect(() => {
    if (unreadCount > 0) {
      document.title = `(${unreadCount} new) ${originalTitle}`;
    } else {
      document.title = originalTitle;
    }
  }, [unreadCount, originalTitle]);

  /**
   * Update favicon with badge
   */
  useEffect(() => {
    if (!enableFaviconBadge || typeof window === 'undefined') return;

    if (unreadCount > 0) {
      drawFaviconBadge(unreadCount, badgeColor, badgeTextColor);
    } else if (originalFaviconRef.current && faviconLinkRef.current) {
      faviconLinkRef.current.href = originalFaviconRef.current;
    }
  }, [unreadCount, enableFaviconBadge, badgeColor, badgeTextColor]);

  /**
   * Clear unread count when tab gains focus
   */
  useEffect(() => {
    const handleFocus = () => {
      if (unreadCount > 0) {
        setUnreadCountState(0);
      }
    };

    window.addEventListener('focus', handleFocus);
    return () => window.removeEventListener('focus', handleFocus);
  }, [unreadCount]);

  /**
   * Set unread count
   */
  const setUnreadCount = (count: number) => {
    setUnreadCountState(Math.max(0, count));
  };

  /**
   * Clear unread count
   */
  const clearUnread = () => {
    setUnreadCountState(0);
  };

  /**
   * Draw badge on favicon
   */
  const drawFaviconBadge = (count: number, bgColor: string, textColor: string) => {
    if (!originalFaviconRef.current || !faviconLinkRef.current) return;

    const canvas = document.createElement('canvas');
    const size = 32;
    canvas.width = size;
    canvas.height = size;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Load original favicon
    const img = new Image();
    img.crossOrigin = 'anonymous';

    img.onload = () => {
      // Draw original icon
      ctx.drawImage(img, 0, 0, size, size);

      // Draw badge
      const badgeSize = size * 0.6;
      const badgeX = size - badgeSize;
      const badgeY = size - badgeSize;

      // Badge background
      ctx.fillStyle = bgColor;
      ctx.beginPath();
      ctx.arc(badgeX + badgeSize / 2, badgeY + badgeSize / 2, badgeSize / 2, 0, 2 * Math.PI);
      ctx.fill();

      // Badge text
      ctx.fillStyle = textColor;
      ctx.font = `bold ${badgeSize * 0.6}px Arial`;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      const badgeText = count > 99 ? '99+' : count.toString();
      ctx.fillText(badgeText, badgeX + badgeSize / 2, badgeY + badgeSize / 2);

      // Update favicon
      if (faviconLinkRef.current) {
        faviconLinkRef.current.href = canvas.toDataURL('image/png');
      }
    };

    img.onerror = () => {
      // Fallback: draw simple badge without icon
      ctx.fillStyle = bgColor;
      ctx.fillRect(0, 0, size, size);

      ctx.fillStyle = textColor;
      ctx.font = `bold ${size * 0.5}px Arial`;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      const badgeText = count > 99 ? '99+' : count.toString();
      ctx.fillText(badgeText, size / 2, size / 2);

      if (faviconLinkRef.current) {
        faviconLinkRef.current.href = canvas.toDataURL('image/png');
      }
    };

    img.src = originalFaviconRef.current;
  };

  return {
    setUnreadCount,
    clearUnread,
    unreadCount
  };
}
