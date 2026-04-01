/**
 * Most Read Section - Slot 5
 * 5x Compact Cards with rank numbers (1-5)
 * Using trending articles from getTrendingArticles API
 */

import { Suspense } from 'react';
import { CompactCard, CompactCardSkeleton, getSectionColor } from '@/components/cards';
import { getTrendingArticles } from '@/lib/api/statistics';
import type { Locale } from '@/lib/types';

interface MostReadSectionProps {
  locale: string;
}

// Localized labels
const labels = {
  ro: {
    mostRead: 'Cele mai citite',
  },
  en: {
    mostRead: 'Most Read',
  },
  ru: {
    mostRead: 'Самое популярное',
  },
} as const;

async function MostReadSectionContent({ locale }: MostReadSectionProps) {
  let trendingArticles: any[] = [];

  try {
    trendingArticles = await getTrendingArticles(5, locale as Locale);
  } catch (error) {
    console.error('Failed to fetch trending articles:', error);
  }

  // Don't render if no articles
  if (!trendingArticles || trendingArticles.length === 0) {
    return null;
  }

  const sectionColor = getSectionColor('politics');
  const localeLabels = labels[locale as keyof typeof labels] || labels.ro;

  return (
    <div className="@container">
      {/* Section Header */}
      <div className="mb-8">
        <h2
          className="font-sans font-semibold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] border-l-4 pl-3"
          style={{ fontSize: 'var(--font-size-2xl)', borderLeftColor: sectionColor }}
        >
          {localeLabels.mostRead}
        </h2>
      </div>

      {/* Most Read Cards */}
      <div className="grid grid-cols-1 gap-4 max-w-2xl">
        {trendingArticles.slice(0, 5).map((article, index) => (
          <div key={article.id} className="relative">
            <CompactCard
              article={article}
              locale={locale}
              rank={index + 1}
              className="border-b border-[var(--color-border)] dark:border-[var(--color-border-dark)] pb-4 last:border-b-0 last:pb-0"
            />
          </div>
        ))}
      </div>
    </div>
  );
}

function MostReadSectionSkeleton() {
  return (
    <div className="@container">
      {/* Section Header Skeleton */}
      <div className="mb-8">
        <div className="border-l-4 border-[var(--color-skeleton)] dark:border-[var(--color-skeleton-dark)] pl-3">
          <div className="w-40 h-8 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse" />
        </div>
      </div>

      {/* Cards Skeleton */}
      <div className="grid grid-cols-1 gap-4 max-w-2xl">
        {Array.from({ length: 5 }).map((_, index) => (
          <div key={index} className="border-b border-[var(--color-border)] dark:border-[var(--color-border-dark)] pb-4 last:border-b-0 last:pb-0">
            <CompactCardSkeleton />
          </div>
        ))}
      </div>
    </div>
  );
}

export default function MostReadSection({ locale }: MostReadSectionProps) {
  return (
    <Suspense fallback={<MostReadSectionSkeleton />}>
      <MostReadSectionContent locale={locale} />
    </Suspense>
  );
}