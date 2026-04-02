/**
 * Article Page Loading State
 * Displays skeleton while article is being fetched
 */

export default function ArticleLoading() {
  return (
    <main id="content">
      <div className="bg-surface-sunken py-6">
        <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
          <div className="flex flex-row flex-wrap">
            {/* Left - Article Content Skeleton */}
            <div className="flex-shrink max-w-full w-full lg:w-2/3 overflow-hidden">
              {/* Article Title Skeleton */}
              <div className="w-full py-3 mb-3">
                <div className="animate-pulse">
                  <div className="h-10 bg-gray-300 rounded w-3/4 mb-2"></div>
                  <div className="h-10 bg-gray-300 rounded w-1/2"></div>
                </div>
              </div>

              <div className="flex flex-row flex-wrap -mx-3">
                <div className="max-w-full w-full px-4">
                  {/* Featured Image Skeleton */}
                  <div className="animate-pulse mb-6">
                    <div className="bg-gray-300 h-96 w-full rounded"></div>
                  </div>

                  {/* Lead Paragraph Skeleton */}
                  <div className="animate-pulse mb-6">
                    <div className="h-6 bg-gray-300 rounded w-full mb-2"></div>
                    <div className="h-6 bg-gray-300 rounded w-full mb-2"></div>
                    <div className="h-6 bg-gray-300 rounded w-3/4"></div>
                  </div>

                  {/* Content Skeleton */}
                  <div className="animate-pulse space-y-4 mb-8">
                    <div className="h-4 bg-gray-300 rounded w-full"></div>
                    <div className="h-4 bg-gray-300 rounded w-full"></div>
                    <div className="h-4 bg-gray-300 rounded w-5/6"></div>
                    <div className="h-4 bg-gray-300 rounded w-full"></div>
                    <div className="h-4 bg-gray-300 rounded w-4/5"></div>
                    <div className="h-4 bg-gray-300 rounded w-full"></div>
                    <div className="h-4 bg-gray-300 rounded w-full"></div>
                    <div className="h-4 bg-gray-300 rounded w-3/4"></div>
                  </div>

                  {/* Meta Info Skeleton */}
                  <div className="animate-pulse bg-gray-100 px-6 py-4 mt-12">
                    <div className="flex space-x-4">
                      <div className="h-4 bg-gray-300 rounded w-32"></div>
                      <div className="h-4 bg-gray-300 rounded w-32"></div>
                      <div className="h-4 bg-gray-300 rounded w-32"></div>
                    </div>
                  </div>

                  {/* Author Bio Skeleton */}
                  <div className="animate-pulse flex flex-wrap flex-row -mx-4 justify-center py-4 mt-8 border-t border-gray-200">
                    <div className="flex-shrink max-w-full px-4 w-1/3 sm:w-1/4 md:w-1/6">
                      <div className="w-20 h-20 rounded-full bg-gray-300"></div>
                    </div>
                    <div className="flex-shrink max-w-full px-4 w-2/3 sm:w-3/4 md:w-10/12">
                      <div className="h-6 bg-gray-300 rounded w-48 mb-2"></div>
                      <div className="h-4 bg-gray-300 rounded w-full mb-1"></div>
                      <div className="h-4 bg-gray-300 rounded w-full mb-1"></div>
                      <div className="h-4 bg-gray-300 rounded w-2/3"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Right Sidebar Skeleton */}
            <div className="flex-shrink max-w-full w-full lg:w-1/3 lg:pl-8 lg:pt-14 lg:pb-8 order-first lg:order-last">
              <div className="w-full bg-surface">
                <div className="mb-6">
                  <div className="p-4 bg-gray-100">
                    <div className="animate-pulse h-6 bg-gray-300 rounded w-32"></div>
                  </div>
                  <div className="p-4 space-y-4">
                    {[1, 2, 3].map((i) => (
                      <div key={i} className="animate-pulse flex space-x-4">
                        <div className="flex-shrink-0 w-24 h-16 bg-gray-300 rounded"></div>
                        <div className="flex-1 space-y-2">
                          <div className="h-4 bg-gray-300 rounded"></div>
                          <div className="h-4 bg-gray-300 rounded w-5/6"></div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              {/* Advertisement Skeleton */}
              <div className="text-sm py-6 sticky">
                <div className="w-full text-center">
                  <div className="animate-pulse">
                    <div className="h-4 bg-gray-300 rounded w-32 mx-auto mb-2"></div>
                    <div className="mt-2 bg-gray-200 h-64"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}
