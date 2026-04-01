/**
 * Feature Card Component
 * Top stories and section highlights with balanced image-text ratio
 *
 * Grid span: col-span-4 (desktop), col-span-6 (tablet), full-width (mobile)
 * Image top (16:9 aspect ratio), content below
 */

import Link from 'next/link';
import Image from 'next/image';
import type { Article } from '@/lib/types/article';
import type { Locale } from '@/lib/types';
import { getFeaturedImage, getThumbnailByProfile, buildImageUrl } from '@/lib/api/important-articles';
import { buildArticleUrl } from '@/lib/utils/url-builder';
import { getSectionColor, getCategorySlugFromArticle, getCategoryTitle, formatRelativeTime, getLocalizedBadgeText, getFirstSentence } from './utils';

interface FeatureCardProps {
  article: Article;
  locale: string;
  className?: string;
  showExcerpt?: boolean;
}

export function FeatureCard({ article, locale, className = '', showExcerpt = true }: FeatureCardProps) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage ? getThumbnailByProfile(featuredImage, 'card_large') : null;
  const imageToUse = thumbnail || featuredImage;

  const articleUrl = buildArticleUrl(article, locale as Locale);
  const categorySlug = getCategorySlugFromArticle(article.category);
  const categoryTitle = getCategoryTitle(article.category);
  const sectionColor = getSectionColor(categorySlug);
  const relativeTime = article.publishedAt ? formatRelativeTime(article.publishedAt, locale) : '';
  const badgeText = getLocalizedBadgeText(article.badge, locale);
  const excerpt = article.lead || (article.content ? getFirstSentence(article.content) : '');

  return (
    <article className={`group bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] rounded-[var(--radius-card)] overflow-hidden hover-lift-sm transition-all duration-300 col-span-full @md:col-span-6 @lg:col-span-4 ${className}`}>
      <Link href={articleUrl} className="block">
        {/* Image */}
        <figure className="relative aspect-video overflow-hidden">
          {imageToUse ? (
            <Image
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              fill
              sizes="(max-width: 740px) 100vw, (max-width: 1024px) 50vw, 33vw"
              className="object-cover transition-transform duration-500 group-hover:scale-105"
              loading="lazy"
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-[var(--color-skeleton)] to-[var(--color-border)] dark:from-[var(--color-skeleton-dark)] dark:to-[var(--color-border-dark)] flex items-center justify-center">
              <span className="text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] text-sm font-sans">No image</span>
            </div>
          )}

          {/* Category color bar overlay */}
          <div
            className="absolute top-0 left-0 w-full h-1"
            style={{ backgroundColor: sectionColor }}
          />

          {/* Badge overlay */}
          {badgeText && (
            <div className="absolute top-3 left-3">
              <span className="px-2 py-1 text-xs font-semibold tracking-wider rounded-[var(--radius-sm)] bg-[var(--color-breaking)] text-white animate-breaking-pulse font-sans">
                {badgeText}
              </span>
            </div>
          )}
        </figure>

        {/* Content */}
        <div className="p-5">
          {/* Category and timestamp */}
          <div className="flex items-center justify-between mb-3">
            {categoryTitle && (
              <span
                className="text-xs font-semibold tracking-wider uppercase font-sans"
                style={{ color: sectionColor }}
              >
                {categoryTitle}
              </span>
            )}
            {relativeTime && (
              <time
                dateTime={article.publishedAt || ''}
                className="text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] text-xs font-sans"
              >
                {relativeTime}
              </time>
            )}
          </div>

          {/* Headline */}
          <h3
            className="text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] leading-[var(--leading-snug)] mb-3 group-hover:text-[var(--color-accent)] dark:group-hover:text-[var(--color-accent)] transition-colors duration-200 font-sans line-clamp-3"
            style={{ fontSize: 'var(--font-size-2xl)' }}
          >
            {article.title}
          </h3>

          {/* Excerpt */}
          {showExcerpt && excerpt && (
            <p
              className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] leading-[var(--leading-relaxed)] line-clamp-2 font-serif"
              style={{ fontSize: 'var(--font-size-base)' }}
            >
              {excerpt}
            </p>
          )}
        </div>

        {/* Focus state */}
        <div className="absolute inset-0 rounded-[var(--radius-card)] opacity-0 group-focus-within:opacity-100 ring-3 ring-[var(--color-focus)] dark:ring-[var(--color-focus-dark)] pointer-events-none transition-opacity duration-200" />
      </Link>
    </article>
  );
}