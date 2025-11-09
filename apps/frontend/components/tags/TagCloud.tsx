/**
 * TagCloud Component
 * Renders tags in a cloud layout with sizes based on usage count
 */

import Link from 'next/link';
import { Tag } from '@/lib/types/tag';

export interface TagCloudProps {
  tags: Tag[];
  locale: string;
  className?: string;
  minSize?: string;
  maxSize?: string;
  showHash?: boolean;
}

/**
 * TagCloud - Display tags with variable font sizes based on usage
 *
 * Tags with higher usage count appear larger, creating a visual
 * representation of popular topics.
 *
 * @param tags - Array of tag objects (should be sorted by usageCount)
 * @param locale - Current locale for routing
 * @param className - Additional CSS classes for container
 * @param minSize - Minimum font size (Tailwind class, default: text-sm)
 * @param maxSize - Maximum font size (Tailwind class, default: text-3xl)
 * @param showHash - Whether to show # prefix (default: true)
 */
export default function TagCloud({
  tags,
  locale,
  className = '',
  minSize = 'text-sm',
  maxSize = 'text-3xl',
  showHash = true,
}: TagCloudProps) {
  if (!tags || tags.length === 0) {
    return null;
  }

  // Calculate font sizes based on usage count
  const maxCount = Math.max(...tags.map((t) => t.usageCount));
  const minCount = Math.min(...tags.map((t) => t.usageCount));

  const getFontSize = (count: number): string => {
    // If all tags have same usage, use medium size
    if (maxCount === minCount) {
      return 'text-base';
    }

    // Calculate ratio (0 to 1)
    const ratio = (count - minCount) / (maxCount - minCount);

    // Map to font size classes
    if (ratio > 0.8) return maxSize; // Very popular
    if (ratio > 0.6) return 'text-2xl'; // Popular
    if (ratio > 0.4) return 'text-xl'; // Above average
    if (ratio > 0.2) return 'text-lg'; // Average
    return minSize; // Below average
  };

  const getOpacity = (count: number): string => {
    if (maxCount === minCount) return 'opacity-100';

    const ratio = (count - minCount) / (maxCount - minCount);

    if (ratio > 0.8) return 'opacity-100';
    if (ratio > 0.6) return 'opacity-90';
    if (ratio > 0.4) return 'opacity-80';
    if (ratio > 0.2) return 'opacity-70';
    return 'opacity-60';
  };

  return (
    <div
      className={`flex flex-wrap gap-3 items-center justify-center ${className}`}
    >
      {tags.map((tag) => (
        <Link
          key={tag.id}
          href={`/${locale}/tags/${tag.slug}`}
          className={`
            ${getFontSize(tag.usageCount)}
            ${getOpacity(tag.usageCount)}
            text-blue-600 hover:text-blue-800
            dark:text-blue-400 dark:hover:text-blue-300
            hover:underline transition-all duration-200
            font-medium
          `}
          title={`${tag.name} (${tag.usageCount} articles)`}
        >
          {showHash && '#'}
          {tag.name}
        </Link>
      ))}
    </div>
  );
}
