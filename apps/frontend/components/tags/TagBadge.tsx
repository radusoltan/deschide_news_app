/**
 * TagBadge Component
 * Renders a single tag as a clickable badge/chip
 */

import Link from 'next/link';
import { Tag } from '@/lib/types/tag';
import { buildLocalizedUrl } from '@/lib/utils/url-builder';
import type { Locale } from '@/lib/types';

export interface TagBadgeProps {
  tag: Tag;
  locale: Locale;
  variant?: 'default' | 'outline' | 'solid';
  size?: 'sm' | 'md' | 'lg';
  showHash?: boolean;
  className?: string;
}

/**
 * TagBadge - Clickable tag badge with multiple variants and sizes
 *
 * @param tag - Tag object to display
 * @param locale - Current locale for routing
 * @param variant - Visual variant (default, outline, solid)
 * @param size - Size variant (sm, md, lg)
 * @param showHash - Whether to show # prefix (default: true)
 * @param className - Additional CSS classes
 */
export default function TagBadge({
  tag,
  locale,
  variant = 'default',
  size = 'md',
  showHash = true,
  className = '',
}: TagBadgeProps) {
  const sizeClasses = {
    sm: 'text-xs px-2 py-0.5',
    md: 'text-sm px-3 py-1',
    lg: 'text-base px-4 py-2',
  };

  const variantClasses = {
    default: 'bg-[var(--color-surface-sunken)] text-[var(--color-text-primary)] hover:bg-[var(--color-surface-sunken)]',
    outline: 'border border-[var(--color-border)] text-[var(--color-text-primary)] hover:bg-[var(--color-surface-sunken)]',
    solid: 'bg-[var(--color-accent)] text-white hover:bg-[var(--color-accent)]',
  };

  return (
    <Link
      href={buildLocalizedUrl(`/tags/${tag.slug}`, locale)}
      className={`
        inline-flex items-center rounded-full font-medium
        transition-colors duration-200
        ${sizeClasses[size]}
        ${variantClasses[variant]}
        ${className}
      `}
      title={tag.description || tag.name}
    >
      {showHash && '#'}
      {tag.name}
      {tag.usageCount > 0 && size !== 'sm' && (
        <span className="ml-1 opacity-75 text-xs">
          ({tag.usageCount})
        </span>
      )}
    </Link>
  );
}
