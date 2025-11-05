import Link from "next/link";
import Image from "next/image";
import { fetchLatestArticles } from "@/lib/api/articles";
import { getFeaturedImage, buildImageUrl, getThumbnailByProfile } from "@/lib/api/important-articles";
import { Article } from "@/lib/types/article";
import { buildArticleUrl } from "@/lib/utils/url-builder";
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
  const featuredThumbnail = featuredImage ? getThumbnailByProfile(featuredImage, 'article_wide') : null;

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
    <div className="bg-gray-50 py-6">
      <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
        <div className="flex flex-row flex-wrap">
          {/* Sidebar - 1/3 width - Most Popular */}
          <div className="flex-shrink max-w-full w-full lg:w-1/3 lg:pr-8 lg:pt-14 lg:pb-8 order-first">
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
              <h2 className="text-gray-800 text-2xl font-bold">
                <span className="inline-block h-5 border-l-3 border-red-600 mr-2"></span>
                Latest news
              </h2>
            </div>

            <div className="flex flex-row flex-wrap -mx-3">
              {/* Featured Article - Full Width */}
              <div className="flex-shrink max-w-full w-full px-3 pb-5">
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
                          <div className="inline-block h-3 border-l-2 border-red-600 mr-2"></div>
                          {getCategoryTitle(featuredArticle.category)}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Grid of 6 Articles - 3 columns */}
              {gridArticles.map((article) => {
                const image = getFeaturedImage(article.articleImages);
                const thumbnail = image ? getThumbnailByProfile(image, 'article_card') : null;

                return (
                  <div
                    key={article.id}
                    className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100"
                  >
                    <div className="flex flex-row sm:block hover-img">
                      <Link href={buildArticleUrl(article, locale as Locale)}>
                        {thumbnail ? (
                          <Image
                            className="max-w-full w-full mx-auto"
                            src={buildImageUrl(thumbnail.path)}
                            alt={image?.alt || article.title}
                            width={thumbnail.width}
                            height={thumbnail.height}
                            loading="lazy"
                            placeholder="blur"
                            blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAwIiBoZWlnaHQ9IjYwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjNmNGY2Ii8+PC9zdmc+"
                          />
                        ) : image ? (
                          <Image
                            className="max-w-full w-full mx-auto"
                            src={buildImageUrl(image.path)}
                            alt={image.alt || article.title}
                            width={image.width || 800}
                            height={image.height || 600}
                            loading="lazy"
                            placeholder="blur"
                            blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAwIiBoZWlnaHQ9IjYwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjNmNGY2Ii8+PC9zdmc+"
                          />
                        ) : (
                          <div className="w-full h-40 bg-gray-200 flex items-center justify-center">
                            <span className="text-gray-400 text-xs">No image</span>
                          </div>
                        )}
                      </Link>
                      <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                        <h3 className="text-lg font-bold leading-tight mb-2">
                          <Link href={buildArticleUrl(article, locale as Locale)}>
                            {article.title}
                          </Link>
                        </h3>
                        {article.lead && (
                          <p className="hidden md:block text-gray-600 leading-tight mb-1">
                            {article.lead}
                          </p>
                        )}
                        {article.category && getCategorySlug(article.category) && (
                          <Link className="text-gray-500" href={`/${locale}/category/${getCategorySlug(article.category)}`}>
                            <span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>
                            {getCategoryTitle(article.category)}
                          </Link>
                        )}
                      </div>
                    </div>
                  </div>
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
