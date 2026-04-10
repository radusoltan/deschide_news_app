/**
 * Most Popular Articles Sidebar Component
 * Displays a simple numbered list of popular articles - matches homepage design
 */

import Link from 'next/link';
import { fetchArticlesByCategory } from '@/lib/api/articles';
import { buildArticleUrl } from '@/lib/utils/url-builder';
import type { Locale } from '@/lib/types';

interface MostPopularProps {
  locale: Locale;
  categoryId: number;
  limit?: number;
}

export default async function MostPopular({
  locale,
  categoryId,
  limit = 10
}: MostPopularProps) {
  let articles: Article[] = [];

  try {
    const response = await fetchArticlesByCategory(categoryId, locale, limit);
    articles = response.member || [];
  } catch (error) {
    console.error('Failed to fetch articles for Most Popular:', error);
  }

  if (articles.length === 0) {
    return null;
  }

  return (
    <div className="w-full bg-surface">
      {/* Simple Header - matches homepage pattern */}
      <div className="p-4 bg-gray-100">
        <h2 className="text-lg font-bold text-brand-oxford-900">Most Popular</h2>
      </div>

      {/* Simple Articles List */}
      <ul className="post-number">
        {articles.map((article) => {
          const articleUrl = buildArticleUrl(article, locale);

          return (
            <li
              key={article.id}
              className="border-b border-gray-100 hover:bg-surface-sunken transition-colors"
            >
              <Link
                href={articleUrl}
                className="text-base font-bold px-6 py-3 flex flex-row items-center text-brand-oxford-900 hover:text-brand-tomato-500 transition-colors"
              >
                {article.title}
              </Link>
            </li>
          );
        })}
      </ul>
    </div>
  );
}
