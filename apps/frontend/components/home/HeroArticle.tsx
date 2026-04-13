// @ts-nocheck
import type { Locale } from "@/lib/types";
"use client";

/**
 * Premium HeroArticle Component
 * Full-width dramatic hero section inspired by major media outlets
 * (The New York Times, The Guardian, RePublica)
 *
 * Features:
 * - Full-bleed hero image with dramatic gradient overlay
 * - Large typography with premium font hierarchy
 * - Dynamic category badges with brand colors
 * - Smooth hover animations and micro-interactions
 * - Mobile-responsive with optimized layouts
 * - Premium editorial design with high contrast
 */

import Link from 'next/link';
import Image from 'next/image';
import { Article } from '@/lib/types/article';
import { buildArticleUrl, getCategorySlug } from '@/lib/utils/url-builder';
import { buildImageUrl, getFeaturedImage, getThumbnailByProfile } from '@/lib/api/important-articles';

interface HeroArticleProps {
  article: {
    id: number;
    title: string;
    slug: string;
    lead?: string;
    publishedAt?: string;
    category?: {
      id: number;
      title: string;
      slug: string;
    };
    articleImages?: Array<{
      id: number;
      image: {
        path: string;
        alt?: string;
        width?: number;
        height?: number;
      };
      isFeatured: boolean;
    }>;
  };
  locale: string;
}

/**
 * Format relative time (e.g., "2 hours ago")
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
    return `${diffInMinutes} min ago`;
  }

  const diffInHours = Math.floor(diffInMinutes / 60);
  if (diffInHours < 24) {
    if (locale === 'ro') return `acum ${diffInHours}h`;
    if (locale === 'ru') return `${diffInHours}ч назад`;
    return `${diffInHours}h ago`;
  }

  const diffInDays = Math.floor(diffInHours / 24);
  if (diffInDays < 7) {
    if (locale === 'ro') return `acum ${diffInDays} zile`;
    if (locale === 'ru') return `${diffInDays} дней назад`;
    return `${diffInDays} days ago`;
  }

  // Format full date for older articles
  return date.toLocaleDateString(locale === 'ro' ? 'ro-RO' : locale === 'ru' ? 'ru-RU' : 'en-US', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  });
}

export default function HeroArticle({ article, locale }: HeroArticleProps) {
  // Get featured image with hero_big profile for maximum quality
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const heroThumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, 'hero_big')
    : null;

  // Use hero thumbnail if available, fallback to original image
  const imageToUse = heroThumbnail || featuredImage;

  // Build URLs
  const articleUrl = buildArticleUrl(article as Article, locale as Locale);
  const categorySlug = article.category?.slug || 'uncategorized';
  const categoryTitle = article.category?.title || 'News';

  // CTA text based on locale
  const ctaText = locale === 'ro'
    ? 'Citește articolul complet'
    : locale === 'ru'
    ? 'Читать полную статью'
    : 'Read full story';

  return (
    <article className="relative w-full overflow-hidden bg-brand-oxford group">
      {/* Hero Image Container - 60-70vh on desktop */}
      <div className="relative h-[50vh] sm:h-[60vh] lg:h-[70vh] overflow-hidden">
        {imageToUse ? (
          <>
            <Image
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              fill
              priority
              className="object-cover transition-transform duration-[2000ms] ease-out group-hover:scale-105"
              sizes="100vw"
              quality={90}
            />

            {/* Dramatic gradient overlay - transparent to dark */}
            <div className="absolute inset-0 bg-gradient-to-t from-black/95 via-black/60 to-transparent" />
          </>
        ) : (
          // Premium fallback for missing images
          <div className="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
            <div className="absolute inset-0 opacity-10">
              <svg className="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                <pattern id="hero-pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                  <circle cx="2" cy="2" r="1" fill="currentColor" />
                </pattern>
                <rect width="100" height="100" fill="url(#hero-pattern)" />
              </svg>
            </div>
            <div className="absolute inset-0 bg-gradient-to-t from-black/95 via-black/60 to-transparent" />
          </div>
        )}

        {/* Content overlay - positioned at bottom */}
        <div className="absolute inset-0 flex items-end">
          <div className="container-deschide w-full pb-8 sm:pb-12 lg:pb-16">
            <div className="max-w-4xl">
              {/* Category Badge with dynamic color */}
              <div className="mb-4 sm:mb-6 animate-fade-in-up">
                <span
                  className="category-badge inline-flex items-center px-4 py-2 text-xs sm:text-sm font-bold uppercase tracking-wider shadow-lg"
                  data-category={categorySlug}
                >
                  {categoryTitle}
                </span>
              </div>

              {/* Hero Title - Large dramatic typography */}
              <h1 className="font-heading text-white mb-4 sm:mb-6 animate-fade-in-up stagger-1">
                <Link
                  href={articleUrl}
                  className="block transition-colors duration-300 hover:text-brand-mindaro-400 focus-brand-oxford text-crisp"
                >
                  <span className="block text-3xl sm:text-4xl md:text-5xl lg:text-6xl leading-[1.1] tracking-tight text-on-photo-strong">
                    {article.title}
                  </span>
                </Link>
              </h1>

              {/* Lead text - Serif font for editorial feel */}
              {article.lead && (
                <p className="font-serif text-white/95 text-base sm:text-lg md:text-xl lg:text-2xl leading-relaxed mb-6 sm:mb-8 max-w-3xl animate-fade-in-up stagger-2 text-on-photo">
                  {article.lead}
                </p>
              )}

              {/* CTA and Meta row */}
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 animate-fade-in-up stagger-3">
                {/* Read full story CTA */}
                <Link
                  href={articleUrl}
                  className="group/cta inline-flex items-center gap-3 px-6 py-3 bg-brand-tomato hover:bg-brand-tomato-600 text-white font-bold text-sm sm:text-base uppercase tracking-wider rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl hover:translate-x-1 focus-brand w-fit"
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
                    className="text-white/80 text-sm font-medium tracking-wide"
                    dateTime={article.publishedAt}
                  >
                    {formatRelativeTime(article.publishedAt, locale)}
                  </time>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Subtle parallax scroll indicator (desktop only) */}
        <div className="hidden lg:block absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
          <div className="w-6 h-10 rounded-full border-2 border-white/40 flex items-start justify-center p-2">
            <div className="w-1.5 h-1.5 rounded-full bg-surface/60 animate-pulse" />
          </div>
        </div>
      </div>

      {/* Optional: Subtle texture overlay for premium feel */}
      <div className="absolute inset-0 pointer-events-none mix-blend-overlay opacity-5">
        <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIj48ZmlsdGVyIGlkPSJhIiB4PSIwIiB5PSIwIj48ZmVUdXJidWxlbmNlIGJhc2VGcmVxdWVuY3k9Ii43NSIgc3RpdGNoVGlsZXM9InN0aXRjaCIgdHlwZT0iZnJhY3RhbE5vaXNlIi8+PGZlQ29sb3JNYXRyaXggdHlwZT0ic2F0dXJhdGUiIHZhbHVlcz0iMCIvPjwvZmlsdGVyPjxwYXRoIGQ9Ik0wIDBoMzAwdjMwMEgweiIgZmlsdGVyPSJ1cmwoI2EpIiBvcGFjaXR5PSIuMDUiLz48L3N2Zz4=')]" />
      </div>

      {/* Dark mode support - additional overlay */}
      <div className="absolute inset-0 dark:bg-black/20 pointer-events-none" />
    </article>
  );
}
