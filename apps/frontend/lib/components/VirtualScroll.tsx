'use client';

import { useEffect, useRef, useState, useCallback, ReactNode } from 'react';

/**
 * Options for virtual scroll
 */
interface VirtualScrollOptions {
  /**
   * Total number of items
   */
  itemCount: number;

  /**
   * Estimated height of each item in pixels
   * @default 100
   */
  itemHeight?: number;

  /**
   * Number of items to render outside visible area (buffer)
   * @default 5
   */
  overscan?: number;

  /**
   * Container height in pixels (if not provided, uses viewport height)
   */
  height?: number;

  /**
   * Scroll threshold for loading more items (in pixels from bottom)
   * @default 500
   */
  loadMoreThreshold?: number;

  /**
   * Callback when scrolled near bottom
   */
  onLoadMore?: () => void;
}

/**
 * Virtual scroll props
 */
interface VirtualScrollProps<T> extends VirtualScrollOptions {
  /**
   * Array of items to render
   */
  items: T[];

  /**
   * Render function for each item
   */
  renderItem: (item: T, index: number) => ReactNode;

  /**
   * Custom className
   */
  className?: string;

  /**
   * Loading state
   */
  isLoading?: boolean;

  /**
   * Loading component
   */
  loadingComponent?: ReactNode;
}

/**
 * Virtual scroll component for efficient rendering of large lists
 *
 * Only renders visible items plus a small buffer, dramatically improving
 * performance for lists with 100+ items.
 *
 * @example
 * ```tsx
 * <VirtualScroll
 *   items={posts}
 *   itemCount={posts.length}
 *   itemHeight={200}
 *   renderItem={(post, index) => (
 *     <PostCard key={post.id} post={post} />
 *   )}
 *   onLoadMore={loadMorePosts}
 * />
 * ```
 */
export function VirtualScroll<T>({
  items,
  itemCount,
  itemHeight = 100,
  overscan = 5,
  height,
  loadMoreThreshold = 500,
  onLoadMore,
  renderItem,
  className = '',
  isLoading = false,
  loadingComponent
}: VirtualScrollProps<T>) {
  const containerRef = useRef<HTMLDivElement>(null);
  const [scrollTop, setScrollTop] = useState(0);
  const [containerHeight, setContainerHeight] = useState(height || 0);

  // Calculate visible range
  const startIndex = Math.max(0, Math.floor(scrollTop / itemHeight) - overscan);
  const endIndex = Math.min(
    itemCount,
    Math.ceil((scrollTop + containerHeight) / itemHeight) + overscan
  );

  const visibleItems = items.slice(startIndex, endIndex);

  // Total height of all items
  const totalHeight = itemCount * itemHeight;

  // Offset for positioning visible items
  const offsetY = startIndex * itemHeight;

  /**
   * Handle scroll event
   */
  const handleScroll = useCallback(() => {
    if (!containerRef.current) return;

    const newScrollTop = containerRef.current.scrollTop;
    setScrollTop(newScrollTop);

    // Check if near bottom for load more
    if (onLoadMore && !isLoading) {
      const scrollHeight = containerRef.current.scrollHeight;
      const clientHeight = containerRef.current.clientHeight;
      const distanceFromBottom = scrollHeight - (newScrollTop + clientHeight);

      if (distanceFromBottom < loadMoreThreshold) {
        onLoadMore();
      }
    }
  }, [onLoadMore, isLoading, loadMoreThreshold]);

  /**
   * Update container height on mount/resize
   */
  useEffect(() => {
    if (!containerRef.current) return;

    const updateHeight = () => {
      if (containerRef.current) {
        setContainerHeight(height || containerRef.current.clientHeight);
      }
    };

    updateHeight();

    const resizeObserver = new ResizeObserver(updateHeight);
    resizeObserver.observe(containerRef.current);

    return () => resizeObserver.disconnect();
  }, [height]);

  /**
   * Attach scroll listener
   */
  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;

    container.addEventListener('scroll', handleScroll, { passive: true });
    return () => container.removeEventListener('scroll', handleScroll);
  }, [handleScroll]);

  return (
    <div
      ref={containerRef}
      className={`relative overflow-y-auto ${className}`}
      style={{ height: height ? `${height}px` : '100%' }}
      role="list"
      aria-label="Scrollable list"
    >
      {/* Spacer for total height */}
      <div style={{ height: `${totalHeight}px`, position: 'relative' }}>
        {/* Visible items container */}
        <div
          style={{
            position: 'absolute',
            top: 0,
            left: 0,
            right: 0,
            transform: `translateY(${offsetY}px)`
          }}
        >
          {visibleItems.map((item, index) => (
            <div
              key={startIndex + index}
              style={{ minHeight: `${itemHeight}px` }}
              role="listitem"
            >
              {renderItem(item, startIndex + index)}
            </div>
          ))}
        </div>
      </div>

      {/* Loading indicator */}
      {isLoading && (
        <div className="flex items-center justify-center py-8">
          {loadingComponent || (
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          )}
        </div>
      )}
    </div>
  );
}
