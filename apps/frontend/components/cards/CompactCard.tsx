/**
 * Compact Card Component
 * Dense lists for Most Read, sidebar, related articles
 *
 * Horizontal layout: small thumbnail left (80x80 or 120x80), text right
 * Optional rank number for Most Read sections
 */

import Link from 'next/link';
import Image from 'next/image';
import type { Article } from '@/lib/types/article';
import type { Locale } from '@/lib/types';
import { getFeaturedImage, getThumbnailByProfile, buildImageUrl } from '@/lib/api/important-articles';
import { buildArticleUrl } from '@/lib/utils/url-builder';
import { getSectionColor, getCategorySlugFromArticle, formatRelativeTime } from './utils';

interface CompactCardProps {
  article: Article;
  locale: string;
  rank?: number;
  className?: string;
}

export function CompactCard({ article, locale, rank, className = '' }: CompactCardProps) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage ? getThumbnailByProfile(featuredImage, 'card_small') : null;
  const imageToUse = thumbnail || featuredImage;

  const articleUrl = buildArticleUrl(article, locale as Locale);
  const categorySlug = getCategorySlugFromArticle(article.category);
  const sectionColor = getSectionColor(categorySlug);
  const relativeTime = article.publishedAt ? formatRelativeTime(article.publishedAt, locale) : '';

  return (
    <article className={`group hover-scale-sm transition-all duration-200 ${className}`}>
      <Link href={articleUrl} className="flex gap-3 items-start">
        {/* Rank number (for Most Read) */}
        {rank && (
          <div className="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-[var(--radius-sm)] bg-[var(--color-accent)] text-white font-bold text-sm font-sans">
            {rank}
          </div>
        )}

        {/* Thumbnail */}
        {imageToUse && (
          <figure className="relative flex-shrink-0 w-20 h-16 @sm:w-24 @sm:h-18 overflow-hidden rounded-[var(--radius-sm)] bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)]">
            <Image
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              fill
              sizes="(max-width: 490px) 80px, 96px"
              className="object-cover transition-transform duration-300 group-hover:scale-110"
              loading="lazy"
            />
          </figure>
        )}

        {/* Content */}
        <div className="flex-1 min-w-0">
          {/* Headline */}
          <h4
            className="text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] leading-[var(--leading-snug)] mb-1 group-hover:text-[var(--color-accent)] dark:group-hover:text-[var(--color-accent)] transition-colors duration-200 font-sans line-clamp-3"
            style={{ fontSize: 'var(--font-size-lg)' }}
          >
            {article.title}
          </h4>

          {/* Timestamp */}
          {relativeTime && (
            <time
              dateTime={article.publishedAt || ''}
              className="text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] font-sans block"
              style={{ fontSize: 'var(--font-size-xs)' }}
            >
              {relativeTime}
            </time>
          )}

          {/* Section color indicator */}
          {!rank && categorySlug && (
            <div
              className="w-8 h-0.5 mt-2 rounded-full"
              style={{ backgroundColor: sectionColor }}
            />
          )}
        </div>
      </Link>

      {/* Focus state */}
      <div className="absolute inset-0 opacity-0 group-focus-within:opacity-100 ring-2 ring-[var(--color-focus)] dark:ring-[var(--color-focus-dark)] rounded-[var(--radius-sm)] pointer-events-none transition-opacity duration-200" />
    </article>
  );
}