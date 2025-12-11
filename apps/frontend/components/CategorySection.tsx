/**
 * Category Section Component
 * Displays a section with category title and 6 articles in 3-column grid
 */

import Image from 'next/image';
import { Category } from '@/lib/types/article';
import { fetchArticlesByCategory } from '@/lib/api/articles';
import ArticleCard from './ArticleCard';

interface CategorySectionProps {
  category: Category;
  locale: string;
}

export default async function CategorySection({
  category,
  locale,
}: CategorySectionProps) {
  // Fetch articles for this category
  let articles;
  try {
    const response = await fetchArticlesByCategory(category.id, locale, 6);
    articles = response.member || [];
  } catch (error) {
    console.error(`Failed to fetch articles for category ${category.title}:`, error);
    return null; // Skip this category if fetch fails
  }

  // Don't render if no articles
  if (articles.length === 0) {
    return null;
  }

  return (
    <div className="bg-white">
      <div className="xl:container mx-auto p-3 sm:p-4 xl:p-2">
        <div className="flex flex-row flex-wrap">
          {/* Left - Articles (2/3 width) */}
          <div className="flex-shrink max-w-full w-full lg:w-2/3 overflow-hidden">
            <div className="w-full py-3">
              <h2 className="text-brand-oxford-900 text-2xl font-heading uppercase">
                <span className="inline-block h-5 border-l-3 border-brand-tomato-500 mr-2"></span>
                {category.title}
              </h2>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-6">
              {articles.map((article) => (
                <ArticleCard
                  key={article.id}
                  article={article}
                  locale={locale}
                  thumbnailProfile="article_card"
                />
              ))}
            </div>
          </div>

          {/* Right - Advertisement (1/3 width) */}
          <div className="flex-shrink max-w-full w-full lg:w-1/3 lg:pl-8 lg:pt-14 lg:pb-8 order-first lg:order-last">
            <div className="w-full h-full">
              <div className="text-sm py-6 sticky top-10">
                <div className="w-full text-center">
                  <a className="uppercase" href="#">
                    Advertisement
                  </a>
                  <a href="#">
                    <Image
                      className="mx-auto"
                      src="/tailnews/dummy/img12.jpg"
                      alt="advertisement area"
                      width={300}
                      height={250}
                      loading="lazy"
                      placeholder="blur"
                      blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjI1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjNmNGY2Ii8+PC9zdmc+"
                    />
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
