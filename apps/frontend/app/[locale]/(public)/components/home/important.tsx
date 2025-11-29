import Link from "next/link";
import Image from "next/image";
import { fetchImportantArticles, getFeaturedImage, buildImageUrl, getThumbnailByProfile } from "@/lib/api/important-articles";
import { ImportantArticle } from "@/lib/types/article";
import { buildArticleUrl } from "@/lib/utils/url-builder";
import type { Locale } from "@/lib/types";

interface ImportantListProps {
  locale: string;
}

/**
 * Category badge component with distinctive styling
 */
const CategoryBadge = ({ title, variant = 'default' }: { title: string; variant?: 'default' | 'hero' }) => {
  const baseClasses = "inline-flex items-center font-semibold uppercase tracking-wider";
  const variantClasses = variant === 'hero'
    ? "px-4 py-1.5 text-xs bg-red-600 text-white"
    : "px-2.5 py-1 text-[10px] bg-red-600 text-white";

  return (
    <span className={`${baseClasses} ${variantClasses}`}>
      {title}
    </span>
  );
};

/**
 * Elegant placeholder for missing images
 */
const ImagePlaceholder = ({ title, variant = 'default' }: { title: string; variant?: 'hero' | 'default' }) => {
  const heightClass = variant === 'hero' ? 'h-full' : 'h-full';

  return (
    <div className={`w-full ${heightClass} bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center relative overflow-hidden`}>
      {/* Subtle grid pattern */}
      <div className="absolute inset-0 opacity-5" style={{ backgroundImage: 'linear-gradient(rgba(255,255,255,.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.1) 1px, transparent 1px)', backgroundSize: '20px 20px' }} />
      {/* News icon */}
      <div className="relative z-10 text-white/20">
        <svg className={variant === 'hero' ? 'w-24 h-24' : 'w-14 h-14'} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
        </svg>
      </div>
    </div>
  );
};

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

  // Get article_hero thumbnail for main article
  const mainThumbnail = mainImage ? getThumbnailByProfile(mainImage, 'article_hero') : null;

  // Remaining articles (up to 4) are displayed in grid (right side)
  const gridArticles = importantArticles.slice(1, 5);

  // Helper function to get category title
  const getCategoryTitle = (category: any): string => {
    if (typeof category === 'object' && category !== null && category.title) {
      return category.title;
    }
    return 'Uncategorized';
  };

  return (
    <section className="bg-white py-6">
      <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
        {/* Section header - aligned with other sections */}
        <div className="w-full py-3 mb-2">
          <h2 className="text-gray-800 text-2xl font-bold">
            <span className="inline-block h-5 border-l-3 border-red-600 mr-2"></span>
            Top Stories
          </h2>
        </div>

        {/* Main grid: Hero left (1/2), 4 cards right (1/2) */}
        <div className="flex flex-row flex-wrap -mx-3">
          {/* Left Cover - Main Story (Hero) */}
          <div className="flex-shrink max-w-full w-full lg:w-1/2 px-3 pb-3 lg:pb-0">
            <article className="group relative h-[400px] lg:h-[496px] overflow-hidden">
              <Link href={buildArticleUrl(mainArticle.article, locale as Locale)} className="block h-full">
                {mainThumbnail ? (
                  <Image
                    className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                    src={buildImageUrl(mainThumbnail.path)}
                    alt={mainImage?.alt || mainArticle.article.title || 'Article image'}
                    width={mainThumbnail.width}
                    height={mainThumbnail.height}
                    priority
                    placeholder="blur"
                    blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iIzFmMjkzNyIvPjwvc3ZnPg=="
                  />
                ) : mainImage ? (
                  <Image
                    className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                    src={buildImageUrl(mainImage.path)}
                    alt={mainImage.alt || mainArticle.article.title || 'Article image'}
                    width={mainImage.width || 1600}
                    height={mainImage.height || 900}
                    priority
                    placeholder="blur"
                    blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iIzFmMjkzNyIvPjwvc3ZnPg=="
                  />
                ) : (
                  <ImagePlaceholder title={mainArticle.article.title || ''} variant="hero" />
                )}

                {/* Gradient overlay */}
                <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent" />

                {/* Content overlay */}
                <div className="absolute inset-x-0 bottom-0 p-5 lg:p-6">
                  {/* Category badge */}
                  <div className="mb-3">
                    <CategoryBadge title={getCategoryTitle(mainArticle.article.category)} variant="hero" />
                  </div>

                  {/* Title */}
                  <h2 className="text-xl sm:text-2xl lg:text-3xl font-bold text-white mb-2 leading-tight line-clamp-3 group-hover:text-red-100 transition-colors duration-300">
                    {mainArticle.article.title}
                  </h2>

                  {/* Lead text */}
                  {mainArticle.article.lead && (
                    <p className="text-gray-200 text-sm line-clamp-2 max-w-xl">
                      {mainArticle.article.lead}
                    </p>
                  )}

                  {/* Read more indicator */}
                  <div className="mt-3 flex items-center text-white/70 text-sm font-medium group-hover:text-white transition-colors duration-300">
                    <span>Read article</span>
                    <svg className="w-4 h-4 ml-2 transform group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                  </div>
                </div>
              </Link>
            </article>
          </div>

          {/* Right Side - Grid of 4 Stories */}
          <div className="flex-shrink max-w-full w-full lg:w-1/2 px-3">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 h-full">
              {gridArticles.map((importantArticle, index) => {
                const image = getFeaturedImage(importantArticle.article.articleImages);
                const thumbnail = image ? getThumbnailByProfile(image, 'article_card') : null;

                return (
                  <article
                    key={importantArticle.id}
                    className="group relative h-48 sm:h-[242px] overflow-hidden"
                  >
                    <Link href={buildArticleUrl(importantArticle.article, locale as Locale)} className="block h-full">
                      {thumbnail ? (
                        <Image
                          className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                          src={buildImageUrl(thumbnail.path)}
                          alt={image?.alt || importantArticle.article.title || 'Article image'}
                          width={thumbnail.width}
                          height={thumbnail.height}
                          loading={index < 2 ? "eager" : "lazy"}
                          placeholder="blur"
                          blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAwIiBoZWlnaHQ9IjYwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjMWYyOTM3Ii8+PC9zdmc+"
                        />
                      ) : image ? (
                        <Image
                          className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                          src={buildImageUrl(image.path)}
                          alt={image.alt || importantArticle.article.title || 'Article image'}
                          width={image.width || 1600}
                          height={image.height || 900}
                          loading={index < 2 ? "eager" : "lazy"}
                          placeholder="blur"
                          blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iIzFmMjkzNyIvPjwvc3ZnPg=="
                        />
                      ) : (
                        <ImagePlaceholder title={importantArticle.article.title || ''} />
                      )}

                      {/* Gradient overlay */}
                      <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent" />

                      {/* Content */}
                      <div className="absolute inset-x-0 bottom-0 p-4">
                        {/* Category badge */}
                        <div className="mb-2">
                          <CategoryBadge title={getCategoryTitle(importantArticle.article.category)} />
                        </div>

                        {/* Title */}
                        <h3 className="text-sm sm:text-base font-bold text-white leading-snug line-clamp-2 group-hover:text-red-100 transition-colors duration-300">
                          {importantArticle.article.title}
                        </h3>
                      </div>
                    </Link>
                  </article>
                );
              })}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

export default ImportantList