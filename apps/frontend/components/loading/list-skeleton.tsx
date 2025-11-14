/**
 * List Skeleton Component
 * Loading placeholder for article lists with shimmer animation
 */

interface ListSkeletonProps {
  count?: number;
  showImage?: boolean;
}

export default function ListSkeleton({ count = 5, showImage = true }: ListSkeletonProps) {
  const skeletons = Array.from({ length: count }, (_, i) => i);

  return (
    <div className="space-y-6">
      {skeletons.map((i) => (
        <div key={i} className="animate-pulse">
          <div className="flex gap-4">
            {/* Optional Image */}
            {showImage && (
              <div className="w-32 h-24 bg-gray-200 rounded flex-shrink-0"></div>
            )}

            {/* Content */}
            <div className="flex-1 space-y-3">
              {/* Category Badge */}
              <div className="h-5 w-24 bg-gray-200 rounded-full"></div>

              {/* Title */}
              <div className="space-y-2">
                <div className="h-5 bg-gray-200 rounded w-full"></div>
                <div className="h-5 bg-gray-200 rounded w-3/4"></div>
              </div>

              {/* Lead */}
              <div className="space-y-2">
                <div className="h-4 bg-gray-200 rounded w-full"></div>
                <div className="h-4 bg-gray-200 rounded w-5/6"></div>
                <div className="h-4 bg-gray-200 rounded w-4/5"></div>
              </div>

              {/* Meta */}
              <div className="flex items-center gap-4">
                <div className="flex items-center gap-2">
                  <div className="w-6 h-6 bg-gray-200 rounded-full"></div>
                  <div className="h-3 w-24 bg-gray-200 rounded"></div>
                </div>
                <div className="h-3 w-20 bg-gray-200 rounded"></div>
              </div>
            </div>
          </div>

          {/* Divider */}
          {i < count - 1 && <div className="border-b border-gray-200 mt-6"></div>}
        </div>
      ))}
    </div>
  );
}
