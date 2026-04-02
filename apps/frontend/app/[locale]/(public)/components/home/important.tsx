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
  const baseClasses = "inline-flex items-center font-heading tracking-wider rounded shadow-lg";
  const variantClasses = variant === 'hero'
    ? "px-4 py-1.5 text-xs bg-brand-tomato text-white"
    : "px-2.5 py-1 text-[10px] bg-brand-tomato text-white";

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
    <section className="bg-surface py-6">
      <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
        {/* Main grid: 4 columns - Hero spans 2 cols + 2 rows, 4 secondary cards fill the rest */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
          {/* Left Cover - Main Story (Hero) - spans 2 cols and 2 rows on desktop */}
          <article className="group relative overflow-hidden rounded-sm sm:col-span-2 lg:col-span-2 lg:row-span-2">
            <Link href={buildArticleUrl(mainArticle.article, locale as Locale)} className="block h-full">
              <div className="relative w-full h-full min-h-[300px] lg:min-h-0">
                {mainThumbnail ? (
                  <Image
                    className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                    src={buildImageUrl(mainThumbnail.path)}
                    alt={mainImage?.alt || mainArticle.article.title || 'Article image'}
                    fill
                    sizes="(max-width: 1024px) 100vw, 50vw"
                    priority
                    placeholder="blur"
                    blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iIzFmMjkzNyIvPjwvc3ZnPg=="
                  />
                ) : mainImage ? (
                  <Image
                    className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                    src={buildImageUrl(mainImage.path)}
                    alt={mainImage.alt || mainArticle.article.title || 'Article image'}
                    fill
                    sizes="(max-width: 1024px) 100vw, 50vw"
                    priority
                    placeholder="blur"
                    blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iIzFmMjkzNyIvPjwvc3ZnPg=="
                  />
                ) : (
                  <ImagePlaceholder title={mainArticle.article.title || ''} variant="hero" />
                )}
              </div>

              {/* Gradient overlay */}
              <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent" />

              {/* Content overlay */}
              <div className="absolute inset-x-0 bottom-0 p-5 lg:p-6">
                {/* Category badge */}
                <div className="mb-3">
                  <CategoryBadge title={getCategoryTitle(mainArticle.article.category)} variant="hero" />
                </div>

                {/* Title with text shadow for readability */}
                <h2 className="text-xl sm:text-2xl lg:text-3xl font-heading text-white text-on-photo-strong mb-2 leading-tight line-clamp-3 group-hover:text-brand-mindaro-400 transition-colors duration-300">
                  {mainArticle.article.title}
                </h2>

                {/* Lead text */}
                {mainArticle.article.lead && (
                  <p className="text-white text-on-photo text-sm line-clamp-2 max-w-xl font-body leading-relaxed">
                    {mainArticle.article.lead}
                  </p>
                )}

                {/* Read more indicator */}
                <div className="mt-3 flex items-center text-white/70 text-sm font-medium transition-colors duration-300">
                  <span className="group-hover:text-white">Read article</span>
                  <svg className="w-4 h-4 ml-2 transform group-hover:translate-x-1 transition-transform duration-300 text-brand-mindaro-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                </div>
              </div>
            </Link>
          </article>

          {/* Right Side - 4 Stories arranged in rows that define the grid height */}
          {gridArticles.map((importantArticle, index) => {
            const image = getFeaturedImage(importantArticle.article.articleImages);
            const thumbnail = image ? getThumbnailByProfile(image, 'article_card') : null;

            return (
              <article
                key={importantArticle.id}
                className="group relative aspect-video overflow-hidden rounded-sm"
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

                    {/* Title with text shadow */}
                    <h3 className="text-sm sm:text-base font-heading text-white text-on-photo-strong leading-snug line-clamp-2 group-hover:text-brand-mindaro-400 transition-colors duration-300">
                      {importantArticle.article.title}
                    </h3>
                  </div>
                </Link>
              </article>
            );
          })}
        </div>
      </div>
    </section>
  );
}

export default ImportantList