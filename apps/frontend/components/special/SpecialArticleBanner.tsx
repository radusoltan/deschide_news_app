'use client';

import React from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { cn } from '@/lib/utils/cn';

// Badge type definitions
export type BadgeType = 'breaking' | 'alert' | 'flash';

interface Author {
  '@id': string;
  id?: number;
  firstName?: string;
  lastName?: string;
  name?: string;
}

interface Category {
  id: number;
  title: string;
  slug: string;
}

interface ArticleImage {
  id: number;
  image?: {
    path?: string;
    filename?: string;
    alt?: string;
  };
  isFeatured?: boolean;
}

export interface SpecialArticle {
  id: number;
  title: string;
  slug: string;
  lead?: string | null;
  badge: BadgeType;
  category?: Category | string;
  authors?: Author[];
  articleImages?: ArticleImage[];
  publishedAt?: string | null;
}

interface SpecialArticleBannerProps {
  article: SpecialArticle;
  locale: string;
  className?: string;
}

// Clean, minimal badge configuration inspired by BBC/Guardian
// Red = Breaking (urgency), Oxford Blue = Alert (authority), Teal = Flash (speed)
const BADGE_CONFIG: Record<BadgeType, {
  label: string;
  labelRo: string;
  labelRu: string;
  bgColor: string;
  textColor: string;
  borderColor: string;
  dotColor: string;
  hoverBg: string;
  animation?: string;
}> = {
  breaking: {
    label: 'BREAKING',
    labelRo: 'BREAKING',
    labelRu: 'СРОЧНО',
    bgColor: 'bg-red-600',
    textColor: 'text-white',
    borderColor: 'border-red-600',
    dotColor: 'bg-white',
    hoverBg: 'hover:bg-red-700',
    animation: 'animate-brand-pulse',
  },
  alert: {
    label: 'ALERT',
    labelRo: 'ALERTĂ',
    labelRu: 'ВНИМАНИЕ',
    bgColor: 'bg-slate-800',
    textColor: 'text-white',
    borderColor: 'border-slate-800',
    dotColor: 'bg-orange-500',
    hoverBg: 'hover:bg-slate-700',
  },
  flash: {
    label: 'FLASH',
    labelRo: 'FLASH',
    labelRu: 'МОЛНИЯ',
    bgColor: 'bg-amber-500',
    textColor: 'text-slate-900',
    borderColor: 'border-amber-500',
    dotColor: 'bg-slate-900',
    hoverBg: 'hover:bg-amber-400',
  },
};

// Format relative time (e.g., "2 min ago", "1 hour ago")
function formatRelativeTime(dateString: string | null | undefined, locale: string): string {
  if (!dateString) return '';

  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  const labels: Record<string, { min: string; mins: string; hour: string; hours: string; day: string; days: string; now: string }> = {
    ro: { min: 'min', mins: 'min', hour: 'oră', hours: 'ore', day: 'zi', days: 'zile', now: 'acum' },
    en: { min: 'min', mins: 'mins', hour: 'hour', hours: 'hours', day: 'day', days: 'days', now: 'now' },
    ru: { min: 'мин', mins: 'мин', hour: 'час', hours: 'часов', day: 'день', days: 'дней', now: 'сейчас' },
  };

  const l = labels[locale] || labels.en;

  if (diffMins < 1) return l.now;
  if (diffMins < 60) return `${diffMins} ${diffMins === 1 ? l.min : l.mins}`;
  if (diffHours < 24) return `${diffHours} ${diffHours === 1 ? l.hour : l.hours}`;
  return `${diffDays} ${diffDays === 1 ? l.day : l.days}`;
}

// Get featured image URL
function getFeaturedImageUrl(articleImages?: ArticleImage[]): string | null {
  if (!articleImages || articleImages.length === 0) return null;

  const featured = articleImages.find(ai => ai.isFeatured) || articleImages[0];
  if (!featured?.image?.path) return null;

  const cdnUrl = process.env.NEXT_PUBLIC_CDN_URL || 'http://127.0.0.1:8082';

  // Handle path - VichUploader stores in images/originals/ but API returns images/
  let imagePath = featured.image.path;
  if (imagePath.startsWith('images/') && !imagePath.includes('/originals/')) {
    imagePath = imagePath.replace('images/', 'images/originals/');
  }

  return `${cdnUrl}/uploads/${imagePath}`;
}

// Get category slug
function getCategorySlug(category: Category | string | undefined): string {
  if (!category) return 'article';
  if (typeof category === 'string') return 'article';
  return category.slug;
}

// Get badge label based on locale
function getBadgeLabel(config: typeof BADGE_CONFIG[BadgeType], locale: string): string {
  if (locale === 'ro') return config.labelRo;
  if (locale === 'ru') return config.labelRu;
  return config.label;
}

/**
 * SpecialArticleBanner - Clean, minimal breaking news component
 *
 * Design inspired by BBC, Guardian, and Reuters:
 * - Horizontal ticker-style layout
 * - Solid color backgrounds (no gradients)
 * - Small colored badge pill
 * - Clean typography
 * - Subtle hover effects
 */
export const SpecialArticleBanner: React.FC<SpecialArticleBannerProps> = ({
  article,
  locale,
  className,
}) => {
  const config = BADGE_CONFIG[article.badge];
  const imageUrl = getFeaturedImageUrl(article.articleImages);
  const categorySlug = getCategorySlug(article.category);
  const articleUrl = `/${locale}/${categorySlug}/${article.slug}`;

  return (
    <Link
      href={articleUrl}
      className={cn(
        'group flex items-center gap-3 md:gap-4',
        'bg-white border-l-4',
        config.borderColor,
        'rounded-r-lg shadow-sm',
        'transition-all duration-200 ease-out',
        'hover:shadow-md hover:bg-[var(--color-surface-sunken)]',
        className
      )}
    >
      {/* Badge pill */}
      <div className={cn(
        'flex items-center gap-1.5 px-3 py-1.5 ml-3 my-3',
        'rounded-full',
        config.bgColor,
        config.hoverBg,
        'transition-colors duration-200'
      )}>
        {/* Animated dot for breaking */}
        <span className={cn(
          'w-1.5 h-1.5 rounded-full',
          config.dotColor,
          config.animation
        )} />

        {/* Badge text */}
        <span className={cn(
          'font-sans font-bold text-[10px] md:text-xs tracking-wider uppercase',
          config.textColor
        )}>
          {getBadgeLabel(config, locale)}
        </span>
      </div>

      {/* Thumbnail (mobile hidden, desktop visible) */}
      {imageUrl && (
        <div className="hidden sm:block relative w-16 h-12 flex-shrink-0 rounded overflow-hidden">
          <Image
            src={imageUrl}
            alt=""
            fill
            className="object-cover transition-transform duration-300 group-hover:scale-105"
            sizes="64px"
          />
        </div>
      )}

      {/* Content */}
      <div className="flex-1 min-w-0 py-3 pr-3">
        {/* Title */}
        <h3 className={cn(
          'font-sans font-bold text-sm md:text-base',
          'text-[var(--color-text-primary)] leading-snug',
          'line-clamp-1 md:line-clamp-2',
          'group-hover:text-[var(--color-breaking)]',
          'transition-colors duration-200'
        )}>
          {article.title}
        </h3>

        {/* Meta info */}
        <div className="flex items-center gap-2 mt-1 text-xs text-[var(--color-text-secondary)]">
          {article.publishedAt && (
            <span className="font-serif">
              {formatRelativeTime(article.publishedAt, locale)}
            </span>
          )}
        </div>
      </div>

      {/* Arrow */}
      <div className="hidden md:flex items-center pr-4">
        <svg
          className="w-4 h-4 text-[var(--color-text-tertiary)] transition-all duration-200 group-hover:text-[var(--color-breaking)] group-hover:translate-x-0.5"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          strokeWidth={2}
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
        </svg>
      </div>
    </Link>
  );
};

export default SpecialArticleBanner;
