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
import type { Locale } from '@/lib/types';

interface ArticleCardProps {
  article: any;
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
                className="text-xs text-gray-500 mt-1 block"
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
              className="text-xs text-gray-500"
              dateTime={article.publishedAt}
            >
              {formatDate(article.publishedAt, locale)}
            </time>
          )}
        </Link>
      </article>
    );
  }

  // Default variant
  return (
    <article className={`group bg-white rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow ${className}`}>
      <Link href={articleUrl} className="block">
        {/* Thumbnail */}
        {imageUrl && (
          <div className="relative w-full h-48 overflow-hidden bg-gray-200">
            <Image
              src={imageUrl}
              alt={featuredImage?.alt || article.title}
              fill
              className="object-cover group-hover:scale-105 transition-transform duration-300"
              sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
            />
            {/* Archive Badge */}
            {isArchived && (
              <div className="absolute top-2 right-2 z-10">
                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-900/90 backdrop-blur-sm text-amber-50 text-xs font-bold uppercase tracking-wider shadow-lg">
                  <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                  </svg>
                  {archiveBadgeText}
                </span>
              </div>
            )}
          </div>
        )}

        {/* Content */}
        <div className="p-4">
          {/* Category */}
          {showCategory && article.category && (
            <span className="inline-block px-3 py-1 rounded bg-brand-tomato text-white text-xs font-medium uppercase tracking-wide mb-2">
              {article.category.title}
            </span>
          )}

          {/* Title */}
          <h3 className="text-lg font-heading text-brand-oxford-900 line-clamp-2 group-hover:text-brand-tomato-500 transition-colors mb-2">
            {article.title}
          </h3>

          {/* Lead */}
          {showLead && article.lead && (
            <p className="text-sm text-gray-600 line-clamp-2 mb-3">
              {article.lead}
            </p>
          )}

          {/* Date */}
          {showDate && article.publishedAt && (
            <time
              className="text-xs text-gray-500"
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
