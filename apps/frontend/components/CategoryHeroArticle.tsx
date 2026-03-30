/**
 * Category Hero Article Component
 * Large article display for category pages with gradient overlay
 */

import Link from 'next/link';
import Image from 'next/image';
import { Article, Category } from '@/lib/types/article';
import { buildImageUrl, getThumbnailByProfile, getFeaturedImage } from '@/lib/api/important-articles';
import { buildArticleUrl } from '@/lib/utils/url-builder';
import type { Locale } from '@/lib/types';

interface CategoryHeroArticleProps {
  article: Article;
  locale: Locale;
}

/**
 * Extract excerpt from HTML content
 */
function getExcerpt(html: string, maxLength: number = 150): string {
  // Remove HTML tags
  const text = html.replace(/<[^>]*>/g, '');
  // Truncate if needed
  return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
}

/**
 * Get category title safely
 */
function getCategoryTitle(category: Category | string): string {
  if (typeof category === 'object' && category?.title) {
    return category.title;
  }
  return 'Uncategorized';
}

export default function CategoryHeroArticle({ article, locale }: CategoryHeroArticleProps) {
  // Get featured image
  const featuredImage = getFeaturedImage(article.articleImages || []);

  // Get thumbnail with article_hero profile (1600x600)
  const thumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, 'article_hero')
    : null;

  // Use thumbnail if available, fallback to original image
  const imageToUse = thumbnail || featuredImage;

  // Get excerpt: use lead if available, otherwise extract from content
  const excerpt = article.lead || (article.content ? getExcerpt(article.content) : '');

  // Build article URL using url-builder utility
  const articleUrl = buildArticleUrl(article, locale);

  return (
    <div className="flex-shrink max-w-full w-full px-3 pb-6">
      <div className="group relative overflow-hidden rounded-xl shadow-2xl hover:shadow-3xl transition-all duration-500">
        {/* Thumbnail with Premium Hover Effect */}
        <Link href={articleUrl} className="block relative aspect-[21/9] overflow-hidden">
          {imageToUse ? (
            <Image
              className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              width={imageToUse.width || 1920}
              height={imageToUse.height || 1080}
              priority
              placeholder="blur"
              blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTkyMCIgaGVpZ2h0PSIxMDgwIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9IiNmM2Y0ZjYiLz48L3N2Zz4="
            />
          ) : (
            <div className="w-full h-full bg-gradient-to-br from-[var(--color-surface-sunken)] to-[var(--color-surface-sunken)] flex items-center justify-center">
              <div className="text-center">
                <svg className="w-16 h-16 mx-auto text-[var(--color-text-primary)]/20 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span className="text-[var(--color-text-primary)]/40 font-medium text-sm">No image</span>
              </div>
            </div>
          )}
        </Link>

        {/* Premium Gradient Overlay - Oxford Blue to Transparent */}
        <div className="absolute inset-0 bg-gradient-to-t from-[var(--color-surface-dark)]/95 via-[var(--color-surface-dark)]/60 to-transparent pointer-events-none"></div>

        {/* Content - Positioned over image with text shadows */}
        <div className="absolute bottom-0 left-0 right-0 px-8 py-6 z-10">
          {/* Category Badge - Tomato Background, White Text */}
          <div className="mb-4 animate-fade-in">
            <span className="inline-flex items-center px-4 py-2 rounded-md bg-[var(--color-accent)] text-white text-xs font-bold uppercase tracking-widest shadow-2xl hover:bg-[var(--color-accent)] transition-colors duration-300">
              {getCategoryTitle(article.category)}
            </span>
          </div>

          {/* Title - League Spartan Bold, UPPERCASE, with Drop Shadow */}
          <Link href={articleUrl} className="block mb-3 group/title">
            <h2 className="text-3xl md:text-4xl lg:text-5xl font-sans text-white text-on-photo-strong leading-tight transition-all duration-300 group-hover/title:text-[var(--color-accent)]">
              {article.title}
            </h2>
          </Link>

          {/* Excerpt - Poppins with Drop Shadow */}
          {excerpt && (
            <p className="text-white text-on-photo hidden sm:block font-serif text-base md:text-lg leading-relaxed max-w-4xl">
              {excerpt}
            </p>
          )}

          {/* Subtle Bottom Accent Line with Mindaro */}
          <div className="mt-4 w-24 h-1 bg-gradient-to-r from-[var(--color-accent)] to-transparent opacity-80"></div>
        </div>

        {/* Subtle Mindaro Glow on Hover */}
        <div className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none">
          <div className="absolute inset-0 bg-[var(--color-accent)]/5"></div>
        </div>
      </div>
    </div>
  );
}
