/**
 * Editor's Picks Section - Slot 4
 * 3x Feature Cards using ImportantArticles positions 2-4
 * Section header with localized titles
 */

import { Suspense } from 'react';
import { FeatureCard, FeatureCardSkeleton, getSectionColor } from '@/components/cards';
import { fetchImportantArticles } from '@/lib/api/important-articles';
import type { ImportantArticle } from '@/lib/types/article';
import type { Locale } from '@/lib/types';
import { buildLocalizedUrl } from '@/lib/utils/url-builder';
import Link from 'next/link';

interface EditorsPicksProps {
  locale: string;
}

// Localized labels
const labels = {
  ro: {
    editorsPicks: 'Alegerea editorului',
    viewAll: 'Vezi toate',
  },
  en: {
    editorsPicks: "Editor's Picks",
    viewAll: 'View all',
  },
  ru: {
    editorsPicks: 'Выбор редактора',
    viewAll: 'Смотреть все',
  },
} as const;

async function EditorsPicksContent({ locale }: EditorsPicksProps) {
  let editorsPicks: any[] = [];

  try {
    const response = await fetchImportantArticles(locale);
    const importantArticles = response.member || [];

    // Use positions 2-4 for editor's picks (index 1-3)
    editorsPicks = importantArticles
      .slice(1, 4)
      .map((item: ImportantArticle) => item.article);
  } catch (error) {
    console.error('Failed to fetch editor\'s picks:', error);
  }

  // Don't render if no articles
  if (editorsPicks.length === 0) {
    return null;
  }

  const sectionColor = getSectionColor('opinion');
  const localeLabels = labels[locale as keyof typeof labels] || labels.ro;

  return (
    <div className="@container">
      {/* Section Header */}
      <div className="flex items-center justify-between mb-8">
        <h2
          className="font-sans font-semibold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] border-l-4 pl-3"
          style={{ fontSize: 'var(--font-size-2xl)', borderLeftColor: sectionColor }}
        >
          {localeLabels.editorsPicks}
        </h2>

        <Link
          href={buildLocalizedUrl('/ultimele-stiri', locale as Locale)}
          className="text-[var(--color-accent)] hover:text-[var(--color-accent-hover)] font-medium transition-colors duration-200 font-sans"
          style={{ fontSize: 'var(--font-size-sm)' }}
        >
          {localeLabels.viewAll} →
        </Link>
      </div>

      {/* Cards Grid */}
      <div className="grid grid-cols-1 @md:grid-cols-2 @lg:grid-cols-3 gap-4">
        {editorsPicks.map((article) => (
          <FeatureCard
            key={article.id}
            article={article}
            locale={locale}
            showExcerpt={true}
            className="h-full"
          />
        ))}
      </div>
    </div>
  );
}

function EditorsPicksSkeleton() {
  return (
    <div className="@container">
      {/* Section Header Skeleton */}
      <div className="flex items-center justify-between mb-8">
        <div className="border-l-4 border-[var(--color-skeleton)] dark:border-[var(--color-skeleton-dark)] pl-3">
          <div className="w-48 h-8 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse" />
        </div>
        <div className="w-20 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse" />
      </div>

      {/* Cards Grid Skeleton */}
      <div className="grid grid-cols-1 @md:grid-cols-2 @lg:grid-cols-3 gap-4">
        {Array.from({ length: 3 }).map((_, index) => (
          <FeatureCardSkeleton key={index} />
        ))}
      </div>
    </div>
  );
}

export default function EditorsPicks({ locale }: EditorsPicksProps) {
  return (
    <Suspense fallback={<EditorsPicksSkeleton />}>
      <EditorsPicksContent locale={locale} />
    </Suspense>
  );
}