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
    <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
      <div className="flex flex-row sm:block hover-img">
        <Link href={articleUrl}>
          {imageToUse ? (
            <Image
              className="max-w-full w-full mx-auto h-auto"
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              width={imageToUse.width || 640}
              height={imageToUse.height || 427}
              loading="lazy"
              placeholder="blur"
              blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQwIiBoZWlnaHQ9IjQyNyIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjNmNGY2Ii8+PC9zdmc+"
            />
          ) : (
            <div className="w-full h-48 bg-gray-200 flex items-center justify-center">
              <span className="text-gray-400 text-sm">No image</span>
            </div>
          )}
        </Link>
        <div className="py-0 sm:py-3 pl-3 sm:pl-0">
          <h3 className="text-lg font-bold leading-tight mb-2">
            <Link href={articleUrl}>{article.title}</Link>
          </h3>
          {excerpt && (
            <p className="hidden md:block text-gray-600 leading-tight mb-1">
              {excerpt}
            </p>
          )}
          <div className="flex items-center justify-between mt-2">
            <Link
              href={categoryUrl}
              className="text-gray-500"
            >
              <span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>
              {getCategoryTitle(article.category)}
            </Link>
            {article.viewCount && (
              <ViewCountBadge views={article.viewCount} />
            )}
          </div>
          {/* Tags */}
          {article.tags && article.tags.length > 0 && (
            <div className="mt-3">
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
    </div>
  );
}
