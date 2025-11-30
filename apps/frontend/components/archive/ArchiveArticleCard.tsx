'use client';

/**
 * ArchiveArticleCard Component
 * Displays archived articles with a vintage newspaper aesthetic
 * Includes archive badge and sepia-toned styling
 */

import Link from 'next/link';
import Image from 'next/image';
import {
  getFeaturedImage,
  getThumbnailByProfile,
  buildImageUrl,
} from '@/lib/api/important-articles';
import type { Locale } from '@/lib/types';

interface ArchiveArticleCardProps {
  article: any;
  locale: Locale;
  showCategory?: boolean;
  showLead?: boolean;
  className?: string;
}

function formatDate(dateString: string, locale: Locale): string {
  const date = new Date(dateString);
  const options: Intl.DateTimeFormatOptions = {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  };

  const localeMap = {
    ro: 'ro-RO',
    en: 'en-US',
    ru: 'ru-RU',
  };

  return date.toLocaleDateString(localeMap[locale] || 'ro-RO', options);
}

const translations = {
  ro: { archived: 'Arhivat' },
  en: { archived: 'Archived' },
  ru: { archived: 'В архиве' },
};

export default function ArchiveArticleCard({
  article,
  locale,
  showCategory = true,
  showLead = false,
  className = '',
}: ArchiveArticleCardProps) {
  const t = translations[locale as keyof typeof translations] || translations.ro;
  const articleUrl = `/${locale}/${article.category?.slug || 'uncategorized'}/${article.slug}`;

  // Get featured image
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, 'article_card')
    : null;
  const imageToUse = thumbnail || featuredImage;
  const imageUrl = imageToUse ? buildImageUrl(imageToUse.path) : null;

  return (
    <article
      className={`
        group relative bg-gradient-to-br from-amber-50 to-orange-50
        rounded-xl overflow-hidden
        border border-amber-200/60
        shadow-sm hover:shadow-xl
        transition-all duration-500 ease-out
        hover:-translate-y-1
        ${className}
      `}
    >
      {/* Vintage paper texture overlay */}
      <div
        className="absolute inset-0 opacity-[0.03] pointer-events-none mix-blend-multiply"
        style={{
          backgroundImage: `url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E")`,
        }}
      />

      <Link href={articleUrl} className="block relative">
        {/* Image Container */}
        {imageUrl && (
          <div className="relative w-full h-48 overflow-hidden bg-amber-100">
            {/* Sepia overlay for vintage effect */}
            <div className="absolute inset-0 bg-gradient-to-b from-amber-900/10 to-amber-900/30 z-10 mix-blend-multiply" />

            <Image
              src={imageUrl}
              alt={featuredImage?.alt || article.title}
              fill
              className="object-cover transition-all duration-700 group-hover:scale-110 filter sepia-[.15] group-hover:sepia-0"
              sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
            />

            {/* Archive Badge */}
            <div className="absolute top-3 right-3 z-20">
              <span className="
                inline-flex items-center gap-1.5
                px-3 py-1.5 rounded-full
                bg-amber-900/90 backdrop-blur-sm
                text-amber-50 text-xs font-bold uppercase tracking-wider
                shadow-lg shadow-amber-900/30
                border border-amber-700/50
              ">
                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
                {t.archived}
              </span>
            </div>

            {/* Corner fold effect */}
            <div className="absolute bottom-0 right-0 w-8 h-8 z-10">
              <div className="absolute bottom-0 right-0 w-0 h-0 border-l-[32px] border-l-transparent border-b-[32px] border-b-amber-100 group-hover:border-b-amber-200 transition-colors" />
            </div>
          </div>
        )}

        {/* Content */}
        <div className="relative p-5">
          {/* Category */}
          {showCategory && article.category && (
            <span className="
              inline-block text-xs font-bold uppercase tracking-widest
              text-amber-700 mb-3
              pb-1 border-b-2 border-amber-400/50
            ">
              {article.category.title}
            </span>
          )}

          {/* Title */}
          <h3 className="
            text-lg font-bold leading-snug
            text-amber-950 group-hover:text-amber-800
            transition-colors duration-300
            line-clamp-2 mb-3
            font-serif
          ">
            {article.title}
          </h3>

          {/* Lead */}
          {showLead && article.lead && (
            <p className="text-sm text-amber-800/70 line-clamp-2 mb-4 italic">
              {article.lead}
            </p>
          )}

          {/* Date and decorative line */}
          <div className="flex items-center gap-3">
            <div className="flex-1 h-px bg-gradient-to-r from-amber-300 to-transparent" />
            <time
              className="text-xs text-amber-600 font-medium tracking-wide"
              dateTime={article.publishedAt}
            >
              {article.publishedAt && formatDate(article.publishedAt, locale)}
            </time>
          </div>
        </div>
      </Link>
    </article>
  );
}
