/**
 * Reusable Article Card Component
 * Displays article with thumbnail, title, excerpt, and category
 */

import Link from 'next/link';
import Image from 'next/image';
import { Article, Category } from '@/lib/types/article';
import type { Tag } from '@/lib/types/tag';
import { buildImageUrl, getThumbnailByProfile, getFeaturedImage } from '@/lib/api/important-articles';
import { buildArticleUrl, buildCategoryUrl, getCategorySlug as getSlug } from '@/lib/utils/url-builder';
import { ViewCountBadge } from '@/components/public/ViewCountBadge';
import { TagList } from '@/components/tags';
import type { Locale } from '@/lib/types';

interface ArticleCardProps {
  article: Article;
  locale: string;
  thumbnailProfile?: string; // Default: 'article_card'
}

/**
 * Extract first sentence from HTML content
 */
function getFirstSentence(html: string): string {
  // Remove HTML tags
  const text = html.replace(/<[^>]*>/g, '');
  // Get first sentence (ends with . ! or ?)
  const match = text.match(/^[^.!?]*[.!?]/);
  return match ? match[0].trim() : text.substring(0, 150) + '...';
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

/**
 * Get category slug safely
 */
function getCategorySlug(category: Category | string): string {
  if (typeof category === 'object' && category?.slug) {
    return category.slug;
  }
  return 'uncategorized';
}

export default function ArticleCard({
  article,
  locale,
  thumbnailProfile = 'article_card',
}: ArticleCardProps) {
  // Get featured image
  const featuredImage = getFeaturedImage(article.articleImages || []);

  // Get thumbnail with specified profile
  const thumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, thumbnailProfile)
    : null;

  // Use thumbnail if available, fallback to original image
  const imageToUse = thumbnail || featuredImage;

  // Get excerpt: use lead if available, otherwise first sentence from content
  const excerpt = article.lead || (article.content ? getFirstSentence(article.content) : '');

  // Build correct URLs using utility functions
  const articleUrl = buildArticleUrl(article, locale as Locale);
  const categoryUrl = buildCategoryUrl(article.category, locale as Locale);

  return (
    <article className="group flex flex-col h-full">
      {/* Image container with 16:9 aspect ratio */}
      <Link href={articleUrl} className="block relative aspect-video overflow-hidden bg-gray-100 mb-3 rounded-sm">
        {imageToUse ? (
          <Image
            className="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105"
            src={buildImageUrl(imageToUse.path)}
            alt={featuredImage?.alt || article.title}
            fill
            sizes="(max-width: 640px) 100vw, 33vw"
            loading="lazy"
            placeholder="blur"
            blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2YzZjRmNiIvPjwvc3ZnPg=="
          />
        ) : (
          <div className="absolute inset-0 bg-gray-200 flex items-center justify-center">
            <span className="text-gray-400 text-sm">No image</span>
          </div>
        )}
      </Link>

      {/* Content wrapper - flex-grow to fill remaining space */}
      <div className="flex flex-col flex-grow">
        {/* Title - full visibility, no truncation */}
        <h3 className="text-lg font-bold leading-snug mb-2 tracking-tight">
          <Link href={articleUrl} className="hover:text-brand-tomato-500 transition-colors duration-200 block">
            {article.title}
          </Link>
        </h3>

        {/* Excerpt - subtle, 2 lines max */}
        <p className="hidden md:block text-gray-500 text-sm leading-relaxed mb-3 line-clamp-2 flex-grow">
          {excerpt || '\u00A0'}
        </p>

        {/* Footer with category and view count - always at bottom */}
        <div className="mt-auto pt-2">
          <div className="flex items-center justify-between">
            <Link
              href={categoryUrl}
              className="inline-flex items-center text-xs font-medium text-gray-500 hover:text-brand-tomato-500 transition-colors uppercase tracking-wide"
            >
              <span className="w-0.5 h-3 bg-brand-tomato-500 mr-2"></span>
              {getCategoryTitle(article.category)}
            </Link>
            {article.viewCount && (
              <ViewCountBadge views={article.viewCount} />
            )}
          </div>

          {/* Tags */}
          {article.tags && article.tags.length > 0 && (
            <div className="mt-2">
              <TagList
                tags={article.tags.filter((tag): tag is Tag => typeof tag !== 'string')}
                locale={locale}
                variant="default"
                size="sm"
                maxTags={3}
                showHash={true}
              />
            </div>
          )}
        </div>
      </div>
    </article>
  );
}
