/**
 * TagList Component
 * Renders a list of tags using TagBadge components
 */

import { Tag } from '@/lib/types/tag';
import TagBadge, { TagBadgeProps } from './TagBadge';
import type { Locale } from '@/lib/types';

export interface TagListProps {
  tags: Tag[];
  locale: string | Locale;
  variant?: TagBadgeProps['variant'];
  size?: TagBadgeProps['size'];
  showHash?: boolean;
  maxTags?: number;
  className?: string;
  showEmpty?: boolean;
  emptyMessage?: string;
}

/**
 * TagList - Display a list of tags as badges
 *
 * @param tags - Array of tag objects
 * @param locale - Current locale for routing
 * @param variant - Visual variant for badges
 * @param size - Size variant for badges
 * @param showHash - Whether to show # prefix
 * @param maxTags - Maximum number of tags to display
 * @param className - Additional CSS classes for container
 * @param showEmpty - Whether to show message when no tags
 * @param emptyMessage - Custom message for empty state
 */
export default function TagList({
  tags,
  locale,
  variant = 'default',
  size = 'md',
  showHash = true,
  maxTags,
  className = '',
  showEmpty = false,
  emptyMessage = 'No tags',
}: TagListProps) {
  // Handle empty tags
  if (!tags || tags.length === 0) {
    if (showEmpty) {
      return (
        <div className={`text-secondary text-sm italic ${className}`}>
          {emptyMessage}
        </div>
      );
    }
    return null;
  }

  // Limit tags if maxTags is specified
  const displayTags = maxTags ? tags.slice(0, maxTags) : tags;
  const hasMore = maxTags && tags.length > maxTags;

  return (
    <div className={`flex flex-wrap gap-2 items-center ${className}`}>
      {displayTags.map((tag) => (
        <TagBadge
          key={tag.id}
          tag={tag}
          locale={locale as Locale}
          variant={variant}
          size={size}
          showHash={showHash}
        />
      ))}
      {hasMore && (
        <span className="text-sm text-secondary ml-1">
          +{tags.length - maxTags} more
        </span>
      )}
    </div>
  );
}
