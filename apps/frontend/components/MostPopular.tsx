/**
 * Most Popular Articles Sidebar Component
 * Displays a numbered list of articles from the same category
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
  limit = 5
}: MostPopularProps) {
  let articles: any[] = [];

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
    <div className="w-full bg-white">
      <div className="mb-6">
        <div className="p-4 bg-brand-oxford-900">
          <h2 className="text-lg font-heading text-white">Most Popular</h2>
        </div>
        <ul className="post-number">
          {articles.map((article) => {
            const articleUrl = buildArticleUrl(article, locale);

            return (
              <li key={article.id} className="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                <Link
                  href={articleUrl}
                  className="text-lg font-heading text-brand-oxford-900 hover:text-brand-tomato-500 px-6 py-3 flex flex-row items-center transition-colors"
                >
                  {article.title}
                </Link>
              </li>
            );
          })}
        </ul>
      </div>
    </div>
  );
}
