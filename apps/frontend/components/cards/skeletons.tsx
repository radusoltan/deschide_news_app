/**
 * Card Skeleton Components
 * Loading placeholders for the new card system
 */

interface SkeletonProps {
  className?: string;
}

export function HeroCardSkeleton({ className = '' }: SkeletonProps) {
  return (
    <div className={`h-full min-h-[320px] lg:min-h-[400px] rounded-[var(--radius-card)] bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] animate-pulse overflow-hidden ${className}`}>
      {/* Large image block */}
      <div className="w-full h-full relative">
        {/* Content at bottom */}
        <div className="absolute inset-x-0 bottom-0 p-6 @lg:p-8 space-y-3">
          {/* Category badge */}
          <div className="w-20 h-6 bg-black/20 rounded-[var(--radius-sm)]"></div>
          {/* Title lines */}
          <div className="w-full h-8 bg-black/20 rounded"></div>
          <div className="w-3/4 h-8 bg-black/20 rounded"></div>
          {/* Timestamp */}
          <div className="w-24 h-4 bg-black/20 rounded"></div>
        </div>
      </div>
    </div>
  );
}

export function FeatureCardSkeleton({ className = '' }: SkeletonProps) {
  return (
    <div className={`bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] rounded-[var(--radius-card)] overflow-hidden animate-pulse ${className}`}>
      {/* Medium image block */}
      <div className="aspect-video bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)]"></div>
      {/* Content */}
      <div className="p-5 space-y-3">
        {/* Category */}
        <div className="w-16 h-3 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        {/* Title lines */}
        <div className="w-full h-6 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        <div className="w-4/5 h-6 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        {/* Excerpt lines */}
        <div className="w-full h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        <div className="w-2/3 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
      </div>
    </div>
  );
}

export function CompactCardSkeleton({ className = '' }: SkeletonProps) {
  return (
    <div className={`flex gap-3 items-start animate-pulse ${className}`}>
      {/* Small square thumbnail */}
      <div className="flex-shrink-0 w-20 h-16 @sm:w-24 @sm:h-18 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded-[var(--radius-sm)]"></div>
      {/* Text content */}
      <div className="flex-1 min-w-0 space-y-2">
        {/* Title lines (narrow) */}
        <div className="w-full h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        <div className="w-3/4 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        {/* Timestamp */}
        <div className="w-16 h-3 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
      </div>
    </div>
  );
}

export function TextOnlyCardSkeleton({ className = '' }: SkeletonProps) {
  return (
    <div className={`p-5 rounded-[var(--radius-card)] animate-pulse border-l-4 border-[var(--color-skeleton)] dark:border-[var(--color-skeleton-dark)] ${className}`}>
      <div className="space-y-3">
        {/* Badge and category */}
        <div className="flex items-center gap-3">
          <div className="w-16 h-5 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
          <div className="w-20 h-3 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        </div>
        {/* Title lines with left border */}
        <div className="w-full h-6 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        <div className="w-5/6 h-6 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        <div className="w-3/4 h-6 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        {/* Excerpt */}
        <div className="w-full h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        <div className="w-4/5 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
      </div>
    </div>
  );
}

export function LiveCardSkeleton({ className = '' }: SkeletonProps) {
  return (
    <div className={`col-span-full @lg:col-span-8 @xl:col-span-12 bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] rounded-[var(--radius-card)] p-6 animate-pulse ${className}`}>
      <div className="space-y-4">
        {/* Live indicator and badge */}
        <div className="flex items-center gap-3">
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded-full bg-red-500 animate-breaking-pulse"></div>
            <div className="w-16 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
          </div>
          <div className="w-20 h-5 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded-full"></div>
        </div>
        {/* Title lines */}
        <div className="w-full h-7 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        <div className="w-2/3 h-7 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        {/* Update preview */}
        <div className="bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)] rounded-[var(--radius-md)] p-4">
          <div className="w-full h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded mb-2"></div>
          <div className="w-3/4 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
        </div>
      </div>
    </div>
  );
}

export function MultiSourceCardSkeleton({ className = '' }: SkeletonProps) {
  return (
    <div className={`col-span-full @lg:col-span-4 bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] rounded-[var(--radius-card)] overflow-hidden animate-pulse ${className}`}>
      {/* Image block */}
      <div className="aspect-video bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)]"></div>
      {/* Content */}
      <div className="p-5">
        {/* Title */}
        <div className="w-full h-5 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded mb-2"></div>
        <div className="w-4/5 h-5 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded mb-4"></div>
        {/* Source list */}
        <div className="border-t border-[var(--color-border)] dark:border-[var(--color-border-dark)] pt-4 space-y-2">
          <div className="w-20 h-3 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded mb-3"></div>
          {/* 3 source rows */}
          <div className="flex items-center justify-between py-2 px-3 bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)] rounded-[var(--radius-sm)]">
            <div className="w-24 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
            <div className="w-8 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
          </div>
          <div className="flex items-center justify-between py-2 px-3 bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)] rounded-[var(--radius-sm)]">
            <div className="w-20 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
            <div className="w-8 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
          </div>
          <div className="flex items-center justify-between py-2 px-3 bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)] rounded-[var(--radius-sm)]">
            <div className="w-28 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
            <div className="w-8 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded"></div>
          </div>
        </div>
      </div>
    </div>
  );
}