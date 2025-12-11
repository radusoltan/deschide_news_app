import Link from "next/link";
import Image from "next/image";
import { fetchLatestArticles } from "@/lib/api/articles";
import { getFeaturedImage, buildImageUrl, getThumbnailByProfile } from "@/lib/api/important-articles";
import { Article } from "@/lib/types/article";
import { buildArticleUrl } from "@/lib/utils/url-builder";
import { ViewCountBadge } from "@/components/public/ViewCountBadge";
import type { Locale } from "@/lib/types";

interface LatestNewsProps {
  locale: string;
}

const LatestNews = async ({ locale }: LatestNewsProps) => {
  // Fetch latest published articles from API
  let articles: Article[] = [];

  try {
    const response = await fetchLatestArticles(locale, 10);
    articles = response.member || [];
  } catch (error) {
    console.error('Failed to fetch latest articles:', error);
    return null;
  }

  // Need at least 1 article to display
  if (articles.length === 0) {
    return null;
  }

  // First article is the featured article (full width)
  const featuredArticle = articles[0];
  const featuredImage = getFeaturedImage(featuredArticle.articleImages);
  const featuredThumbnail = featuredImage ? getThumbnailByProfile(featuredImage, 'article_hero') : null;

  // Remaining articles (up to 6) are displayed in 3-column grid
  const gridArticles = articles.slice(1, 7);

  // Helper function to get category title
  const getCategoryTitle = (category: any): string => {
    if (typeof category === 'object' && category !== null && category.title) {
      return category.title;
    }
    return '';
  };

  // Helper function to get category slug
  const getCategorySlug = (category: any): string => {
    if (typeof category === 'object' && category !== null && category.slug) {
      return category.slug;
    }
    return '';
  };

  return (
    <div className="bg-white py-6">
      <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
        <div className="flex flex-row flex-wrap">
          {/* Sidebar - 1/3 width - Most Popular */}
          <div className="flex-shrink max-w-full w-full lg:w-1/3 lg:pr-8 lg:pb-8 order-first">
            <div className="w-full bg-white">
              <div className="mb-6">
                <div className="p-4 bg-gray-100">
                  <h2 className="text-lg font-bold">Most Popular</h2>
                </div>
                <ul className="post-number">
                  {articles.slice(0, 10).map((article, index) => (
                    <li key={article.id} className="border-b border-gray-100 hover:bg-gray-50">
                      <Link
                        className="text-lg font-bold px-6 py-3 flex flex-row items-center"
                        href={buildArticleUrl(article, locale as Locale)}
                      >
                        {article.title}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </div>

          {/* Main Content - 2/3 width */}
          <div className="flex-shrink max-w-full w-full lg:w-2/3 overflow-hidden">
            {/* Section Title */}
            <div className="w-full py-3">
              <h2 className="text-brand-oxford-900 text-2xl font-heading uppercase">
                <span className="inline-block h-5 border-l-3 border-brand-tomato-500 mr-2"></span>
                Latest news
              </h2>
            </div>

            {/* Featured Article - Full Width */}
            <div className="w-full pb-5">
              <div className="relative hover-img max-h-98 overflow-hidden">
                <Link href={buildArticleUrl(featuredArticle, locale as Locale)}>
                  {featuredThumbnail ? (
                    <Image
                      className="max-w-full w-full mx-auto h-auto"
                      src={buildImageUrl(featuredThumbnail.path)}
                      alt={featuredImage?.alt || featuredArticle.title}
                      width={featuredThumbnail.width}
                      height={featuredThumbnail.height}
                      priority
                      placeholder="blur"
                      blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTkyMCIgaGVpZ2h0PSIxMDgwIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9IiNmM2Y0ZjYiLz48L3N2Zz4="
                    />
                  ) : featuredImage ? (
                    <Image
                      className="max-w-full w-full mx-auto h-auto"
                      src={buildImageUrl(featuredImage.path)}
                      alt={featuredImage.alt || featuredArticle.title}
                      width={featuredImage.width || 1920}
                      height={featuredImage.height || 1080}
                      priority
                      placeholder="blur"
                      blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTkyMCIgaGVpZ2h0PSIxMDgwIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9IiNmM2Y0ZjYiLz48L3N2Zz4="
                    />
                  ) : (
                    <div className="w-full h-96 bg-gray-200 flex items-center justify-center">
                      <span className="text-gray-400">No image</span>
                    </div>
                  )}
                </Link>
                <div className="absolute px-5 pt-8 pb-5 bottom-0 w-full bg-gradient-cover">
                  <Link href={buildArticleUrl(featuredArticle, locale as Locale)}>
                    <h2 className="text-3xl font-bold capitalize text-white mb-3">
                      {featuredArticle.title}
                    </h2>
                  </Link>
                  {featuredArticle.lead && (
                    <p className="text-gray-100 hidden sm:inline-block">
                      {featuredArticle.lead}
                    </p>
                  )}
                  {featuredArticle.category && (
                    <div className="pt-2">
                      <div className="text-gray-100">
                        <div className="inline-block h-3 border-l-2 border-brand-tomato-500 mr-2"></div>
                        {getCategoryTitle(featuredArticle.category)}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Grid of 6 Articles - CSS Grid with row-synchronized heights and full titles */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-6">
              {gridArticles.map((article) => {
                const image = getFeaturedImage(article.articleImages);
                const thumbnail = image ? getThumbnailByProfile(image, 'article_card') : null;

                return (
                  <article
                    key={article.id}
                    className="group flex flex-col h-full"
                  >
                    {/* Image container with 16:9 aspect ratio */}
                    <Link href={buildArticleUrl(article, locale as Locale)} className="block relative aspect-video overflow-hidden bg-gray-100 mb-3 rounded-sm">
                      {thumbnail ? (
                        <Image
                          className="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105"
                          src={buildImageUrl(thumbnail.path)}
                          alt={image?.alt || article.title}
                          fill
                          sizes="(max-width: 640px) 100vw, 33vw"
                          loading="lazy"
                          placeholder="blur"
                          blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2YzZjRmNiIvPjwvc3ZnPg=="
                        />
                      ) : image ? (
                        <Image
                          className="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105"
                          src={buildImageUrl(image.path)}
                          alt={image.alt || article.title}
                          fill
                          sizes="(max-width: 640px) 100vw, 33vw"
                          loading="lazy"
                          placeholder="blur"
                          blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2YzZjRmNiIvPjwvc3ZnPg=="
                        />
                      ) : (
                        <div className="absolute inset-0 bg-gray-200 flex items-center justify-center">
                          <span className="text-gray-400 text-xs">No image</span>
                        </div>
                      )}
                    </Link>

                    {/* Content wrapper - flex-grow to fill remaining space */}
                    <div className="flex flex-col flex-grow">
                      {/* Title - full visibility, no truncation */}
                      <h3 className="text-lg font-bold leading-snug mb-2 tracking-tight">
                        <Link
                          href={buildArticleUrl(article, locale as Locale)}
                          className="hover:text-brand-tomato-500 transition-colors duration-200 block"
                        >
                          {article.title}
                        </Link>
                      </h3>

                      {/* Lead - subtle, 2 lines max */}
                      <p className="hidden md:block text-gray-500 text-sm leading-relaxed mb-3 line-clamp-2 flex-grow">
                        {article.lead || '\u00A0'}
                      </p>

                      {/* Footer with category and view count - always at bottom */}
                      <div className="mt-auto pt-2">
                        <div className="flex items-center justify-between">
                          {article.category && getCategorySlug(article.category) && (
                            <Link
                              className="inline-flex items-center text-xs font-medium text-gray-500 hover:text-brand-tomato-500 transition-colors uppercase tracking-wide"
                              href={`/${locale}/category/${getCategorySlug(article.category)}`}
                            >
                              <span className="w-0.5 h-3 bg-brand-tomato-500 mr-2"></span>
                              {getCategoryTitle(article.category)}
                            </Link>
                          )}
                          {article.viewCount && (
                            <ViewCountBadge views={article.viewCount} />
                          )}
                        </div>
                      </div>
                    </div>
                  </article>
                );
              })}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default LatestNews;
