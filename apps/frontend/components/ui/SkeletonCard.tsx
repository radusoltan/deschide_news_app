/**
 * SkeletonCard Component
 *
 * Premium skeleton loading states for article cards using Deschide brand colors.
 * Provides smooth, accessible loading placeholders with Oxford Blue gradient.
 *
 * Features:
 * - Brand-aligned Oxford Blue gradient animation
 * - Multiple layout variants (horizontal, vertical, compact)
 * - Responsive sizing
 * - Performance optimized (GPU-accelerated)
 * - Respects prefers-reduced-motion
 *
 * Usage:
 * ```tsx
 * <SkeletonCard variant="article" />
 * <SkeletonCard variant="hero" />
 * <SkeletonCard variant="compact" count={3} />
 * ```
 */

import React from 'react';

export interface SkeletonCardProps {
  /** Card variant/layout */
  variant?: 'article' | 'hero' | 'compact' | 'list' | 'horizontal';
  /** Number of skeleton cards to render */
  count?: number;
  /** Custom className for additional styling */
  className?: string;
  /** Show image skeleton */
  showImage?: boolean;
  /** Show category badge skeleton */
  showCategory?: boolean;
}

/**
 * Base Skeleton Element
 * Reusable animated skeleton shape
 */
function SkeletonElement({
  className = '',
  variant = 'dark',
}: {
  className?: string;
  variant?: 'light' | 'dark';
}) {
  const variantClass = variant === 'light' ? 'skeleton-brand' : 'skeleton-brand-dark';
  return <div className={`${variantClass} rounded ${className}`} />;
}

/**
 * Article Card Skeleton (Standard vertical card)
 */
export function ArticleCardSkeleton({
  showImage = true,
  showCategory = true,
  className = '',
}: Partial<SkeletonCardProps>) {
  return (
    <div
      className={`bg-white rounded-lg shadow-sm overflow-hidden animate-fade-in ${className}`}
      role="status"
      aria-label="Loading article"
    >
      {/* Image Skeleton */}
      {showImage && (
        <SkeletonElement className="w-full h-48 rounded-t-lg rounded-b-none" />
      )}

      {/* Content */}
      <div className="p-4 space-y-3">
        {/* Category Badge */}
        {showCategory && (
          <SkeletonElement className="h-6 w-24" />
        )}

        {/* Title - 2 lines */}
        <div className="space-y-2">
          <SkeletonElement className="h-6 w-full" />
          <SkeletonElement className="h-6 w-4/5" />
        </div>

        {/* Excerpt - 3 lines */}
        <div className="space-y-2 pt-2">
          <SkeletonElement className="h-4 w-full" />
          <SkeletonElement className="h-4 w-full" />
          <SkeletonElement className="h-4 w-3/4" />
        </div>

        {/* Meta info (author, date) */}
        <div className="flex items-center gap-3 pt-3">
          <SkeletonElement className="h-8 w-8 rounded-full" />
          <div className="flex-1 space-y-2">
            <SkeletonElement className="h-3 w-24" />
            <SkeletonElement className="h-3 w-20" />
          </div>
        </div>
      </div>

      <span className="sr-only">Loading article card...</span>
    </div>
  );
}

/**
 * Hero Card Skeleton (Large featured card)
 */
export function HeroCardSkeleton({
  className = '',
}: Partial<SkeletonCardProps>) {
  return (
    <div
      className={`bg-white rounded-xl shadow-lg overflow-hidden animate-fade-in ${className}`}
      role="status"
      aria-label="Loading hero article"
    >
      {/* Large Image */}
      <SkeletonElement className="w-full h-96 rounded-t-xl rounded-b-none" />

      {/* Content */}
      <div className="p-6 space-y-4">
        {/* Category Badge */}
        <SkeletonElement className="h-7 w-32" />

        {/* Title - 3 lines (larger) */}
        <div className="space-y-3">
          <SkeletonElement className="h-8 w-full" />
          <SkeletonElement className="h-8 w-full" />
          <SkeletonElement className="h-8 w-3/4" />
        </div>

        {/* Excerpt - 4 lines */}
        <div className="space-y-2 pt-2">
          <SkeletonElement className="h-5 w-full" />
          <SkeletonElement className="h-5 w-full" />
          <SkeletonElement className="h-5 w-full" />
          <SkeletonElement className="h-5 w-4/5" />
        </div>

        {/* Meta info */}
        <div className="flex items-center gap-4 pt-4">
          <SkeletonElement className="h-10 w-10 rounded-full" />
          <div className="flex-1 space-y-2">
            <SkeletonElement className="h-4 w-32" />
            <SkeletonElement className="h-3 w-24" />
          </div>
        </div>
      </div>

      <span className="sr-only">Loading hero article...</span>
    </div>
  );
}

/**
 * Compact Card Skeleton (Small card for lists)
 */
export function CompactCardSkeleton({
  showImage = true,
  className = '',
}: Partial<SkeletonCardProps>) {
  return (
    <div
      className={`bg-white rounded-lg shadow-sm p-3 animate-fade-in ${className}`}
      role="status"
      aria-label="Loading compact article"
    >
      <div className="flex gap-3">
        {/* Small Image */}
        {showImage && (
          <SkeletonElement className="w-20 h-20 flex-shrink-0 rounded" />
        )}

        {/* Content */}
        <div className="flex-1 space-y-2">
          {/* Title - 2 lines */}
          <SkeletonElement className="h-4 w-full" />
          <SkeletonElement className="h-4 w-4/5" />

          {/* Meta */}
          <SkeletonElement className="h-3 w-24 mt-auto" />
        </div>
      </div>

      <span className="sr-only">Loading compact article...</span>
    </div>
  );
}

/**
 * Horizontal Card Skeleton (Wide layout)
 */
export function HorizontalCardSkeleton({
  showCategory = true,
  className = '',
}: Partial<SkeletonCardProps>) {
  return (
    <div
      className={`bg-white rounded-lg shadow-sm overflow-hidden animate-fade-in ${className}`}
      role="status"
      aria-label="Loading article"
    >
      <div className="flex flex-col sm:flex-row">
        {/* Image */}
        <SkeletonElement className="w-full sm:w-1/3 h-48 sm:h-auto rounded-t-lg sm:rounded-l-lg sm:rounded-tr-none" />

        {/* Content */}
        <div className="flex-1 p-5 space-y-3">
          {/* Category */}
          {showCategory && (
            <SkeletonElement className="h-6 w-28" />
          )}

          {/* Title - 2 lines */}
          <div className="space-y-2">
            <SkeletonElement className="h-6 w-full" />
            <SkeletonElement className="h-6 w-4/5" />
          </div>

          {/* Excerpt - 3 lines */}
          <div className="space-y-2 pt-2">
            <SkeletonElement className="h-4 w-full" />
            <SkeletonElement className="h-4 w-full" />
            <SkeletonElement className="h-4 w-3/5" />
          </div>

          {/* Meta */}
          <div className="flex items-center gap-3 pt-3">
            <SkeletonElement className="h-3 w-20" />
            <SkeletonElement className="h-3 w-16" />
          </div>
        </div>
      </div>

      <span className="sr-only">Loading horizontal article...</span>
    </div>
  );
}

/**
 * List Item Skeleton (Minimal, text-focused)
 */
export function ListItemSkeleton({
  className = '',
}: Partial<SkeletonCardProps>) {
  return (
    <div
      className={`border-b border-gray-100 py-4 animate-fade-in ${className}`}
      role="status"
      aria-label="Loading list item"
    >
      <div className="space-y-2">
        {/* Title */}
        <SkeletonElement className="h-5 w-full" />
        <SkeletonElement className="h-5 w-3/4" />

        {/* Meta */}
        <div className="flex items-center gap-4 pt-2">
          <SkeletonElement className="h-3 w-24" />
          <SkeletonElement className="h-3 w-20" />
        </div>
      </div>

      <span className="sr-only">Loading list item...</span>
    </div>
  );
}

/**
 * Main SkeletonCard Component
 * Smart wrapper that renders appropriate variant
 */
export function SkeletonCard({
  variant = 'article',
  count = 1,
  className = '',
  showImage = true,
  showCategory = true,
}: SkeletonCardProps) {
  const skeletons = Array.from({ length: count }, (_, i) => i);

  const renderSkeleton = (index: number) => {
    const key = `skeleton-${variant}-${index}`;
    const staggerClass = index > 0 ? `stagger-${Math.min(index, 6)}` : '';

    switch (variant) {
      case 'hero':
        return <HeroCardSkeleton key={key} className={staggerClass} />;
      case 'compact':
        return (
          <CompactCardSkeleton
            key={key}
            showImage={showImage}
            className={staggerClass}
          />
        );
      case 'horizontal':
        return (
          <HorizontalCardSkeleton
            key={key}
            showCategory={showCategory}
            className={staggerClass}
          />
        );
      case 'list':
        return <ListItemSkeleton key={key} className={staggerClass} />;
      case 'article':
      default:
        return (
          <ArticleCardSkeleton
            key={key}
            showImage={showImage}
            showCategory={showCategory}
            className={staggerClass}
          />
        );
    }
  };

  return (
    <div className={className}>
      {skeletons.map((_, index) => renderSkeleton(index))}
    </div>
  );
}

/**
 * Skeleton Grid
 * Responsive grid of skeleton cards
 */
export interface SkeletonGridProps extends SkeletonCardProps {
  /** Grid columns (responsive) */
  columns?: {
    sm?: number;
    md?: number;
    lg?: number;
  };
}

export function SkeletonGrid({
  variant = 'article',
  count = 6,
  className = '',
  columns = { sm: 1, md: 2, lg: 3 },
  ...props
}: SkeletonGridProps) {
  const gridCols = {
    sm: columns.sm || 1,
    md: columns.md || 2,
    lg: columns.lg || 3,
  };

  return (
    <div
      className={`
        grid
        grid-cols-${gridCols.sm}
        md:grid-cols-${gridCols.md}
        lg:grid-cols-${gridCols.lg}
        gap-6
        ${className}
      `}
    >
      <SkeletonCard variant={variant} count={count} {...props} />
    </div>
  );
}

export default SkeletonCard;
