'use client';

/**
 * AlertHero Template
 * Authoritative and urgent, but not alarming
 *
 * Design Philosophy: Important news that demands attention
 * - Navy/Oxford blue theme with amber accents
 * - Subtle glow effect on badge
 * - Large but slightly smaller than Breaking
 * - Professional, trustworthy feel
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

export const AlertHero: React.FC<HeroTemplateProps> = ({
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
      data-testid="alert-hero"
    >
      {/* Hero Container - 55-65vh */}
      <div className="relative h-[45vh] sm:h-[55vh] lg:h-[65vh] overflow-hidden">
        {/* Background Image */}
        {imageToUse ? (
          <Image
            src={buildImageUrl(imageToUse.path)}
            alt={featuredImage?.alt || article.title}
            fill
            priority
            className="object-cover transition-transform duration-[3000ms] ease-out group-hover:scale-[1.02]"
            sizes="100vw"
            quality={90}
          />
        ) : (
          <div className="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900" />
        )}

        {/* Oxford Blue gradient overlay */}
        <div className="absolute inset-0 bg-gradient-to-t from-[#112240]/95 via-[#1e3a5f]/70 to-[#112240]/30" />

        {/* Subtle amber accent glow at bottom */}
        <div className="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-amber-500 to-transparent opacity-60" />

        {/* Content overlay */}
        <div className="absolute inset-0 flex items-end">
          <div className="container-deschide w-full pb-8 sm:pb-10 lg:pb-14">
            <div className="max-w-4xl">
              {/* Alert Badge - Amber glow */}
              <div className="mb-4 sm:mb-5 animate-hero-content-enter hero-stagger-1">
                <HeroBadge variant="alert" locale={locale} size="md" />
              </div>

              {/* Hero Title */}
              <h1 className="mb-4 sm:mb-5 animate-hero-content-enter hero-stagger-2">
                <Link
                  href={articleUrl}
                  className="block transition-all duration-300 hover:opacity-90 focus-brand"
                >
                  <span className="block text-white font-heading text-2xl sm:text-3xl md:text-4xl lg:text-5xl leading-[1.1] tracking-tight text-crisp">
                    <span className="drop-shadow-[0_2px_8px_rgba(0,0,0,0.7)]">
                      {article.title}
                    </span>
                  </span>
                </Link>
              </h1>

              {/* Lead text */}
              {article.lead && (
                <p className="font-serif text-white/90 text-base sm:text-lg md:text-xl leading-relaxed mb-5 sm:mb-7 max-w-3xl animate-hero-content-enter hero-stagger-3 drop-shadow-[0_1px_3px_rgba(0,0,0,0.5)]">
                  {article.lead}
                </p>
              )}

              {/* CTA and Meta row */}
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 animate-hero-content-enter hero-stagger-4">
                {/* CTA Button - Amber accent */}
                <Link
                  href={articleUrl}
                  className="group/cta inline-flex items-center gap-3 px-5 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-900 font-bold text-sm uppercase tracking-wider rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl hover:translate-x-1 focus-brand w-fit"
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

                {/* Publication time */}
                {article.publishedAt && (
                  <time
                    className="text-white/70 text-sm font-medium tracking-wide"
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
        <div className="hidden lg:block absolute bottom-6 left-1/2 -translate-x-1/2 animate-bounce">
          <div className="w-5 h-8 rounded-full border-2 border-white/30 flex items-start justify-center p-1.5">
            <div className="w-1 h-1 rounded-full bg-amber-400/80 animate-pulse" />
          </div>
        </div>
      </div>
    </article>
  );
};

export default AlertHero;
