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
    <div className="flex-shrink max-w-full w-full px-3 pb-5">
      <div className="relative hover-img max-h-98 overflow-hidden">
        {/* Thumbnail */}
        <Link href={articleUrl}>
          {imageToUse ? (
            <Image
              className="max-w-full w-full mx-auto h-auto"
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              width={imageToUse.width || 1920}
              height={imageToUse.height || 1080}
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

        {/* Gradient overlay with content */}
        <div className="absolute px-5 pt-8 pb-5 bottom-0 w-full card-overlay-gradient">
          {/* Category Badge */}
          <div className="mb-3">
            <span className="inline-block px-3 py-1.5 rounded bg-brand-tomato text-white text-xs font-medium uppercase tracking-wide shadow-lg">
              {getCategoryTitle(article.category)}
            </span>
          </div>

          {/* Title with text shadow for readability on images */}
          <Link href={articleUrl}>
            <h2 className="text-3xl font-heading text-white text-on-photo-strong mb-3">
              {article.title}
            </h2>
          </Link>

          {/* Excerpt */}
          {excerpt && (
            <p className="text-white text-on-photo hidden sm:inline-block font-body leading-relaxed">
              {excerpt}
            </p>
          )}
        </div>
      </div>
    </div>
  );
}
