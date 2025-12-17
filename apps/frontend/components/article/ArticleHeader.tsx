/**
 * Article Header Component
 * Displays article title, lead, author, date, and category
 */

import Link from 'next/link';
import type { Article } from '@/lib/types/article';
import type { Locale } from '@/lib/types';
import { buildAuthorUrl, buildCategoryUrl } from '@/lib/utils/url-builder';
import { SafeHtml } from '@/components/SafeHtml';

interface ArticleHeaderProps {
  article: Article;
  locale: Locale;
  className?: string;
}

/**
 * Format date for display
 */
function formatDate(dateString: string, locale: Locale = 'ro'): string {
  const date = new Date(dateString);
  const localeMap = {
    ro: 'ro-RO',
    en: 'en-US',
    ru: 'ru-RU',
  };

  return date.toLocaleDateString(localeMap[locale], {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Get category title safely
 */
function getCategoryTitle(category: any): string {
  if (typeof category === 'object' && category?.title) {
    return category.title;
  }
  return 'Uncategorized';
}

/**
 * Get category slug safely
 */
function getCategorySlug(category: any): string {
  if (typeof category === 'object' && category?.slug) {
    return category.slug;
  }
  return 'uncategorized';
}

/**
 * Calculate reading time estimate (assumes 200 words per minute)
 */
function estimateReadingTime(content: string): number {
  const text = content.replace(/<[^>]*>/g, ''); // Strip HTML
  const wordCount = text.split(/\s+/).length;
  return Math.ceil(wordCount / 200);
}

/**
 * Strip outer paragraph wrapper from lead content
 * Avoids nested <p> tags when rendering inside a <p> element
 */
function stripParagraphWrapper(html: string): string {
  if (!html) return '';
  // Remove outer <p>...</p> wrapper if present
  const trimmed = html.trim();
  if (trimmed.startsWith('<p>') && trimmed.endsWith('</p>')) {
    return trimmed.slice(3, -4);
  }
  return trimmed;
}

export default function ArticleHeader({
  article,
  locale,
  className = '',
}: ArticleHeaderProps) {
  const authors = article.authors || [];
  const readingTime = article.content ? estimateReadingTime(article.content) : 0;

  return (
    <header className={`w-full mb-8 ${className}`}>
      {/* Category Badge */}
      {article.category && (
        <div className="mb-4">
          <Link
            href={buildCategoryUrl(article.category, locale)}
            className="inline-block px-3 py-1 text-xs font-semibold uppercase tracking-wider text-brand-tomato-600 hover:text-brand-tomato-700 bg-brand-tomato-50 rounded-md transition-colors"
          >
            {getCategoryTitle(article.category)}
          </Link>
        </div>
      )}

      {/* Article Title - Premium Typography */}
      <h1 className="text-4xl md:text-5xl lg:text-[56px] font-bold leading-tight md:leading-[1.1] text-gray-900 mb-6 font-heading tracking-tight">
        {article.title}
      </h1>

      {/* Article Lead/Summary - Premium Typography */}
      {article.lead && (
        <SafeHtml
          html={stripParagraphWrapper(article.lead)}
          as="p"
          className="text-xl md:text-2xl text-gray-700 mb-8 leading-relaxed font-normal max-w-4xl"
        />
      )}

      {/* Article Meta Info - Premium Layout */}
      <div className="flex flex-wrap items-center gap-4 md:gap-6 text-sm text-gray-600 pb-6 border-b border-gray-200">
        {/* Authors */}
        {authors.length > 0 && (
          <div className="flex items-center gap-2">
            <svg
              className="w-5 h-5 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
              />
            </svg>
            <span className="text-gray-600">
              {authors.map((author: any, index: number) => (
                <span key={`author-${author.id || index}`}>
                  <Link
                    href={buildAuthorUrl(author.slug, locale)}
                    className="font-medium text-gray-900 hover:text-brand-tomato-600 transition-colors"
                  >
                    {author.fullName}
                  </Link>
                  {index < authors.length - 1 && ', '}
                </span>
              ))}
            </span>
          </div>
        )}

        {/* Publish Date */}
        {article.publishedAt && (
          <time className="flex items-center gap-2" dateTime={article.publishedAt}>
            <svg
              className="w-5 h-5 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
              />
            </svg>
            <span className="text-gray-600">{formatDate(article.publishedAt, locale)}</span>
          </time>
        )}

        {/* Reading Time */}
        {readingTime > 0 && (
          <div className="flex items-center gap-2">
            <svg
              className="w-5 h-5 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <span className="text-gray-600">{readingTime} min read</span>
          </div>
        )}
      </div>
    </header>
  );
}
