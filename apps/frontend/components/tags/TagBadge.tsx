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
    default: 'bg-brand-oxford-100 text-brand-oxford-900 hover:bg-brand-oxford-200 dark:bg-brand-oxford-900 dark:text-white',
    outline: 'border border-brand-oxford-300 text-brand-oxford-900 hover:bg-brand-oxford-50 dark:border-brand-oxford-700 dark:text-white dark:hover:bg-brand-oxford-800',
    solid: 'bg-brand-tomato text-white hover:bg-brand-tomato-600 dark:bg-brand-tomato-600 dark:hover:bg-brand-tomato-500',
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
