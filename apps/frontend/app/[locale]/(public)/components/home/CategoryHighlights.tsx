/**
 * Category Highlights Section - Slot 6
 * Per front-page category section with section color coding
 * Mix of Feature Cards and Compact Cards
 * Refactored from existing CategorySection logic
 */

import { Suspense } from 'react';
import { FeatureCard, CompactCard, FeatureCardSkeleton, CompactCardSkeleton, getSectionColor } from '@/components/cards';
import { fetchArticlesByCategory } from '@/lib/api/articles';
import { buildCategoryUrl } from '@/lib/utils/url-builder';
import type { Locale } from '@/lib/types';
import Link from 'next/link';

interface CategoryHighlightsProps {
  category: any;
  locale: string;
}

// Localized labels
const labels = {
  ro: {
    viewAll: 'Vezi toate',
  },
  en: {
    viewAll: 'View all',
  },
  ru: {
    viewAll: 'Смотреть все',
  },
} as const;

async function CategoryHighlightsContent({ category, locale }: CategoryHighlightsProps) {
  let articles: any[] = [];

  try {
    const response = await fetchArticlesByCategory(category.id, locale, 5); // Get 5 articles per category
    articles = response.member || [];
  } catch (error) {
    console.error(`Failed to fetch articles for category ${category.id}:`, error);
  }

  // Don't render if no articles
  if (articles.length === 0) {
    return null;
  }

  // Get category slug for color mapping
  const categorySlug = category.slug || 'default';
  const sectionColor = getSectionColor(categorySlug);
  const categoryTitle = category.title || category.name || 'Uncategorized';
  const categoryUrl = buildCategoryUrl(category, locale as Locale);
  const localeLabels = labels[locale as keyof typeof labels] || labels.ro;

  // Split articles: first 2 as Feature Cards, remaining as Compact Cards
  const featureArticles = articles.slice(0, 2);
  const compactArticles = articles.slice(2, 5);

  return (
    <div className="@container">
      {/* Section Header with Category Color Bar */}
      <div className="flex items-center justify-between mb-8">
        <h2
          className="font-sans font-semibold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] border-l-4 pl-3"
          style={{ fontSize: 'var(--font-size-2xl)', borderLeftColor: sectionColor }}
        >
          {categoryTitle}
        </h2>

        <Link
          href={categoryUrl}
          className="text-[var(--color-accent)] hover:text-[var(--color-accent-hover)] font-medium transition-colors duration-200 font-sans"
          style={{ fontSize: 'var(--font-size-sm)' }}
        >
          {localeLabels.viewAll} →
        </Link>
      </div>

      {/* Mixed Layout: Feature Cards + Compact Cards */}
      <div className="grid grid-cols-1 @lg:grid-cols-3 gap-6">

        {/* Left Side: Feature Cards */}
        <div className="@lg:col-span-2 grid grid-cols-1 @md:grid-cols-2 gap-4">
          {featureArticles.map((article) => (
            <FeatureCard
              key={article.id}
              article={article}
              locale={locale}
              showExcerpt={true}
              className="h-full"
            />
          ))}
        </div>

        {/* Right Side: Compact Cards Stack */}
        {compactArticles.length > 0 && (
          <div className="@lg:col-span-1">
            <div className="grid grid-cols-1 gap-4">
              {compactArticles.map((article) => (
                <CompactCard
                  key={article.id}
                  article={article}
                  locale={locale}
                  className="border-b border-[var(--color-border)] dark:border-[var(--color-border-dark)] pb-4 last:border-b-0 last:pb-0"
                />
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

function CategoryHighlightsSkeleton() {
  return (
    <div className="@container">
      {/* Section Header Skeleton */}
      <div className="flex items-center justify-between mb-8">
        <div className="border-l-4 border-[var(--color-skeleton)] dark:border-[var(--color-skeleton-dark)] pl-3">
          <div className="w-32 h-8 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse" />
        </div>
        <div className="w-20 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse" />
      </div>

      {/* Mixed Layout Skeleton */}
      <div className="grid grid-cols-1 @lg:grid-cols-3 gap-6">

        {/* Feature Cards Skeleton */}
        <div className="@lg:col-span-2 grid grid-cols-1 @md:grid-cols-2 gap-4">
          <FeatureCardSkeleton />
          <FeatureCardSkeleton />
        </div>

        {/* Compact Cards Skeleton */}
        <div className="@lg:col-span-1">
          <div className="grid grid-cols-1 gap-4">
            <CompactCardSkeleton />
            <CompactCardSkeleton />
            <CompactCardSkeleton />
          </div>
        </div>
      </div>
    </div>
  );
}

export default function CategoryHighlights({ category, locale }: CategoryHighlightsProps) {
  return (
    <Suspense fallback={<CategoryHighlightsSkeleton />}>
      <CategoryHighlightsContent category={category} locale={locale} />
    </Suspense>
  );
}