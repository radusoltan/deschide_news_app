import Link from "next/link";
import Image from "next/image";
import { fetchImportantArticles, getFeaturedImage, buildImageUrl, getThumbnailByProfile } from "@/lib/api/important-articles";
import { ImportantArticle } from "@/lib/types/article";
import { buildArticleUrl } from "@/lib/utils/url-builder";
import type { Locale } from "@/lib/types";

interface ImportantListProps {
  locale: string;
}

const ImportantList = async ({ locale }: ImportantListProps) => {
  // Fetch important articles from API
  let importantArticles: ImportantArticle[] = [];

  try {
    const response = await fetchImportantArticles(locale);
    importantArticles = response.member || [];
  } catch (error) {
    console.error('Failed to fetch important articles:', error);
    // Return empty section if fetch fails
    return null;
  }

  // Need at least 1 article to display
  if (importantArticles.length === 0) {
    return null;
  }

  // First article is the main story (left side)
  const mainArticle = importantArticles[0];
  const mainImage = getFeaturedImage(mainArticle.article.articleImages);

  // Get article_wide thumbnail for main article
  const mainThumbnail = mainImage ? getThumbnailByProfile(mainImage, 'article_wide') : null;

  // Remaining articles (up to 4) are displayed in grid (right side)
  const gridArticles = importantArticles.slice(1, 5);

  // Helper function to get category title
  const getCategoryTitle = (category: any): string => {
    if (typeof category === 'object' && category !== null && category.title) {
      return category.title;
    }
    return 'Uncategorized';
  };

  return <div className="bg-white py-6">
    <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
      <div className="flex flex-row flex-wrap">
        {/* Left Cover - Main Story */}
        <div className="flex-shrink max-w-full w-full lg:w-1/2 pb-1 lg:pb-0 lg:pr-1">
          <div className="relative hover-img max-h-98 overflow-hidden">
            <Link href={buildArticleUrl(mainArticle.article, locale as Locale)}>
              {mainThumbnail ? (
                <Image
                  className="max-w-full w-full mx-auto h-auto"
                  src={buildImageUrl(mainThumbnail.path)}
                  alt={mainImage?.alt || mainArticle.article.title || 'Article image'}
                  width={mainThumbnail.width}
                  height={mainThumbnail.height}
                  priority
                  placeholder="blur"
                  blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2YzZjRmNiIvPjwvc3ZnPg=="
                />
              ) : mainImage ? (
                <Image
                  className="max-w-full w-full mx-auto h-auto"
                  src={buildImageUrl(mainImage.path)}
                  alt={mainImage.alt || mainArticle.article.title || 'Article image'}
                  width={mainImage.width || 1600}
                  height={mainImage.height || 900}
                  priority
                  placeholder="blur"
                  blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2YzZjRmNiIvPjwvc3ZnPg=="
                />
              ) : (
                <div className="w-full h-96 bg-gray-200 flex items-center justify-center">
                  <span className="text-gray-400">No image</span>
                </div>
              )}
            </Link>
            <div className="absolute px-5 pt-8 pb-5 bottom-0 w-full bg-gradient-cover">
              <Link href={buildArticleUrl(mainArticle.article, locale as Locale)}>
                <h2 className="text-3xl font-bold capitalize text-white mb-3">
                  {mainArticle.article.title}
                </h2>
              </Link>
              {mainArticle.article.lead && (
                <p className="text-gray-100 hidden sm:inline-block">{mainArticle.article.lead}</p>
              )}
              <div className="pt-2">
                <div className="text-gray-100">
                  <div className="inline-block h-3 border-l-2 border-red-600 mr-2"></div>
                  {getCategoryTitle(mainArticle.article.category)}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Right Side - Grid of 4 Stories */}
        <div className="flex-shrink max-w-full w-full lg:w-1/2">
          <div className="box-one flex flex-row flex-wrap">
            {gridArticles.map((importantArticle) => {
              const image = getFeaturedImage(importantArticle.article.articleImages);
              const thumbnail = image ? getThumbnailByProfile(image, 'article_wide') : null;

              return (
                <article key={importantArticle.id} className="flex-shrink max-w-full w-full sm:w-1/2">
                  <div className="relative hover-img max-h-48 overflow-hidden">
                    <Link href={buildArticleUrl(importantArticle.article, locale as Locale)}>
                      {thumbnail ? (
                        <Image
                          className="max-w-full w-full mx-auto h-auto"
                          src={buildImageUrl(thumbnail.path)}
                          alt={image?.alt || importantArticle.article.title || 'Article image'}
                          width={thumbnail.width}
                          height={thumbnail.height}
                          loading="lazy"
                          placeholder="blur"
                          blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAwIiBoZWlnaHQ9IjYwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjNmNGY2Ii8+PC9zdmc+"
                        />
                      ) : image ? (
                        <Image
                          className="max-w-full w-full mx-auto h-auto"
                          src={buildImageUrl(image.path)}
                          alt={image.alt || importantArticle.article.title || 'Article image'}
                          width={image.width || 1600}
                          height={image.height || 900}
                          loading="lazy"
                          placeholder="blur"
                          blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2YzZjRmNiIvPjwvc3ZnPg=="
                        />
                      ) : (
                        <div className="w-full h-48 bg-gray-200 flex items-center justify-center">
                          <span className="text-gray-400 text-sm">No image</span>
                        </div>
                      )}
                    </Link>
                    <div className="absolute px-4 pt-7 pb-4 bottom-0 w-full bg-gradient-cover">
                      <Link href={buildArticleUrl(importantArticle.article, locale as Locale)}>
                        <h2 className="text-lg font-bold capitalize leading-tight text-white mb-1">
                          {importantArticle.article.title}
                        </h2>
                      </Link>
                      <div className="pt-1">
                        <div className="text-gray-100">
                          <div className="inline-block h-3 border-l-2 border-red-600 mr-2"></div>
                          {getCategoryTitle(importantArticle.article.category)}
                        </div>
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
}

export default ImportantList