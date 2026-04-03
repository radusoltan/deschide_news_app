/**
 * Article Sidebar Component
 * Displays related articles, popular articles, and advertisement spaces
 */

import React from 'react';
import Link from 'next/link';
import type { Article } from '@/lib/types/article';
import type { Locale } from '@/lib/types';
import { buildArticleUrl } from '@/lib/utils/url-builder';

interface ArticleSidebarProps {
  relatedArticles?: Article[];
  popularArticles?: Article[];
  locale: Locale;
  className?: string;
}

/**
 * Related Articles Widget
 */
function RelatedArticles({ articles, locale }: { articles: Article[]; locale: Locale }) {
  if (!articles || articles.length === 0) {
    return null;
  }

  return (
    <div className="w-full bg-surface dark:bg-surface-dark mb-6">
      <div className="p-4 bg-gray-100 dark:bg-surface-elevated-dark">
        <h2 className="text-lg font-bold dark:text-primary-dark">Related Articles</h2>
      </div>
      <ul className="divide-y divide-gray-100 dark:divide-border-dark">
        {articles.map((article) => (
          <li key={article.id} className="hover:bg-surface-sunken dark:hover:bg-surface-sunken-dark transition-colors">
            <Link
              href={buildArticleUrl(article, locale)}
              className="block px-4 py-3"
            >
              <h3 className="text-sm font-semibold text-gray-800 dark:text-primary-dark hover:text-red-600 dark:hover:text-red-400 transition-colors leading-tight">
                {article.title}
              </h3>
              {article.publishedAt && (
                <time className="text-xs text-secondary dark:text-secondary-dark mt-1 block" dateTime={article.publishedAt}>
                  {new Date(article.publishedAt).toLocaleDateString()}
                </time>
              )}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}

/**
 * Most Popular Widget
 */
function PopularArticles({ articles, locale }: { articles: Article[]; locale: Locale }) {
  if (!articles || articles.length === 0) {
    return null;
  }

  return (
    <div className="w-full bg-surface dark:bg-surface-dark mb-6">
      <div className="p-4 bg-gray-100 dark:bg-surface-elevated-dark">
        <h2 className="text-lg font-bold dark:text-primary-dark">Most Popular</h2>
      </div>
      <ul className="post-number">
        {articles.map((article, index) => (
          <li
            key={article.id}
            className="border-b border-gray-100 dark:border-border-dark hover:bg-surface-sunken dark:hover:bg-surface-sunken-dark transition-colors"
          >
            <Link
              href={buildArticleUrl(article, locale)}
              className="flex items-start px-4 py-3 gap-3"
            >
              <span className="flex-shrink-0 w-8 h-8 flex items-center justify-center bg-red-600 text-white font-bold text-sm rounded">
                {index + 1}
              </span>
              <h3 className="text-sm font-semibold text-gray-800 dark:text-primary-dark hover:text-red-600 dark:hover:text-red-400 transition-colors leading-tight flex-1">
                {article.title}
              </h3>
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}

/**
 * Advertisement Widget
 */
function AdWidget({ position = 1 }: { position?: number }) {
  return (
    <div className="w-full mb-6 sticky top-4">
      <div className="text-center">
        <a className="uppercase text-secondary dark:text-secondary-dark text-xs" href="#">
          Advertisement
        </a>
        <div className="mt-2 bg-gray-200 dark:bg-surface-elevated-dark h-64 flex items-center justify-center">
          <span className="text-gray-400 dark:text-tertiary-dark">Ad Space 250x250</span>
        </div>
      </div>
    </div>
  );
}

export default function ArticleSidebar({
  relatedArticles,
  popularArticles,
  locale,
  className = '',
}: ArticleSidebarProps) {
  return (
    <div className={className}>
      {/* Related Articles */}
      {relatedArticles && relatedArticles.length > 0 && (
        <RelatedArticles articles={relatedArticles} locale={locale} />
      )}

      {/* Most Popular */}
      {popularArticles && popularArticles.length > 0 && (
        <PopularArticles articles={popularArticles} locale={locale} />
      )}

      {/* Advertisement */}
      <AdWidget position={1} />
    </div>
  );
}
