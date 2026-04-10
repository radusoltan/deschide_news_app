/**
 * Related Articles Component
 * Displays related articles from the same category
 */

import ArticleCard from './ArticleCard';
import type { Locale } from '@/lib/types';

interface RelatedArticlesProps {
  articles: Article[];
  locale: Locale;
  title?: string;
  variant?: 'default' | 'horizontal' | 'minimal';
  className?: string;
}

export default function RelatedArticles({
  articles,
  locale,
  title = 'Related Articles',
  variant = 'default',
  className = '',
}: RelatedArticlesProps) {
  if (!articles || articles.length === 0) {
    return null;
  }

  return (
    <section className={`${className}`}>
      {/* Section Header */}
      <div className="mb-6">
        <h2 className="text-2xl font-bold text-primary border-b-2 border-red-600 pb-2 inline-block">
          {title}
        </h2>
      </div>

      {/* Articles Grid/List */}
      {variant === 'default' ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {articles.map((article) => (
            <ArticleCard
              key={article.id}
              article={article}
              locale={locale}
              variant="default"
              showCategory={false}
              showDate={true}
            />
          ))}
        </div>
      ) : (
        <div className="space-y-4">
          {articles.map((article) => (
            <ArticleCard
              key={article.id}
              article={article}
              locale={locale}
              variant={variant}
              showCategory={false}
              showDate={true}
            />
          ))}
        </div>
      )}
    </section>
  );
}
