/**
 * Category Hero Article Component
 * Large article display for category pages with gradient overlay
 */

import Link from 'next/link';
import Image from 'next/image';
import { Article, Category } from '@/lib/types/article';
import { buildImageUrl, getThumbnailByProfile, getFeaturedImage } from '@/lib/api/important-articles';

interface CategoryHeroArticleProps {
  article: Article;
  locale: string;
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

  // Build article URL with correct category slug
  const categorySlug = typeof article.category === 'object' ? article.category.slug : 'uncategorized';
  const articleUrl = `/${locale}/${categorySlug}/${article.slug}`;

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
        <div className="absolute px-5 pt-8 pb-5 bottom-0 w-full bg-gradient-cover">
          {/* Title */}
          <Link href={articleUrl}>
            <h2 className="text-3xl font-bold capitalize text-white mb-3">
              {article.title}
            </h2>
          </Link>

          {/* Excerpt */}
          {excerpt && (
            <p className="text-gray-100 hidden sm:inline-block">
              {excerpt}
            </p>
          )}

          {/* Category tag */}
          <div className="pt-2">
            <div className="text-gray-100">
              <div className="inline-block h-3 border-l-2 border-red-600 mr-2"></div>
              {getCategoryTitle(article.category)}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
