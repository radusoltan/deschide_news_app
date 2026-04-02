/**
 * Hero Section - Bento grid with auto-rotation
 *
 * Desktop (>=1024px):
 * ┌──────────────────────────┬──────────────────┐
 * │                          │   Secondary 1     │
 * │     Main Article         ├──────────────────┤
 * │     (col-span-7 row-2)   │   Secondary 2     │
 * │                          │                   │
 * ├──────┬──────┬──────┬─────┴───────────────────┤
 * │ Sm 1 │ Sm 2 │ Sm 3 │ Sm 4                    │
 * └──────┴──────┴──────┴─────────────────────────┘
 *
 * Shows 7 of N important articles at a time.
 * Rotates every 10 seconds with a crossfade.
 */

import { Suspense } from 'react';
import { fetchImportantArticles } from '@/lib/api/important-articles';
import { fetchLatestArticles } from '@/lib/api/articles';
import type { Article, ImportantArticle } from '@/lib/types/article';
import HeroRotator from './HeroRotator';

/* ------------------------------------------------------------------ */
/*  Server component — data fetching                                   */
/* ------------------------------------------------------------------ */

interface HeroSectionProps {
  locale: string;
}

async function HeroSectionContent({ locale }: HeroSectionProps) {
  let allArticles: Article[] = [];

  try {
    const [importantResult, latestResult] = await Promise.allSettled([
      fetchImportantArticles(locale),
      fetchLatestArticles(locale, 10),
    ]);

    const importantArticles =
      importantResult.status === 'fulfilled'
        ? importantResult.value.member || []
        : [];

    const latestArticles =
      latestResult.status === 'fulfilled'
        ? latestResult.value.member || []
        : [];

    // Extract articles from important list, with badged articles first (pinned)
    // isFeatured is visual-only — does NOT affect ordering
    const extracted = importantArticles.map(
      (item: ImportantArticle) => item.article,
    );
    const badged = extracted.filter((a: Article) => a.badge);
    const rest = extracted.filter((a: Article) => !a.badge);
    allArticles = [...badged, ...rest];

    // If fewer than 7 important articles, back-fill from latest
    if (allArticles.length < 7) {
      const usedIds = new Set(allArticles.map((a) => a.id));
      const fillers = latestArticles.filter(
        (a: Article) => !usedIds.has(a.id),
      );
      allArticles = [...allArticles, ...fillers].slice(0, 7);
    }
  } catch (error) {
    console.error('Failed to fetch hero section data:', error);
  }

  if (allArticles.length === 0) return null;

  return <HeroRotator allArticles={allArticles} locale={locale} />;
}

/* ------------------------------------------------------------------ */
/*  Skeleton                                                           */
/* ------------------------------------------------------------------ */

function HeroSectionSkeleton() {
  return (
    <div className="w-full">
      {/* Top row */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-1">
        <div className="md:col-span-2 lg:col-span-7 lg:row-span-2 min-h-[280px] md:min-h-[360px] lg:min-h-[480px] bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] animate-pulse rounded-[var(--radius-card)] lg:rounded-none lg:rounded-tl-[var(--radius-card)]" />
        <div className="min-h-[180px] lg:col-span-5 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] animate-pulse rounded-[var(--radius-card)] lg:rounded-none lg:rounded-tr-[var(--radius-card)]" />
        <div className="min-h-[180px] lg:col-span-5 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] animate-pulse rounded-[var(--radius-card)] lg:rounded-none" />
      </div>

      {/* Bottom row */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-1 mt-1">
        {Array.from({ length: 4 }).map((_, i) => (
          <div
            key={i}
            className={`min-h-[160px] md:min-h-[200px] bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] animate-pulse rounded-[var(--radius-card)] lg:rounded-none ${
              i === 0
                ? 'lg:rounded-bl-[var(--radius-card)]'
                : i === 3
                  ? 'lg:rounded-br-[var(--radius-card)]'
                  : ''
            }`}
          />
        ))}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Export                                                              */
/* ------------------------------------------------------------------ */

export default function HeroSection({ locale }: HeroSectionProps) {
  return (
    <Suspense fallback={<HeroSectionSkeleton />}>
      <HeroSectionContent locale={locale} />
    </Suspense>
  );
}
