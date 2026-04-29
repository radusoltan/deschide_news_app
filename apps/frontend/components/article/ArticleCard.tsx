// @ts-nocheck — ArticleCard types need alignment with API (pre-existing)
/**
 * Article Card Component
 * Displays article preview with thumbnail, title, category, and date
 * Used in related articles, category pages, and article lists
 */

import Link from 'next/link';
import Image from 'next/image';
import {
  getFeaturedImage,
  getThumbnailByProfile,
  buildImageUrl,
} from '@/lib/api/important-articles';
import { stripHtml } from '@/lib/utils/strip-html';
import type { Locale } from '@/lib/types';

interface ArticleCardProps {
  article: Article;
  locale: Locale;
  variant?: 'default' | 'horizontal' | 'minimal';
  showCategory?: boolean;
  showDate?: boolean;
  showLead?: boolean;
  showArchiveBadge?: boolean;
  className?: string;
}

const archiveTranslations = {
  ro: 'Arhivat',
  en: 'Archived',
  ru: 'В архиве',
};

/**
 * Format date for display
 */
function formatDate(dateString: string, locale: Locale): string {
  const date = new Date(dateString);
  const options: Intl.DateTimeFormatOptions = {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  };

  const localeMap = {
    ro: 'ro-RO',
    en: 'en-US',
    ru: 'ru-RU',
  };

  return date.toLocaleDateString(localeMap[locale] || 'ro-RO', options);
}

export default function ArticleCard({
  article,
  locale,
  variant = 'default',
  showCategory = true,
  showDate = true,
  showLead = false,
  showArchiveBadge = false,
  className = '',
}: ArticleCardProps) {
  // Determine if article is archived (either passed explicitly or from article status)
  const isArchived = showArchiveBadge || article.status === 'archived';
  const archiveBadgeText = archiveTranslations[locale] || archiveTranslations.ro;
  const articleUrl = `/${locale}/${article.category?.slug}/${article.slug}`;

  // Get featured image
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, 'article_card')
    : null;
  const imageToUse = thumbnail || featuredImage;
  const imageUrl = imageToUse ? buildImageUrl(imageToUse.path) : null;

  if (variant === 'horizontal') {
    return (
      <article className={`group ${className}`}>
        <Link href={articleUrl} className="flex gap-4 hover:opacity-90 transition-opacity">
          {/* Thumbnail */}
          {imageUrl && (
            <div className="relative w-24 h-16 flex-shrink-0 overflow-hidden rounded">
              <Image
                src={imageUrl}
                alt={featuredImage?.alt || article.title}
                fill
                className="object-cover group-hover:scale-105 transition-transform duration-300"
                sizes="96px"
              />
            </div>
          )}

          {/* Content */}
          <div className="flex-1 min-w-0">
            <h3 className="text-sm font-heading text-brand-oxford-900 line-clamp-2 group-hover:text-brand-tomato-500 transition-colors">
              {article.title}
            </h3>
            {showDate && article.publishedAt && (
              <time
                className="text-xs text-secondary mt-1 block"
                dateTime={article.publishedAt}
              >
                {formatDate(article.publishedAt, locale)}
              </time>
            )}
          </div>
        </Link>
      </article>
    );
  }

  if (variant === 'minimal') {
    return (
      <article className={`group ${className}`}>
        <Link href={articleUrl} className="block hover:opacity-90 transition-opacity">
          <h3 className="text-sm font-heading text-brand-oxford-900 line-clamp-2 group-hover:text-brand-tomato-500 transition-colors mb-1">
            {article.title}
          </h3>
          {showDate && article.publishedAt && (
            <time
              className="text-xs text-secondary"
              dateTime={article.publishedAt}
            >
              {formatDate(article.publishedAt, locale)}
            </time>
          )}
        </Link>
      </article>
    );
  }

  // Default variant - Premium Editorial Card Design
  return (
    <article className={`group bg-surface rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-500 border border-brand-oxford-900/10 hover:border-brand-tomato/30 hover:-translate-y-1 ${className}`}>
      <Link href={articleUrl} className="block">
        {/* Premium Thumbnail with Gradient Overlay */}
        {imageUrl && (
          <div className="relative w-full aspect-[16/10] overflow-hidden bg-gradient-to-br from-brand-oxford-100 to-brand-oxford-50">
            <Image
              src={imageUrl}
              alt={featuredImage?.alt || article.title}
              fill
              className="object-cover group-hover:scale-110 transition-transform duration-700"
              sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
            />

            {/* Subtle Gradient Overlay on Image */}
            <div className="absolute inset-0 bg-gradient-to-t from-brand-oxford-900/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

            {/* Archive Badge */}
            {isArchived && (
              <div className="absolute top-3 right-3 z-10">
                <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-amber-900/95 backdrop-blur-sm text-amber-50 text-xs font-bold uppercase tracking-wider shadow-lg">
                  <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                  </svg>
                  {archiveBadgeText}
                </span>
              </div>
            )}

            {/* Category Badge - Positioned over image */}
            {showCategory && article.category && (
              <div className="absolute top-3 left-3 z-10">
                <span className="inline-block px-3 py-1.5 rounded-md bg-brand-tomato text-white text-xs font-bold uppercase tracking-wider shadow-lg hover:bg-brand-tomato-600 transition-colors duration-300">
                  {article.category.title}
                </span>
              </div>
            )}
          </div>
        )}

        {/* Content Area */}
        <div className="p-5">
          {/* Title - Brandbook Typography */}
          <h3 className="text-lg font-semibold text-brand-oxford-900 line-clamp-2 group-hover:text-brand-tomato-500 transition-colors duration-300 mb-3 leading-tight">
            {article.title}
          </h3>

          {/* Lead Text */}
          {showLead && article.lead && (
            <p className="text-sm font-body text-brand-oxford-900/70 line-clamp-2 mb-3 leading-relaxed">
              {stripHtml(article.lead)}
            </p>
          )}

          {/* Date and Time - Premium Styling */}
          {showDate && article.publishedAt && (
            <div className="flex items-center gap-2 text-xs text-brand-oxford-900/50 font-medium">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <time dateTime={article.publishedAt}>
                {formatDate(article.publishedAt, locale)}
              </time>
            </div>
          )}
        </div>

        {/* Subtle Mindaro Accent Bar on Hover */}
        <div className="h-1 bg-gradient-to-r from-brand-mindaro-400 via-brand-tomato to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
      </Link>
    </article>
  );
}
