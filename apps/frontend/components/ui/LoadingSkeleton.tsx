/**
 * Loading Skeleton Component
 * Animated placeholder for loading states
 */

interface Props {
  variant?: 'card' | 'table' | 'stat' | 'text';
  count?: number;
  className?: string;
}

export function LoadingSkeleton({ variant = 'card', count = 1, className = '' }: Props) {
  const skeletons = Array.from({ length: count }, (_, i) => i);

  if (variant === 'stat') {
    return (
      <>
        {skeletons.map((i) => (
          <div key={i} className={`animate-pulse p-6 rounded-lg bg-gray-100 ${className}`}>
            <div className="flex items-center justify-between mb-3">
              <div className="w-8 h-8 bg-gray-300 rounded"></div>
            </div>
            <div className="h-3 bg-gray-300 rounded w-2/3 mb-2"></div>
            <div className="h-8 bg-gray-300 rounded w-1/2"></div>
          </div>
        ))}
      </>
    );
  }

  if (variant === 'table') {
    return (
      <div className={`animate-pulse bg-white p-6 rounded-lg shadow ${className}`}>
        <div className="h-6 bg-gray-300 rounded w-1/3 mb-4"></div>
        <div className="space-y-3">
          {skeletons.map((i) => (
            <div key={i} className="flex items-center gap-4">
              <div className="w-12 h-12 bg-gray-200 rounded"></div>
              <div className="flex-1 space-y-2">
                <div className="h-4 bg-gray-200 rounded w-3/4"></div>
                <div className="h-3 bg-gray-200 rounded w-1/2"></div>
              </div>
              <div className="w-16 h-4 bg-gray-200 rounded"></div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  if (variant === 'text') {
    return (
      <div className={`animate-pulse space-y-2 ${className}`}>
        {skeletons.map((i) => (
          <div key={i} className="h-4 bg-gray-300 rounded w-full"></div>
        ))}
      </div>
    );
  }

  // Default: card variant
  return (
    <>
      {skeletons.map((i) => (
        <div key={i} className={`animate-pulse bg-white p-6 rounded-lg shadow ${className}`}>
          <div className="h-6 bg-gray-300 rounded w-1/2 mb-4"></div>
          <div className="space-y-3">
            <div className="h-4 bg-gray-200 rounded w-full"></div>
            <div className="h-4 bg-gray-200 rounded w-5/6"></div>
            <div className="h-4 bg-gray-200 rounded w-4/6"></div>
          </div>
        </div>
      ))}
    </>
  );
}
