import type { Article } from "@/lib/types/article";
/**
 * Trending Articles Section
 * Displays top trending articles on the homepage
 * Public-facing component with no authentication required
 */

import Link from 'next/link';
import { getTrendingArticles } from '@/lib/api/statistics';
import { buildLocalizedUrl, buildArticleUrl } from '@/lib/utils/url-builder';
import type { Locale } from '@/lib/types';

interface Props {
  locale?: Locale;
  limit?: number;
}

export async function TrendingArticles({ locale = 'ro', limit = 5 }: Props) {
  let articles;

  try {
    articles = await getTrendingArticles(limit, locale);
  } catch (error) {
    console.error('Failed to fetch trending articles:', error);
    return null; // Don't render section if API fails
  }

  if (!articles || articles.length === 0) {
    return null;
  }

  return (
    <section className="my-12">
      <div className="flex items-center gap-3 mb-6">
        <svg
          className="w-8 h-8 text-brand-tomato-500"
          fill="currentColor"
          viewBox="0 0 20 20"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            fillRule="evenodd"
            d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z"
            clipRule="evenodd"
          />
        </svg>
        <h2 className="text-3xl font-heading text-brand-oxford-900">
          Trending Now
        </h2>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {articles.map((article, index) => (
          <Link
            key={article.id}
            href={buildArticleUrl(article as Article, locale)}
            className="group"
          >
            <article className="relative border border-gray-200 rounded-lg p-6 hover:shadow-lg transition-all duration-200 hover:border-brand-tomato-500 bg-surface hover-lift">
              {/* Trending badge for #1 */}
              {index === 0 && (
                <span className="absolute -top-3 -right-3 bg-brand-tomato text-white text-xs font-heading px-3 py-1 rounded-full shadow-lg">
                  #1 Trending
                </span>
              )}

              {/* Rank indicator for top 3 */}
              {index < 3 && (
                <div className="absolute top-4 left-4">
                  <span className={`text-5xl font-heading ${
                    index === 0 ? 'text-yellow-400' :
                    index === 1 ? 'text-gray-400' :
                    'text-orange-400'
                  } opacity-20`}>
                    #{index + 1}
                  </span>
                </div>
              )}

              <div className="relative z-10">
                {/* Category */}
                {article.category && (
                  <p className="text-sm text-brand-oxford-900 font-heading tracking-wide mb-3">
                    {article.category.name}
                  </p>
                )}

                {/* Title */}
                <h3 className="font-heading text-lg mb-3 group-hover:text-brand-tomato-500 transition-colors leading-tight min-h-[3.5rem]">
                  {article.title || 'Untitled'}
                </h3>

                {/* Views count */}
                <div className="flex items-center justify-between text-sm text-secondary pt-3 border-t border-gray-100">
                  <div className="flex items-center gap-2">
                    <svg
                      className="w-5 h-5"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                      />
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                      />
                    </svg>
                    <span className="font-medium">
                      {article.views_24h.toLocaleString()} views
                    </span>
                  </div>

                  {/* Trending icon */}
                  <svg
                    className="w-5 h-5 text-brand-tomato-500"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fillRule="evenodd"
                      d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z"
                      clipRule="evenodd"
                    />
                  </svg>
                </div>

                {/* Time indicator */}
                <p className="text-xs text-gray-400 mt-2">
                  Last 24 hours
                </p>
              </div>
            </article>
          </Link>
        ))}
      </div>
    </section>
  );
}
