'use client';

/**
 * HeroSecondaryStories Component
 * Sidebar panel showing secondary stories alongside the main hero
 */

import React from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { HeroSecondaryStoriesProps, HeroArticle, HeroVariant } from '../types';
import { buildArticleUrl } from '@/lib/utils/url-builder';
import { buildImageUrl, getFeaturedImage, getThumbnailByProfile } from '@/lib/api/important-articles';
import type { Locale } from '@/lib/types';

/**
 * Format relative time
 */
function formatRelativeTime(dateString: string, locale: string): string {
  const date = new Date(dateString);
  const now = new Date();
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

  if (diffInSeconds < 60) {
    return locale === 'ro' ? 'acum' : locale === 'ru' ? 'сейчас' : 'now';
  }

  const diffInMinutes = Math.floor(diffInSeconds / 60);
  if (diffInMinutes < 60) {
    if (locale === 'ro') return `acum ${diffInMinutes} min`;
    if (locale === 'ru') return `${diffInMinutes} мин назад`;
    return `${diffInMinutes}m ago`;
  }

  const diffInHours = Math.floor(diffInMinutes / 60);
  if (diffInHours < 24) {
    if (locale === 'ro') return `acum ${diffInHours}h`;
    if (locale === 'ru') return `${diffInHours}ч назад`;
    return `${diffInHours}h ago`;
  }

  const diffInDays = Math.floor(diffInHours / 24);
  if (locale === 'ro') return `acum ${diffInDays} zile`;
  if (locale === 'ru') return `${diffInDays} дней назад`;
  return `${diffInDays}d ago`;
}

/**
 * Get sidebar class based on variant
 */
function getSidebarClass(variant: HeroVariant): string {
  switch (variant) {
    case 'breaking':
      return 'hero-sidebar-breaking';
    case 'alert':
      return 'hero-sidebar-alert';
    case 'flash':
      return 'hero-sidebar-flash';
    default:
      return 'hero-sidebar-standard';
  }
}

/**
 * Get badge color based on variant
 */
function getBadgeStyle(variant: HeroVariant): string {
  switch (variant) {
    case 'breaking':
      return 'bg-red-600';
    case 'alert':
      return 'bg-amber-500';
    case 'flash':
      return 'bg-teal-500';
    default:
      return 'bg-brand-tomato';
  }
}

/**
 * Secondary Story Card Component
 */
const SecondaryStoryCard: React.FC<{
  article: HeroArticle;
  locale: string;
  variant: HeroVariant;
  index: number;
  layout: 'vertical' | 'horizontal';
}> = ({ article, locale, variant, index, layout }) => {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, 'card_small')
    : null;
  const imageToUse = thumbnail || featuredImage;
  const articleUrl = buildArticleUrl(article as any, locale as Locale);

  if (layout === 'horizontal') {
    // Horizontal card for mobile
    return (
      <Link
        href={articleUrl}
        className="group flex-shrink-0 w-[280px] snap-start"
        style={{ animationDelay: `${index * 100}ms` }}
      >
        <article className="bg-surface/5 backdrop-blur-sm rounded-lg overflow-hidden hover:bg-surface/10 transition-all duration-300">
          {/* Image */}
          <div className="relative aspect-video">
            {imageToUse ? (
              <Image
                src={buildImageUrl(imageToUse.path)}
                alt={featuredImage?.alt || article.title}
                fill
                className="object-cover"
                sizes="280px"
              />
            ) : (
              <div className="absolute inset-0 bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center">
                <span className="text-slate-500 text-sm">DESCHIDE</span>
              </div>
            )}
          </div>
          {/* Content */}
          <div className="p-3">
            <h3 className="text-white text-sm font-semibold leading-tight line-clamp-2 group-hover:text-white/80 transition-colors">
              {article.title}
            </h3>
            {article.publishedAt && (
              <time className="text-white/50 text-xs mt-1 block">
                {formatRelativeTime(article.publishedAt, locale)}
              </time>
            )}
          </div>
        </article>
      </Link>
    );
  }

  // Vertical card for desktop sidebar
  return (
    <Link
      href={articleUrl}
      className="group block animate-fade-in"
      style={{ animationDelay: `${index * 100}ms` }}
    >
      <article className="flex gap-3 p-3 rounded-lg hover:bg-surface/5 transition-all duration-300">
        {/* Thumbnail */}
        <div className="relative w-24 h-16 flex-shrink-0 rounded-md overflow-hidden">
          {imageToUse ? (
            <Image
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              fill
              className="object-cover group-hover:scale-105 transition-transform duration-500"
              sizes="96px"
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center">
              <span className="text-slate-500 text-[10px]">DN</span>
            </div>
          )}
        </div>

        {/* Content */}
        <div className="flex-1 min-w-0">
          {/* Category or Badge indicator */}
          {article.category && (
            <span className="text-[10px] font-bold uppercase tracking-wider text-white/50 mb-1 block">
              {article.category.title}
            </span>
          )}

          <h3 className="text-white text-sm font-semibold leading-tight line-clamp-2 group-hover:text-white/80 transition-colors">
            {article.title}
          </h3>

          {article.publishedAt && (
            <time className="text-white/40 text-xs mt-1 block">
              {formatRelativeTime(article.publishedAt, locale)}
            </time>
          )}
        </div>
      </article>
    </Link>
  );
};

/**
 * Section header translations
 */
const HEADER_TRANSLATIONS: Record<string, string> = {
  ro: 'De asemenea',
  en: 'Also',
  ru: 'Также',
};

export const HeroSecondaryStories: React.FC<HeroSecondaryStoriesProps> = ({
  articles,
  locale,
  variant,
  layout = 'vertical',
}) => {
  if (!articles || articles.length === 0) {
    return null;
  }

  const sidebarClass = getSidebarClass(variant);
  const headerText = HEADER_TRANSLATIONS[locale] || HEADER_TRANSLATIONS.en;

  if (layout === 'horizontal') {
    // Horizontal scrolling layout for mobile
    return (
      <div className="px-4">
        <div className="flex gap-3 overflow-x-auto snap-x snap-mandatory scrollbar-hide pb-2 -mx-4 px-4">
          {articles.slice(0, 3).map((article, index) => (
            <SecondaryStoryCard
              key={article.id}
              article={article}
              locale={locale}
              variant={variant}
              index={index}
              layout="horizontal"
            />
          ))}
        </div>
      </div>
    );
  }

  // Vertical sidebar layout for desktop
  return (
    <aside
      className={`h-full ${sidebarClass} p-4`}
      aria-label="Related stories"
    >
      {/* Section Header */}
      <div className="mb-4 pb-3 border-b border-white/10">
        <h2 className="text-white/70 text-xs font-bold uppercase tracking-widest">
          {headerText}
        </h2>
      </div>

      {/* Story List */}
      <div className="space-y-2">
        {articles.slice(0, 3).map((article, index) => (
          <SecondaryStoryCard
            key={article.id}
            article={article}
            locale={locale}
            variant={variant}
            index={index}
            layout="vertical"
          />
        ))}
      </div>
    </aside>
  );
};

export default HeroSecondaryStories;
