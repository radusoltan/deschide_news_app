/**
 * View Count Badge Component
 * Displays article view count in a formatted, human-readable way
 * Only shows for articles with >100 views
 */

interface Props {
  views: number;
  className?: string;
}

export function ViewCountBadge({ views, className = '' }: Props) {
  // Only show for articles with >100 views
  if (views < 100) {
    return null;
  }

  const formatViews = (count: number): string => {
    if (count >= 1000000) {
      return `${(count / 1000000).toFixed(1)}M`;
    } else if (count >= 1000) {
      return `${(count / 1000).toFixed(1)}k`;
    }
    return count.toString();
  };

  return (
    <span className={`inline-flex items-center gap-1 text-sm text-gray-600 ${className}`}>
      <svg
        className="w-4 h-4"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
        />
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
        />
      </svg>
      <span>{formatViews(views)} views</span>
    </span>
  );
}
