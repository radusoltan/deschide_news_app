/**
 * Article Header Component
 * Displays article title, lead, author, date, and category
 */

import Link from 'next/link';
import type { Article } from '@/lib/types/article';
import type { Locale } from '@/lib/types';
import { buildAuthorUrl, buildCategoryUrl } from '@/lib/utils/url-builder';

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

export default function ArticleHeader({
  article,
  locale,
  className = '',
}: ArticleHeaderProps) {
  const authors = article.authors || [];
  const readingTime = article.content ? estimateReadingTime(article.content) : 0;

  return (
    <div className={`w-full py-3 mb-6 ${className}`}>
      {/* Article Title */}
      <h1 className="text-gray-800 text-3xl md:text-4xl font-bold mb-4">
        <span className="inline-block h-5 border-l-3 border-red-600 mr-2"></span>
        {article.title}
      </h1>

      {/* Article Lead/Summary */}
      {article.lead && (
        <p className="text-xl text-gray-700 mb-4 font-medium leading-relaxed">
          {article.lead}
        </p>
      )}

      {/* Article Meta Info */}
      <div className="flex flex-wrap items-center gap-3 md:gap-6 text-sm text-gray-600 mb-4">
        {/* Authors */}
        {authors.length > 0 && (
          <div className="flex items-center">
            <svg
              className="bi bi-person mr-2 inline-block"
              width="1rem"
              height="1rem"
              viewBox="0 0 16 16"
              fill="currentColor"
            >
              <path
                fillRule="evenodd"
                d="M13 14s1 0 1-1-1-4-6-4-6 3-6 4 1 1 1 1h10zm-9.995-.944v-.002.002zM3.022 13h9.956a.274.274 0 00.014-.002l.008-.002c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664a1.05 1.05 0 00.022.004zm9.974.056v-.002.002zM8 7a2 2 0 100-4 2 2 0 000 4zm3-2a3 3 0 11-6 0 3 3 0 016 0z"
                clipRule="evenodd"
              />
            </svg>
            <span>
              by{' '}
              {authors.map((author: any, index: number) => (
                <span key={`author-${author.id || index}`}>
                  <Link
                    href={buildAuthorUrl(author.slug, locale)}
                    className="font-semibold hover:text-red-600"
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
          <time className="flex items-center" dateTime={article.publishedAt}>
            <svg
              className="bi bi-calendar mr-2 inline-block"
              width="1rem"
              height="1rem"
              viewBox="0 0 16 16"
              fill="currentColor"
            >
              <path
                fillRule="evenodd"
                d="M14 0H2a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V2a2 2 0 00-2-2zM1 3.857C1 3.384 1.448 3 2 3h12c.552 0 1 .384 1 .857v10.286c0 .473-.448.857-1 .857H2c-.552 0-1-.384-1-.857V3.857z"
                clipRule="evenodd"
              />
              <path
                fillRule="evenodd"
                d="M6.5 7a1 1 0 100-2 1 1 0 000 2zm3 0a1 1 0 100-2 1 1 0 000 2zm3 0a1 1 0 100-2 1 1 0 000 2zm-9 3a1 1 0 100-2 1 1 0 000 2zm3 0a1 1 0 100-2 1 1 0 000 2zm3 0a1 1 0 100-2 1 1 0 000 2zm3 0a1 1 0 100-2 1 1 0 000 2zm-9 3a1 1 0 100-2 1 1 0 000 2zm3 0a1 1 0 100-2 1 1 0 000 2zm3 0a1 1 0 100-2 1 1 0 000 2z"
                clipRule="evenodd"
              />
            </svg>
            {formatDate(article.publishedAt, locale)}
          </time>
        )}

        {/* Reading Time */}
        {readingTime > 0 && (
          <div className="flex items-center">
            <svg
              className="bi bi-clock mr-2 inline-block"
              width="1rem"
              height="1rem"
              viewBox="0 0 16 16"
              fill="currentColor"
            >
              <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z" />
              <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z" />
            </svg>
            <span>{readingTime} min read</span>
          </div>
        )}

        {/* Category */}
        {article.category && (
          <Link
            href={buildCategoryUrl(article.category, locale)}
            className="text-red-600 hover:text-red-700 font-semibold"
          >
            <span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>
            {getCategoryTitle(article.category)}
          </Link>
        )}
      </div>
    </div>
  );
}
