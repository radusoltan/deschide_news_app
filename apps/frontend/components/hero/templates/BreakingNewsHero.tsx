import type { Article } from "@/lib/types/article";
'use client';

/**
 * BreakingNewsHero Template
 * Maximum drama and urgency - Full-bleed red gradient, pulsing elements
 *
 * Design Philosophy: This is BREAKING news - make it impossible to ignore
 * - Deep red gradient background with image overlay
 * - Pulsing badge with animated dot
 * - Large, bold typography with strong shadows
 * - Subtle background animation
 */

import React from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { HeroTemplateProps, CTA_TRANSLATIONS } from '../types';
import { HeroBadge } from '../partials/HeroBadge';
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
    return locale === 'ro' ? 'acum' : locale === 'ru' ? 'сейчас' : 'just now';
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

export const BreakingNewsHero: React.FC<HeroTemplateProps> = ({
  article,
  locale,
  className = '',
}) => {
  // Get featured image with hero_big profile
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const heroThumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, 'hero_big')
    : null;
  const imageToUse = heroThumbnail || featuredImage;

  // Build URLs
  const articleUrl = buildArticleUrl(article as Article, locale as Locale);
  const ctaText = CTA_TRANSLATIONS[locale] || CTA_TRANSLATIONS.en;

  return (
    <article
      className={`relative w-full overflow-hidden group ${className}`}
      data-testid="breaking-news-hero"
    >
      {/* Hero Container - 60-70vh */}
      <div className="relative h-[50vh] sm:h-[60vh] lg:h-[70vh] overflow-hidden">
        {/* Background Image with Ken Burns effect */}
        {imageToUse ? (
          <Image
            src={buildImageUrl(imageToUse.path)}
            alt={featuredImage?.alt || article.title}
            fill
            priority
            className="object-cover animate-hero-ken-burns"
            sizes="100vw"
            quality={90}
          />
        ) : (
          <div className="absolute inset-0 bg-gradient-to-br from-red-950 via-red-900 to-red-950" />
        )}

        {/* Red gradient overlay - dramatic */}
        <div className="absolute inset-0 bg-gradient-to-t from-red-950/95 via-red-900/70 to-red-950/40" />

        {/* Animated gradient background effect */}
        <div className="absolute inset-0 bg-gradient-to-br from-red-600/10 via-transparent to-red-800/20 animate-breaking-gradient" />

        {/* Subtle red edge glow */}
        <div className="absolute inset-0 shadow-[inset_0_0_100px_rgba(220,38,38,0.3)]" />

        {/* Content overlay */}
        <div className="absolute inset-0 flex items-end">
          <div className="container-deschide w-full pb-8 sm:pb-12 lg:pb-16">
            <div className="max-w-4xl">
              {/* Breaking Badge - Pulsing */}
              <div className="mb-4 sm:mb-6 animate-hero-content-enter hero-stagger-1">
                <HeroBadge variant="breaking" locale={locale} size="lg" />
              </div>

              {/* Hero Title - Maximum impact */}
              <h1 className="mb-4 sm:mb-6 animate-hero-content-enter hero-stagger-2">
                <Link
                  href={articleUrl}
                  className="block transition-all duration-300 hover:opacity-90 focus-brand"
                >
                  <span className="block text-white font-heading text-3xl sm:text-4xl md:text-5xl lg:text-6xl leading-[1.05] tracking-tight text-crisp">
                    <span className="drop-shadow-[0_2px_10px_rgba(0,0,0,0.8)]">
                      {article.title}
                    </span>
                  </span>
                </Link>
              </h1>

              {/* Lead text - High contrast */}
              {article.lead && (
                <p className="font-serif text-white/95 text-base sm:text-lg md:text-xl lg:text-2xl leading-relaxed mb-6 sm:mb-8 max-w-3xl animate-hero-content-enter hero-stagger-3 drop-shadow-[0_1px_4px_rgba(0,0,0,0.6)]">
                  {article.lead}
                </p>
              )}

              {/* CTA and Meta row */}
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 animate-hero-content-enter hero-stagger-4">
                {/* CTA Button - Red accent */}
                <Link
                  href={articleUrl}
                  className="group/cta inline-flex items-center gap-3 px-6 py-3 bg-surface text-red-700 hover:bg-red-50 font-bold text-sm sm:text-base uppercase tracking-wider rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl hover:translate-x-1 focus-brand w-fit"
                >
                  <span>{ctaText}</span>
                  <svg
                    className="w-5 h-5 transition-transform duration-300 group-hover/cta:translate-x-1"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2.5}
                      d="M13 7l5 5m0 0l-5 5m5-5H6"
                    />
                  </svg>
                </Link>

                {/* Publication time */}
                {article.publishedAt && (
                  <time
                    className="text-white/80 text-sm font-medium tracking-wide drop-shadow-sm"
                    dateTime={article.publishedAt}
                  >
                    {formatRelativeTime(article.publishedAt, locale)}
                  </time>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Scroll indicator */}
        <div className="hidden lg:block absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
          <div className="w-6 h-10 rounded-full border-2 border-white/40 flex items-start justify-center p-2">
            <div className="w-1.5 h-1.5 rounded-full bg-surface/60 animate-pulse" />
          </div>
        </div>
      </div>

      {/* Subtle noise texture overlay */}
      <div className="absolute inset-0 pointer-events-none mix-blend-overlay opacity-[0.03]">
        <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIj48ZmlsdGVyIGlkPSJhIiB4PSIwIiB5PSIwIj48ZmVUdXJidWxlbmNlIGJhc2VGcmVxdWVuY3k9Ii43NSIgc3RpdGNoVGlsZXM9InN0aXRjaCIgdHlwZT0iZnJhY3RhbE5vaXNlIi8+PGZlQ29sb3JNYXRyaXggdHlwZT0ic2F0dXJhdGUiIHZhbHVlcz0iMCIvPjwvZmlsdGVyPjxwYXRoIGQ9Ik0wIDBoMzAwdjMwMEgweiIgZmlsdGVyPSJ1cmwoI2EpIiBvcGFjaXR5PSIuMDUiLz48L3N2Zz4=')]" />
      </div>
    </article>
  );
};

export default BreakingNewsHero;
