'use client';

import { useState, useEffect, useRef, ImgHTMLAttributes } from 'react';

/**
 * Props for LazyImage component
 */
interface LazyImageProps extends Omit<ImgHTMLAttributes<HTMLImageElement>, 'src' | 'placeholder'> {
  /**
   * Image source URL
   */
  src: string;

  /**
   * Alternative text for accessibility
   */
  alt: string;

  /**
   * Placeholder image URL (optional, uses blur by default)
   */
  placeholder?: string;

  /**
   * Threshold for IntersectionObserver (0-1)
   * @default 0.1
   */
  threshold?: number;

  /**
   * Root margin for IntersectionObserver
   * @default '50px'
   */
  rootMargin?: string;

  /**
   * Callback when image loads
   */
  onLoad?: () => void;

  /**
   * Callback when image fails to load
   */
  onError?: () => void;

  /**
   * Custom loading component
   */
  loadingComponent?: React.ReactNode;

  /**
   * Show loading skeleton
   * @default true
   */
  showSkeleton?: boolean;
}

/**
 * Lazy loading image component with IntersectionObserver
 *
 * Features:
 * - Only loads images when they enter viewport
 * - Blur-up placeholder effect
 * - Loading skeleton
 * - Error handling
 * - Accessible alt text
 *
 * @example
 * ```tsx
 * <LazyImage
 *   src="https://example.com/image.jpg"
 *   alt="Description of image"
 *   className="w-full h-64 object-cover rounded-lg"
 *   onLoad={() => console.log('Image loaded')}
 * />
 * ```
 */
export function LazyImage({
  src,
  alt,
  placeholder,
  threshold = 0.1,
  rootMargin = '50px',
  onLoad,
  onError,
  loadingComponent,
  showSkeleton = true,
  className = '',
  ...props
}: LazyImageProps) {
  const [isLoaded, setIsLoaded] = useState(false);
  const [isInView, setIsInView] = useState(false);
  const [hasError, setHasError] = useState(false);
  const imgRef = useRef<HTMLImageElement>(null);
  const observerRef = useRef<IntersectionObserver | null>(null);

  /**
   * Setup IntersectionObserver
   */
  useEffect(() => {
    if (!imgRef.current) return;

    observerRef.current = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setIsInView(true);
            // Stop observing once in view
            if (observerRef.current && imgRef.current) {
              observerRef.current.unobserve(imgRef.current);
            }
          }
        });
      },
      {
        threshold,
        rootMargin
      }
    );

    observerRef.current.observe(imgRef.current);

    return () => {
      if (observerRef.current) {
        observerRef.current.disconnect();
      }
    };
  }, [threshold, rootMargin]);

  /**
   * Handle image load
   */
  const handleLoad = () => {
    setIsLoaded(true);
    setHasError(false);
    onLoad?.();
  };

  /**
   * Handle image error
   */
  const handleError = () => {
    setHasError(true);
    setIsLoaded(false);
    onError?.();
  };

  return (
    <div className={`relative overflow-hidden ${className}`} ref={imgRef}>
      {/* Loading Skeleton */}
      {!isLoaded && !hasError && showSkeleton && (
        <div className="absolute inset-0 bg-gray-200 dark:bg-gray-700 animate-pulse">
          {loadingComponent || (
            <div className="flex items-center justify-center h-full">
              <svg
                className="w-8 h-8 text-gray-400"
                fill="currentColor"
                viewBox="0 0 20 20"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  fillRule="evenodd"
                  d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                  clipRule="evenodd"
                />
              </svg>
            </div>
          )}
        </div>
      )}

      {/* Placeholder Image (blur-up) */}
      {!isLoaded && placeholder && !hasError && (
        <img
          src={placeholder}
          alt=""
          className="absolute inset-0 w-full h-full object-cover filter blur-sm scale-110"
          aria-hidden="true"
        />
      )}

      {/* Actual Image */}
      {isInView && !hasError && (
        <img
          src={src}
          alt={alt}
          onLoad={handleLoad}
          onError={handleError}
          className={`w-full h-full object-cover transition-opacity duration-300 ${
            isLoaded ? 'opacity-100' : 'opacity-0'
          }`}
          loading="lazy"
          {...props}
        />
      )}

      {/* Error State */}
      {hasError && (
        <div className="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-800">
          <div className="text-center text-gray-500 dark:text-gray-400">
            <svg
              className="w-12 h-12 mx-auto mb-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <p className="text-sm">Failed to load image</p>
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * Utility: Preload images
 */
export function preloadImage(src: string): Promise<void> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve();
    img.onerror = reject;
    img.src = src;
  });
}

/**
 * Utility: Preload multiple images
 */
export async function preloadImages(sources: string[]): Promise<void> {
  await Promise.all(sources.map(preloadImage));
}
