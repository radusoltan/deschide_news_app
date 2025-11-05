/**
 * Article Skeleton Component
 * Loading placeholder for article pages with shimmer animation
 */

export default function ArticleSkeleton() {
  return (
    <div className="animate-pulse">
      {/* Breadcrumb Skeleton */}
      <div className="mb-6 flex items-center gap-2">
        <div className="h-4 w-16 bg-gray-200 rounded"></div>
        <div className="h-4 w-4 bg-gray-200 rounded"></div>
        <div className="h-4 w-24 bg-gray-200 rounded"></div>
        <div className="h-4 w-4 bg-gray-200 rounded"></div>
        <div className="h-4 w-32 bg-gray-200 rounded"></div>
      </div>

      {/* Article Header Skeleton */}
      <div className="mb-8">
        {/* Category Badge */}
        <div className="mb-4">
          <div className="h-6 w-24 bg-gray-200 rounded-full"></div>
        </div>

        {/* Title */}
        <div className="space-y-3 mb-6">
          <div className="h-8 bg-gray-200 rounded w-full"></div>
          <div className="h-8 bg-gray-200 rounded w-5/6"></div>
        </div>

        {/* Meta Info */}
        <div className="flex items-center gap-6 mb-4">
          {/* Author */}
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-gray-200 rounded-full"></div>
            <div className="h-4 w-32 bg-gray-200 rounded"></div>
          </div>
          {/* Date */}
          <div className="h-4 w-24 bg-gray-200 rounded"></div>
          {/* Reading Time */}
          <div className="h-4 w-20 bg-gray-200 rounded"></div>
        </div>

        {/* Lead/Summary */}
        <div className="space-y-2">
          <div className="h-4 bg-gray-200 rounded w-full"></div>
          <div className="h-4 bg-gray-200 rounded w-11/12"></div>
          <div className="h-4 bg-gray-200 rounded w-10/12"></div>
        </div>
      </div>

      {/* Featured Image Skeleton */}
      <div className="mb-8">
        <div className="aspect-video bg-gray-200 rounded-lg w-full"></div>
        {/* Image Caption */}
        <div className="mt-2 h-3 bg-gray-200 rounded w-2/3"></div>
      </div>

      {/* Article Body Skeleton */}
      <div className="space-y-4 mb-8">
        {/* Paragraph 1 */}
        <div className="space-y-2">
          <div className="h-4 bg-gray-200 rounded w-full"></div>
          <div className="h-4 bg-gray-200 rounded w-full"></div>
          <div className="h-4 bg-gray-200 rounded w-11/12"></div>
          <div className="h-4 bg-gray-200 rounded w-full"></div>
        </div>

        {/* Paragraph 2 */}
        <div className="space-y-2">
          <div className="h-4 bg-gray-200 rounded w-full"></div>
          <div className="h-4 bg-gray-200 rounded w-full"></div>
          <div className="h-4 bg-gray-200 rounded w-10/12"></div>
        </div>

        {/* Paragraph 3 */}
        <div className="space-y-2">
          <div className="h-4 bg-gray-200 rounded w-full"></div>
          <div className="h-4 bg-gray-200 rounded w-full"></div>
          <div className="h-4 bg-gray-200 rounded w-11/12"></div>
          <div className="h-4 bg-gray-200 rounded w-full"></div>
          <div className="h-4 bg-gray-200 rounded w-9/12"></div>
        </div>
      </div>

      {/* Social Share Skeleton */}
      <div className="flex items-center gap-4 mb-8">
        <div className="h-4 w-20 bg-gray-200 rounded"></div>
        <div className="flex gap-2">
          <div className="w-10 h-10 bg-gray-200 rounded-full"></div>
          <div className="w-10 h-10 bg-gray-200 rounded-full"></div>
          <div className="w-10 h-10 bg-gray-200 rounded-full"></div>
          <div className="w-10 h-10 bg-gray-200 rounded-full"></div>
        </div>
      </div>

      {/* Author Bio Skeleton */}
      <div className="border-t border-gray-200 pt-6">
        <div className="flex gap-4">
          <div className="w-16 h-16 bg-gray-200 rounded-full flex-shrink-0"></div>
          <div className="flex-1">
            <div className="h-5 w-32 bg-gray-200 rounded mb-2"></div>
            <div className="space-y-2">
              <div className="h-3 bg-gray-200 rounded w-full"></div>
              <div className="h-3 bg-gray-200 rounded w-5/6"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
