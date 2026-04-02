/**
 * Category Section Component
 * Displays a section with category title, accent bar, and articles grid
 * Features "View All" link and brand typography
 */

import Link from 'next/link';
import { Category } from '@/lib/types/article';
import { fetchArticlesByCategory } from '@/lib/api/articles';
import { buildCategoryUrl } from '@/lib/utils/url-builder';
import ArticleCard from './ArticleCard';
import type { Locale } from '@/lib/types';

interface CategorySectionProps {
  category: Category;
  locale: Locale;
}

const viewAllLabels: Record<string, string> = {
  ro: 'Vezi toate',
  en: 'View all',
  ru: 'Смотреть все',
};

export default async function CategorySection({
  category,
  locale,
}: CategorySectionProps) {
  let articles;
  try {
    const response = await fetchArticlesByCategory(category.id, locale, 6);
    articles = response.member || [];
  } catch (error) {
    console.error(`Failed to fetch articles for category ${category.title}:`, error);
    return null;
  }

  if (articles.length === 0) {
    return null;
  }

  const categoryUrl = buildCategoryUrl(category, locale as Locale);
  const viewAllLabel = viewAllLabels[locale] || viewAllLabels.ro;

  return (
    <section className="bg-surface py-6">
      <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
        {/* Section header */}
        <div className="flex items-center justify-between mb-5">
          <h2 className="text-brand-oxford-900 text-2xl font-heading uppercase flex items-center">
            <span className="inline-block w-1 h-6 bg-brand-tomato-500 mr-3 rounded-full" />
            {category.title}
          </h2>
          <Link
            href={categoryUrl}
            className="inline-flex items-center gap-1 text-sm font-body font-medium text-brand-tomato-500 hover:text-brand-tomato-600 transition-colors group"
          >
            {viewAllLabel}
            <svg
              className="w-4 h-4 transform group-hover:translate-x-0.5 transition-transform"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
            </svg>
          </Link>
        </div>

        {/* Articles grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-6">
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
    </section>
  );
}
