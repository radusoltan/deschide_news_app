/**
 * Card Skeleton Component
 * Loading placeholder for article cards with shimmer animation
 */

interface CardSkeletonProps {
  count?: number;
  variant?: 'default' | 'horizontal' | 'minimal';
}

export default function CardSkeleton({ count = 3, variant = 'default' }: CardSkeletonProps) {
  const skeletons = Array.from({ length: count }, (_, i) => i);

  if (variant === 'horizontal') {
    return (
      <div className="space-y-4">
        {skeletons.map((i) => (
          <div key={i} className="animate-pulse flex gap-4 p-4 bg-white rounded-lg border border-gray-200">
            {/* Thumbnail */}
            <div className="w-24 h-16 bg-gray-200 rounded flex-shrink-0"></div>

            {/* Content */}
            <div className="flex-1 space-y-2">
              <div className="h-4 bg-gray-200 rounded w-3/4"></div>
              <div className="h-3 bg-gray-200 rounded w-1/2"></div>
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (variant === 'minimal') {
    return (
      <div className="space-y-3">
        {skeletons.map((i) => (
          <div key={i} className="animate-pulse space-y-2">
            <div className="h-4 bg-gray-200 rounded w-full"></div>
            <div className="h-3 bg-gray-200 rounded w-2/3"></div>
          </div>
        ))}
      </div>
    );
  }

  // Default variant - full card
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      {skeletons.map((i) => (
        <div key={i} className="animate-pulse bg-white rounded-lg border border-gray-200 overflow-hidden">
          {/* Image */}
          <div className="aspect-video bg-gray-200"></div>

          {/* Content */}
          <div className="p-4 space-y-3">
            {/* Category Badge */}
            <div className="h-5 w-20 bg-gray-200 rounded-full"></div>

            {/* Title */}
            <div className="space-y-2">
              <div className="h-5 bg-gray-200 rounded w-full"></div>
              <div className="h-5 bg-gray-200 rounded w-4/5"></div>
            </div>

            {/* Lead */}
            <div className="space-y-2">
              <div className="h-4 bg-gray-200 rounded w-full"></div>
              <div className="h-4 bg-gray-200 rounded w-5/6"></div>
            </div>

            {/* Meta */}
            <div className="flex items-center gap-4">
              <div className="h-3 w-24 bg-gray-200 rounded"></div>
              <div className="h-3 w-20 bg-gray-200 rounded"></div>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}
