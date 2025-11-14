/**
 * Most Popular Articles Sidebar Component
 * Displays a numbered list of articles from the same category
 */

import Link from 'next/link';
import { fetchArticlesByCategory } from '@/lib/api/articles';

interface MostPopularProps {
  locale: string;
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
        <div className="p-4 bg-gray-100">
          <h2 className="text-lg font-bold">Most Popular</h2>
        </div>
        <ul className="post-number">
          {articles.map((article) => {
            const categorySlug = article.category?.slug || 'uncategorized';
            const articleUrl = `/${locale}/${categorySlug}/${article.slug}`;

            return (
              <li key={article.id} className="border-b border-gray-100 hover:bg-gray-50">
                <Link
                  href={articleUrl}
                  className="text-lg font-bold px-6 py-3 flex flex-row items-center"
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
