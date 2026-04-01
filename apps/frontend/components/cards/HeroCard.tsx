/**
 * Hero Card Component
 * The dominant story of the day with maximum visual impact
 *
 * Grid span: col-span-8 row-span-2 (desktop), full-width (mobile)
 * Large image with gradient overlay and white headline on overlay
 */

import Link from 'next/link';
import Image from 'next/image';
import type { Article } from '@/lib/types/article';
import type { Locale } from '@/lib/types';
import { getFeaturedImage, getThumbnailByProfile, buildImageUrl } from '@/lib/api/important-articles';
import { buildArticleUrl } from '@/lib/utils/url-builder';
import { getSectionColor, getCategorySlugFromArticle, getCategoryTitle, formatRelativeTime, getLocalizedBadgeText } from './utils';

interface HeroCardProps {
  article: Article;
  locale: string;
  priority?: boolean;
  className?: string;
}

export function HeroCard({ article, locale, priority = true, className = '' }: HeroCardProps) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage ? getThumbnailByProfile(featuredImage, 'hero_big') : null;
  const imageToUse = thumbnail || featuredImage;

  const articleUrl = buildArticleUrl(article, locale as Locale);
  const categorySlug = getCategorySlugFromArticle(article.category);
  const categoryTitle = getCategoryTitle(article.category);
  const sectionColor = getSectionColor(categorySlug);
  const relativeTime = article.publishedAt ? formatRelativeTime(article.publishedAt, locale) : '';
  const badgeText = getLocalizedBadgeText(article.badge, locale);

  return (
    <article className={`group relative overflow-hidden bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] rounded-[var(--radius-card)] h-full min-h-[320px] lg:min-h-[400px] ${className}`}>
      <Link href={articleUrl} className="block h-full">
        {/* Image */}
        <figure className="relative w-full h-full">
          {imageToUse ? (
            <Image
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              fill
              sizes="(max-width: 740px) 100vw, (max-width: 1024px) 100vw, 66vw"
              className="object-cover transition-transform duration-700 group-hover:scale-105"
              loading={priority ? 'eager' : 'lazy'}
              fetchPriority={priority ? 'high' : 'auto'}
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-[var(--color-text-tertiary)] via-[var(--color-text-secondary)] to-[var(--color-text-primary)] dark:from-[var(--color-text-tertiary-dark)] dark:via-[var(--color-text-secondary-dark)] dark:to-[var(--color-text-primary-dark)]" />
          )}
        </figure>

        {/* Gradient overlay */}
        <div className="absolute inset-0 card-overlay-gradient" />

        {/* Content */}
        <div className="absolute inset-x-0 bottom-0 p-6 @lg:p-8">
          {/* Category badge and timestamp */}
          <div className="flex items-center gap-3 mb-4">
            {categoryTitle && (
              <span
                className="px-3 py-1 text-xs font-medium tracking-wider rounded-[var(--radius-sm)] text-white text-on-photo-strong font-sans"
                style={{ backgroundColor: sectionColor }}
              >
                {categoryTitle}
              </span>
            )}

            {badgeText && (
              <span className="px-3 py-1 text-xs font-semibold tracking-wider rounded-[var(--radius-sm)] bg-[var(--color-breaking)] text-white animate-breaking-pulse font-sans">
                {badgeText}
              </span>
            )}
          </div>

          {/* Headline */}
          <h2
            className="text-white text-[clamp(2.75rem,2rem+3.75vw,4.5rem)] text-on-photo-strong leading-[var(--leading-tight)] mb-3 group-hover:text-opacity-90 transition-all duration-300 font-sans font-semibold"
          >
            {article.title}
          </h2>

          {/* Standfirst */}
          {article.lead && (
            <p
              className="text-white/90 text-on-photo leading-[var(--leading-snug)] mb-4 line-clamp-2 max-w-2xl font-serif hidden @md:block"
              style={{ fontSize: 'var(--font-size-lg)' }}
            >
              {article.lead}
            </p>
          )}

          {/* Timestamp */}
          {article.publishedAt && (
            <time
              dateTime={article.publishedAt}
              className="text-white/80 text-on-photo font-medium tracking-wide font-sans"
              style={{ fontSize: 'var(--font-size-sm)' }}
            >
              {relativeTime}
            </time>
          )}
        </div>

        {/* Focus state */}
        <div className="absolute inset-0 rounded-[var(--radius-card)] opacity-0 group-focus-within:opacity-100 ring-3 ring-[var(--color-focus)] dark:ring-[var(--color-focus-dark)] pointer-events-none transition-opacity duration-200" />
      </Link>
    </article>
  );
}