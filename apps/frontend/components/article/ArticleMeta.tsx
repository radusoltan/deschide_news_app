// @ts-nocheck
/**
 * Article Meta Component
 * Displays article metadata including author bio, category, and social sharing
 */

'use client';

import React from 'react';
import Link from 'next/link';
import type { Article, Category, Author } from '@/lib/types/article';
import type { Locale } from '@/lib/types';
import { buildAuthorUrl, buildCategoryUrl } from '@/lib/utils/url-builder';

interface ArticleMetaProps {
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
    month: 'short',
    day: 'numeric',
  });
}

/**
 * Get category title and slug
 */
function getCategoryData(category: Category | string | null): { title: string; slug: string } {
  if (typeof category === 'object' && category) {
    return {
      title: category.title || 'Uncategorized',
      slug: category.slug || 'uncategorized',
    };
  }
  return { title: 'Uncategorized', slug: 'uncategorized' };
}

/**
 * Social Share Component
 */
function SocialShare({
  title,
  url,
  className = '',
}: {
  title: string;
  url: string;
  className?: string;
}) {
  const encodedUrl = encodeURIComponent(url);
  const encodedTitle = encodeURIComponent(title);

  const shareLinks = {
    facebook: `https://facebook.com/sharer/sharer.php?u=${encodedUrl}`,
    twitter: `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}`,
    linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
    whatsapp: `https://wa.me/?text=${encodedTitle}%20${encodedUrl}`,
    email: `mailto:?subject=${encodedTitle}&body=${encodedUrl}`,
  };

  return (
    <div className={`flex items-center space-x-3 text-gray-700 dark:text-secondary-dark ${className}`}>
      {/* Facebook */}
      <a
        href={shareLinks.facebook}
        target="_blank"
        rel="noopener noreferrer"
        className="hover:text-red-700 transition-colors"
        title="Share on Facebook"
        aria-label="Share on Facebook"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="2rem" height="2rem" viewBox="0 0 512 512">
          <path
            fill="currentColor"
            d="M455.27,32H56.73A24.74,24.74,0,0,0,32,56.73V455.27A24.74,24.74,0,0,0,56.73,480H256V304H202.45V240H256V189c0-57.86,40.13-89.36,91.82-89.36,24.73,0,51.33,1.86,57.51,2.68v60.43H364.15c-28.12,0-33.48,13.3-33.48,32.9V240h67l-8.75,64H330.67V480h124.6A24.74,24.74,0,0,0,480,455.27V56.73A24.74,24.74,0,0,0,455.27,32Z"
          />
        </svg>
      </a>

      {/* Twitter */}
      <a
        href={shareLinks.twitter}
        target="_blank"
        rel="noopener noreferrer"
        className="hover:text-red-700 transition-colors"
        title="Share on Twitter"
        aria-label="Share on Twitter"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="2rem" height="2rem" viewBox="0 0 512 512">
          <path
            fill="currentColor"
            d="M496,109.5a201.8,201.8,0,0,1-56.55,15.3,97.51,97.51,0,0,0,43.33-53.6,197.74,197.74,0,0,1-62.56,23.5A99.14,99.14,0,0,0,348.31,64c-54.42,0-98.46,43.4-98.46,96.9a93.21,93.21,0,0,0,2.54,22.1,280.7,280.7,0,0,1-203-101.3A95.69,95.69,0,0,0,36,130.4C36,164,53.53,193.7,80,211.1A97.5,97.5,0,0,1,35.22,199v1.2c0,47,34,86.1,79,95a100.76,100.76,0,0,1-25.94,3.4,94.38,94.38,0,0,1-18.51-1.8c12.51,38.5,48.92,66.5,92.05,67.3A199.59,199.59,0,0,1,39.5,405.6,203,203,0,0,1,16,404.2,278.68,278.68,0,0,0,166.74,448c181.36,0,280.44-147.7,280.44-275.8,0-4.2-.11-8.4-.31-12.5A198.48,198.48,0,0,0,496,109.5Z"
          />
        </svg>
      </a>

      {/* LinkedIn */}
      <a
        href={shareLinks.linkedin}
        target="_blank"
        rel="noopener noreferrer"
        className="hover:text-red-700 transition-colors"
        title="Share on LinkedIn"
        aria-label="Share on LinkedIn"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="2rem" height="2rem" viewBox="0 0 512 512">
          <path
            fill="currentColor"
            d="M444.17,32H70.28C49.85,32,32,46.7,32,66.89V441.61C32,461.91,49.85,480,70.28,480H444.06C464.6,480,480,461.79,480,441.61V66.89C480.12,46.7,464.6,32,444.17,32ZM170.87,405.43H106.69V205.88h64.18ZM141,175.54h-.46c-20.54,0-33.84-15.29-33.84-34.43,0-19.49,13.65-34.42,34.65-34.42s33.85,14.82,34.31,34.42C175.65,160.25,162.35,175.54,141,175.54ZM405.43,405.43H341.25V296.32c0-26.14-9.34-44-32.56-44-17.74,0-28.24,12-32.91,23.69-1.75,4.2-2.22,9.92-2.22,15.76V405.43H209.38V205.88h64.18v27.77c9.34-13.3,23.93-32.44,57.88-32.44,42.13,0,74,27.77,74,87.64Z"
          />
        </svg>
      </a>

      {/* WhatsApp (mobile only) */}
      <a
        href={shareLinks.whatsapp}
        target="_blank"
        rel="noopener noreferrer"
        className="hover:text-red-700 transition-colors sm:hidden"
        title="Share on WhatsApp"
        aria-label="Share on WhatsApp"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="2rem" height="2rem" viewBox="0 0 512 512">
          <path
            fill="currentColor"
            d="M414.73,97.1A222.14,222.14,0,0,0,256.94,32C134,32,33.92,131.58,33.87,254a220.61,220.61,0,0,0,29.78,111L32,480l118.25-30.87a223.63,223.63,0,0,0,106.6,27h.09c122.93,0,223-99.59,223.06-222A220.18,220.18,0,0,0,414.73,97.1ZM256.94,438.66h-.08a185.75,185.75,0,0,1-94.36-25.72l-6.77-4L85.56,427.26l18.73-68.09-4.41-7A183.46,183.46,0,0,1,71.53,254c0-101.73,83.21-184.5,185.48-184.5a185,185,0,0,1,185.33,184.64C442.34,355.88,359.13,438.66,256.94,438.66Z"
          />
        </svg>
      </a>
    </div>
  );
}

export default function ArticleMeta({ article, locale, className = '' }: ArticleMetaProps) {
  const authors = article.authors || [];
  const primaryAuthor = authors[0] as never; // TODO: Fix type - should fetch Author objects
  const categoryData = getCategoryData(article.category);

  // Get current page URL for sharing - will be empty on server, updated on client
  const [pageUrl, setPageUrl] = React.useState('');

  React.useEffect(() => {
    // Set URL only on client side
    if (typeof window !== 'undefined') {
      setPageUrl(window.location.href);
    }
  }, []);

  return (
    <div className={className}>
      {/* Article Meta Info Bar */}
      <div className="relative flex flex-col sm:flex-row items-start sm:items-center justify-between overflow-hidden bg-gray-100 dark:bg-surface-elevated-dark mt-12 mb-6 px-6 py-4">
        <div className="text-sm text-primary dark:text-primary-dark mb-4 sm:mb-0">
          {/* Authors */}
          {authors.length > 0 && (
            <span className="block sm:inline-block mr-4 mb-2 sm:mb-0">
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
              by{' '}
              {authors.map((author: Author | string, index: number) => (
                <span key={`author-${author.id || index}`}>
                  <Link
                    href={buildAuthorUrl(author.slug, locale)}
                    className="font-semibold hover:text-red-600 dark:hover:text-red-400"
                  >
                    {author.fullName}
                  </Link>
                  {index < authors.length - 1 && ', '}
                </span>
              ))}
            </span>
          )}

          {/* Date */}
          {article.publishedAt && (
            <time className="block sm:inline-block mr-4 mb-2 sm:mb-0" dateTime={article.publishedAt}>
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

          {/* Category */}
          <span className="block sm:inline-block">
            <Link
              href={buildCategoryUrl(categoryData.slug, locale)}
              className="text-red-600 hover:text-red-700"
            >
              <span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>
              {categoryData.title}
            </Link>
          </span>
        </div>

        {/* Social Share Buttons - Desktop */}
        <div className="hidden lg:block">
          <SocialShare title={article.title} url={pageUrl} />
        </div>
      </div>

      {/* Social Share Buttons - Mobile */}
      <div className="lg:hidden mb-6 px-6 py-4 bg-gray-100 dark:bg-surface-elevated-dark">
        <h3 className="text-sm font-semibold text-primary dark:text-primary-dark mb-3">Share this article</h3>
        <SocialShare title={article.title} url={pageUrl} />
      </div>

      {/* Primary Author Bio */}
      {/* TODO: Fix author data - currently authors is string[] not Author[] */}
      {/* Author bio section temporarily disabled due to type mismatch */}
    </div>
  );
}
