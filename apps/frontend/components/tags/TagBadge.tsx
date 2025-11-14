/**
 * TagBadge Component
 * Renders a single tag as a clickable badge/chip
 */

import Link from 'next/link';
import { Tag } from '@/lib/types/tag';

export interface TagBadgeProps {
  tag: Tag;
  locale: string;
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
    default: 'bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200',
    outline: 'border border-blue-300 text-blue-700 hover:bg-blue-50 dark:border-blue-700 dark:text-blue-300 dark:hover:bg-blue-900',
    solid: 'bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600',
  };

  return (
    <Link
      href={`/${locale}/tags/${tag.slug}`}
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
