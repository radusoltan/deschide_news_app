'use client';

import { Eye } from 'lucide-react';
import { useEffect, useState } from 'react';
import { getLiveTextViewerCount } from '@/lib/api/livetext-analytics';

interface ViewerCounterProps {
  liveTextId: number;
  /**
   * Update interval in milliseconds
   * @default 30000 (30 seconds)
   */
  updateInterval?: number;
  /**
   * Show animated pulse indicator
   * @default true
   */
  showPulse?: boolean;
  /**
   * Custom className
   */
  className?: string;
}

/**
 * Real-time viewer counter component
 *
 * Displays current number of viewers watching a LiveText with automatic updates
 *
 * @example
 * ```tsx
 * <ViewerCounter liveTextId={123} />
 * ```
 */
export function ViewerCounter({
  liveTextId,
  updateInterval = 30000,
  showPulse = true,
  className = ''
}: ViewerCounterProps) {
  const [viewerCount, setViewerCount] = useState<number>(0);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [previousCount, setPreviousCount] = useState<number>(0);
  const [isAnimating, setIsAnimating] = useState<boolean>(false);

  // Fetch viewer count
  const fetchViewerCount = async () => {
    try {
      const { currentViewers } = await getLiveTextViewerCount(liveTextId);

      // Trigger animation if count changed
      if (currentViewers !== viewerCount && viewerCount !== 0) {
        setPreviousCount(viewerCount);
        setIsAnimating(true);
        setTimeout(() => setIsAnimating(false), 500);
      }

      setViewerCount(currentViewers);
      setError(null);
      setIsLoading(false);
    } catch (err) {
      console.error('Failed to fetch viewer count:', err);
      setError('Failed to load viewer count');
      setIsLoading(false);
    }
  };

  // Initial fetch
  useEffect(() => {
    fetchViewerCount();
  }, [liveTextId]);

  // Setup interval for updates
  useEffect(() => {
    const interval = setInterval(fetchViewerCount, updateInterval);
    return () => clearInterval(interval);
  }, [liveTextId, updateInterval, viewerCount]);

  if (error) {
    return null; // Silently fail
  }

  return (
    <div className={`flex items-center gap-2 ${className}`}>
      {/* Eye icon with optional pulse */}
      <div className="relative">
        <Eye className="h-5 w-5 text-gray-600 dark:text-gray-400" />
        {showPulse && viewerCount > 0 && (
          <span className="absolute -top-1 -right-1">
            <span className="flex h-2 w-2">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
            </span>
          </span>
        )}
      </div>

      {/* Viewer count with animation */}
      <div className="flex items-baseline gap-1">
        <span
          className={`font-semibold text-gray-900 dark:text-gray-100 transition-all duration-500 ${
            isAnimating ? 'scale-110 text-red-600' : ''
          }`}
        >
          {isLoading ? '...' : viewerCount.toLocaleString()}
        </span>
        <span className="text-sm text-gray-600 dark:text-gray-400">
          {viewerCount === 1 ? 'viewer' : 'viewers'}
        </span>
      </div>

      {/* Live indicator badge */}
      {viewerCount > 0 && (
        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
          LIVE
        </span>
      )}
    </div>
  );
}

/**
 * Compact version of ViewerCounter (for use in cards or lists)
 */
export function ViewerCounterCompact({
  liveTextId,
  updateInterval = 30000
}: Pick<ViewerCounterProps, 'liveTextId' | 'updateInterval'>) {
  const [viewerCount, setViewerCount] = useState<number>(0);

  useEffect(() => {
    const fetchCount = async () => {
      try {
        const { currentViewers } = await getLiveTextViewerCount(liveTextId);
        setViewerCount(currentViewers);
      } catch (err) {
        console.error('Failed to fetch viewer count:', err);
      }
    };

    fetchCount();
    const interval = setInterval(fetchCount, updateInterval);
    return () => clearInterval(interval);
  }, [liveTextId, updateInterval]);

  return (
    <div className="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400">
      <Eye className="h-4 w-4" />
      <span>{viewerCount.toLocaleString()}</span>
    </div>
  );
}
