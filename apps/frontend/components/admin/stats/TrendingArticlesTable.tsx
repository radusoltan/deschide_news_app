/**
 * Trending Articles Table Component
 * Displays top trending articles in admin dashboard
 * Server component - fetches data on server side
 */

import Link from 'next/link';
import { getTrendingArticles } from '@/lib/api/statistics';
import { cookies } from 'next/headers';

interface Props {
  limit?: number;
  locale?: string;
}

export async function TrendingArticlesTable({ limit = 10, locale = 'ro' }: Props) {
  const cookieStore = await cookies();

  let articles;
  try {
    articles = await getTrendingArticles(limit, locale);
  } catch (error) {
    console.error('Failed to fetch trending articles:', error);
    return (
      <div className="bg-surface p-6 rounded-lg shadow">
        <p className="text-red-600">Failed to load trending articles</p>
      </div>
    );
  }

  if (!articles || articles.length === 0) {
    return (
      <div className="bg-surface p-6 rounded-lg shadow">
        <h2 className="text-xl font-semibold mb-4 flex items-center gap-2">
          <svg className="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clipRule="evenodd" />
          </svg>
          Trending Articles (Last 24h)
        </h2>
        <p className="text-secondary">No trending articles found</p>
      </div>
    );
  }

  return (
    <div className="bg-surface p-6 rounded-lg shadow">
      <h2 className="text-xl font-semibold mb-4 flex items-center gap-2">
        <svg className="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
          <path fillRule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clipRule="evenodd" />
        </svg>
        Trending Articles (Last 24h)
      </h2>

      <div className="overflow-x-auto">
        <table className="min-w-full">
          <thead className="bg-surface-sunken">
            <tr className="border-b border-gray-200">
              <th className="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                Rank
              </th>
              <th className="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                Article
              </th>
              <th className="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                Category
              </th>
              <th className="text-right py-3 px-4 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                Views (24h)
              </th>
              <th className="text-center py-3 px-4 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                Trend
              </th>
            </tr>
          </thead>
          <tbody className="bg-surface divide-y divide-gray-200">
            {articles.map((article, index) => (
              <tr key={article.id} className="hover:bg-surface-sunken transition-colors">
                <td className="py-4 px-4">
                  <span className={`font-bold text-2xl ${
                    index === 0 ? 'text-yellow-500' :
                    index === 1 ? 'text-gray-400' :
                    index === 2 ? 'text-orange-400' :
                    'text-primary-dark'
                  }`}>
                    #{index + 1}
                  </span>
                </td>
                <td className="py-4 px-4">
                  <Link
                    href={`/${locale}/admin/articles/${article.id}`}
                    className="text-blue-600 hover:text-blue-800 hover:underline font-medium inline-flex items-center gap-2 group"
                  >
                    {article.title || 'Untitled'}
                    <svg className="w-4 h-4 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                  </Link>
                </td>
                <td className="py-4 px-4">
                  {article.category ? (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      {article.category.name}
                    </span>
                  ) : (
                    <span className="text-gray-400 text-sm">No category</span>
                  )}
                </td>
                <td className="py-4 px-4 text-right">
                  <span className="font-semibold text-lg text-primary">
                    {article.views_24h.toLocaleString()}
                  </span>
                </td>
                <td className="py-4 px-4 text-center">
                  <span className="text-2xl">
                    {index < 3 ? '🔥' : '📈'}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="mt-4 pt-4 border-t border-gray-200 flex items-center justify-between">
        <p className="text-sm text-secondary">
          Auto-refreshes every 5 minutes
        </p>
        <div className="flex items-center gap-2 text-xs text-gray-400">
          <svg className="w-4 h-4 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clipRule="evenodd" />
          </svg>
          <span>Live data</span>
        </div>
      </div>
    </div>
  );
}
