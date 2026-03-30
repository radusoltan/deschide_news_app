'use client';

/**
 * FlashHero Template
 * Quick, energetic, modern - for fast-breaking updates
 *
 * Design Philosophy: Speed and timeliness
 * - Teal/Cyan theme suggesting speed
 * - Lightning bolt icon animation
 * - Quick slide-in animations
 * - Clean, modern feel
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

export const FlashHero: React.FC<HeroTemplateProps> = ({
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
  const articleUrl = buildArticleUrl(article as any, locale as Locale);
  const ctaText = CTA_TRANSLATIONS[locale] || CTA_TRANSLATIONS.en;

  return (
    <article
      className={`relative w-full overflow-hidden group ${className}`}
      data-testid="flash-hero"
    >
      {/* Hero Container - 50-60vh (slightly smaller for flash) */}
      <div className="relative h-[40vh] sm:h-[50vh] lg:h-[60vh] overflow-hidden">
        {/* Background Image */}
        {imageToUse ? (
          <Image
            src={buildImageUrl(imageToUse.path)}
            alt={featuredImage?.alt || article.title}
            fill
            priority
            className="object-cover transition-transform duration-[2500ms] ease-out group-hover:scale-[1.03]"
            sizes="100vw"
            quality={90}
          />
        ) : (
          <div className="absolute inset-0 bg-gradient-to-br from-teal-950 via-teal-900 to-slate-950" />
        )}

        {/* Teal gradient overlay */}
        <div className="absolute inset-0 bg-gradient-to-t from-[#134e4a]/95 via-[#0d9488]/50 to-[#134e4a]/20" />

        {/* Electric teal accent line */}
        <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-teal-400 via-cyan-400 to-teal-400 opacity-80" />

        {/* Content overlay */}
        <div className="absolute inset-0 flex items-end">
          <div className="container-deschide w-full pb-6 sm:pb-8 lg:pb-12">
            <div className="max-w-4xl">
              {/* Flash Badge - Strobe effect */}
              <div className="mb-3 sm:mb-4 animate-flash-slide-in">
                <HeroBadge variant="flash" locale={locale} size="md" />
              </div>

              {/* Hero Title */}
              <h1 className="mb-3 sm:mb-4 animate-flash-slide-in" style={{ animationDelay: '0.1s' }}>
                <Link
                  href={articleUrl}
                  className="block transition-all duration-300 hover:opacity-90 focus-brand"
                >
                  <span className="block text-white font-heading text-2xl sm:text-3xl md:text-4xl lg:text-5xl leading-[1.1] tracking-tight text-crisp">
                    <span className="drop-shadow-[0_2px_6px_rgba(0,0,0,0.6)]">
                      {article.title}
                    </span>
                  </span>
                </Link>
              </h1>

              {/* Lead text */}
              {article.lead && (
                <p
                  className="font-serif text-white/90 text-base sm:text-lg md:text-xl leading-relaxed mb-4 sm:mb-6 max-w-3xl animate-flash-slide-in"
                  style={{ animationDelay: '0.2s' }}
                >
                  {article.lead}
                </p>
              )}

              {/* CTA and Meta row */}
              <div
                className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 animate-flash-slide-in"
                style={{ animationDelay: '0.3s' }}
              >
                {/* CTA Button - Teal accent */}
                <Link
                  href={articleUrl}
                  className="group/cta inline-flex items-center gap-2 px-5 py-2.5 bg-teal-400 hover:bg-teal-300 text-teal-950 font-bold text-sm uppercase tracking-wider rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl hover:translate-x-1 focus-brand w-fit"
                >
                  <span>{ctaText}</span>
                  <svg
                    className="w-4 h-4 transition-transform duration-300 group-hover/cta:translate-x-1"
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

                {/* Publication time with lightning icon */}
                {article.publishedAt && (
                  <div className="flex items-center gap-2 text-white/70 text-sm font-medium tracking-wide">
                    <svg className="w-3.5 h-3.5 text-teal-400" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
                    </svg>
                    <time dateTime={article.publishedAt}>
                      {formatRelativeTime(article.publishedAt, locale)}
                    </time>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Scroll indicator - smaller for flash */}
        <div className="hidden lg:block absolute bottom-5 left-1/2 -translate-x-1/2 animate-bounce">
          <div className="w-4 h-7 rounded-full border-2 border-white/25 flex items-start justify-center p-1">
            <div className="w-1 h-1 rounded-full bg-teal-400/70 animate-pulse" />
          </div>
        </div>
      </div>
    </article>
  );
};

export default FlashHero;
