/**
 * Text-only Card Component
 * Breaking news, opinions, Smart Brevity summaries - Speed over imagery
 *
 * No image, left border in section color (4px)
 * Optional author avatar for opinion pieces
 */

import Link from 'next/link';
import Image from 'next/image';
import type { Article } from '@/lib/types/article';
import type { Locale } from '@/lib/types';
import { buildArticleUrl } from '@/lib/utils/url-builder';
import { stripHtml } from '@/lib/utils/strip-html';
import { getSectionColor, getCategorySlugFromArticle, getCategoryTitle, formatRelativeTime, getLocalizedBadgeText, getFirstSentence } from './utils';

interface TextOnlyCardProps {
  article: Article;
  locale: string;
  authorName?: string;
  authorAvatar?: string;
  className?: string;
}

export function TextOnlyCard({ article, locale, authorName, authorAvatar, className = '' }: TextOnlyCardProps) {
  const articleUrl = buildArticleUrl(article, locale as Locale);
  const categorySlug = getCategorySlugFromArticle(article.category);
  const categoryTitle = getCategoryTitle(article.category);
  const sectionColor = getSectionColor(categorySlug);
  const relativeTime = article.publishedAt ? formatRelativeTime(article.publishedAt, locale) : '';
  const badgeText = getLocalizedBadgeText(article.badge, locale);
  const excerpt = article.lead
    ? stripHtml(article.lead)
    : (article.content ? getFirstSentence(article.content, 200) : '');

  return (
    <article
      className={`group bg-transparent p-5 rounded-[var(--radius-card)] hover-lift-sm transition-all duration-300 col-span-full @lg:col-span-4 border-l-4 ${className}`}
      style={{ borderLeftColor: sectionColor }}
    >
      <Link href={articleUrl} className="block">
        {/* Header with badges and category */}
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-3">
            {badgeText && (
              <span className="px-2 py-1 text-xs font-semibold tracking-wider rounded-[var(--radius-sm)] bg-[var(--color-breaking)] text-white animate-breaking-pulse font-sans">
                {badgeText}
              </span>
            )}

            {categoryTitle && (
              <span
                className="text-xs font-semibold tracking-wider uppercase font-sans"
                style={{ color: sectionColor }}
              >
                {categoryTitle}
              </span>
            )}
          </div>

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
          className="text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] leading-[var(--leading-snug)] mb-4 group-hover:text-[var(--color-accent)] dark:group-hover:text-[var(--color-accent)] transition-colors duration-200 font-sans line-clamp-4"
          style={{ fontSize: 'var(--font-size-xl)' }}
        >
          {article.title}
        </h3>

        {/* Summary */}
        {excerpt && (
          <p
            className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] leading-[var(--leading-relaxed)] mb-4 line-clamp-2 font-serif"
            style={{ fontSize: 'var(--font-size-base)' }}
          >
            {excerpt}
          </p>
        )}

        {/* Author section (for opinion pieces) */}
        {(authorName || authorAvatar) && (
          <div className="flex items-center gap-3 mt-4 pt-4 border-t border-[var(--color-border)] dark:border-[var(--color-border-dark)]">
            {authorAvatar && (
              <div className="relative w-8 h-8 rounded-full overflow-hidden bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)]">
                <Image
                  src={authorAvatar}
                  alt={authorName || 'Author'}
                  fill
                  sizes="32px"
                  className="object-cover"
                  loading="lazy"
                />
              </div>
            )}
            {authorName && (
              <span className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] font-medium font-sans text-sm">
                {authorName}
              </span>
            )}
          </div>
        )}

        {/* Focus state */}
        <div className="absolute inset-0 rounded-[var(--radius-card)] opacity-0 group-focus-within:opacity-100 ring-3 ring-[var(--color-focus)] dark:ring-[var(--color-focus-dark)] pointer-events-none transition-opacity duration-200" />
      </Link>
    </article>
  );
}