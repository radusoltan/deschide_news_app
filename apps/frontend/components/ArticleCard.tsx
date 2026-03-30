/**
 * Reusable Article Card Component
 * Displays article with thumbnail, title, excerpt, and category
 *
 * Variants:
 * - default: Standard card with image above content
 * - compact: Small sidebar-style card with horizontal layout
 * - featured: Large hero card with overlay text on image
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

type CardVariant = 'default' | 'compact' | 'featured';

interface ArticleCardProps {
  article: Article;
  locale: string;
  thumbnailProfile?: string;
  variant?: CardVariant;
  className?: string;
  priority?: boolean;
}

function getFirstSentence(html: string): string {
  const text = html.replace(/<[^>]*>/g, '');
  const match = text.match(/^[^.!?]*[.!?]/);
  return match ? match[0].trim() : text.substring(0, 150) + '...';
}

function getCategoryTitle(category: Category | string): string {
  if (typeof category === 'object' && category?.title) {
    return category.title;
  }
  return 'Uncategorized';
}

export default function ArticleCard({
  article,
  locale,
  thumbnailProfile = 'article_card',
  variant = 'default',
  className = '',
  priority = false,
}: ArticleCardProps) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, thumbnailProfile)
    : null;
  const imageToUse = thumbnail || featuredImage;
  const excerpt = article.lead || (article.content ? getFirstSentence(article.content) : '');
  const articleUrl = buildArticleUrl(article, locale as Locale);
  const categoryUrl = buildCategoryUrl(article.category, locale as Locale);

  // Compact variant — horizontal layout for sidebars
  if (variant === 'compact') {
    return (
      <article className={`group ${className}`}>
        <Link href={articleUrl} className="flex gap-3 items-start">
          {imageToUse && (
            <div className="relative w-20 h-14 flex-shrink-0 overflow-hidden rounded-sm bg-[var(--color-surface-sunken)]">
              <Image
                className="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105"
                src={buildImageUrl(imageToUse.path)}
                alt={featuredImage?.alt || article.title}
                fill
                sizes="80px"
                loading="lazy"
              />
            </div>
          )}
          <div className="flex-1 min-w-0">
            <h3 className="text-sm font-sans text-[var(--color-text-primary)] leading-snug line-clamp-2 group-hover:text-[var(--color-accent)] transition-colors">
              {article.title}
            </h3>
            <span className="text-xs text-[var(--color-text-tertiary)] font-serif mt-1 block">
              {getCategoryTitle(article.category)}
            </span>
          </div>
        </Link>
      </article>
    );
  }

  // Featured variant — large card with text overlay on image
  if (variant === 'featured') {
    return (
      <article className={`group relative overflow-hidden rounded-sm ${className}`}>
        <Link href={articleUrl} className="block h-full">
          <div className="relative w-full h-full min-h-[280px] lg:min-h-[400px]">
            {imageToUse ? (
              <Image
                className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                src={buildImageUrl(imageToUse.path)}
                alt={featuredImage?.alt || article.title}
                fill
                sizes="(max-width: 1024px) 100vw, 50vw"
                priority={priority}
                placeholder="blur"
                blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iIzFmMjkzNyIvPjwvc3ZnPg=="
              />
            ) : (
              <div className="absolute inset-0 bg-gradient-to-br from-[var(--color-surface-dark)] via-[var(--color-surface-elevated-dark)] to-[var(--color-surface-dark)]" />
            )}
          </div>

          {/* Gradient overlay */}
          <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent" />

          {/* Content */}
          <div className="absolute inset-x-0 bottom-0 p-5 lg:p-6">
            {/* Category badge */}
            <span className="inline-flex items-center px-3 py-1 text-[10px] font-sans tracking-wider bg-[var(--color-accent)] text-white rounded shadow-lg mb-3">
              {getCategoryTitle(article.category)}
            </span>

            <h2 className="text-xl sm:text-2xl lg:text-3xl font-sans text-white text-on-photo-strong leading-tight line-clamp-3 group-hover:text-[var(--color-accent)] transition-colors duration-300 mb-2">
              {article.title}
            </h2>

            {excerpt && (
              <p className="text-white/80 text-sm line-clamp-2 max-w-xl font-serif leading-relaxed hidden sm:block">
                {excerpt}
              </p>
            )}
          </div>
        </Link>
      </article>
    );
  }

  // Default variant
  return (
    <article className={`group flex flex-col h-full ${className}`}>
      {/* Image container with 16:9 aspect ratio */}
      <Link href={articleUrl} className="block relative aspect-video overflow-hidden bg-[var(--color-surface-sunken)] mb-3 rounded-sm">
        {imageToUse ? (
          <Image
            className="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105"
            src={buildImageUrl(imageToUse.path)}
            alt={featuredImage?.alt || article.title}
            fill
            sizes="(max-width: 640px) 100vw, 33vw"
            loading={priority ? 'eager' : 'lazy'}
            placeholder="blur"
            blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2YzZjRmNiIvPjwvc3ZnPg=="
          />
        ) : (
          <div className="absolute inset-0 bg-[var(--color-surface-sunken)] flex items-center justify-center">
            <span className="text-[var(--color-text-tertiary)] text-sm">No image</span>
          </div>
        )}
      </Link>

      {/* Content wrapper */}
      <div className="flex flex-col flex-grow">
        {/* Title */}
        <h3 className="text-lg font-sans text-[var(--color-text-primary)] leading-snug mb-2 tracking-tight">
          <Link href={articleUrl} className="hover:text-[var(--color-accent)] transition-colors duration-200 block">
            {article.title}
          </Link>
        </h3>

        {/* Excerpt */}
        <p className="hidden md:block text-[var(--color-text-tertiary)] text-sm leading-relaxed mb-3 line-clamp-2 flex-grow font-serif">
          {excerpt || '\u00A0'}
        </p>

        {/* Footer with category and view count */}
        <div className="mt-auto pt-2">
          <div className="flex items-center justify-between">
            <Link
              href={categoryUrl}
              className="inline-flex items-center text-xs font-medium text-[var(--color-text-tertiary)] hover:text-[var(--color-accent)] transition-colors uppercase tracking-wide font-serif"
            >
              <span className="w-0.5 h-3 bg-[var(--color-accent)] mr-2" />
              {getCategoryTitle(article.category)}
            </Link>
            {article.viewCount > 0 && (
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
