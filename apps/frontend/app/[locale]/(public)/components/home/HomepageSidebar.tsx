/**
 * Homepage Sidebar — sticky sidebar with Most Popular + ad placeholder
 * Appears beside category sections on desktop (4/12 columns).
 */

import { Suspense } from 'react';
import Link from 'next/link';
import { getTrendingArticles } from '@/lib/api/statistics';
import { getSectionColor } from '@/components/cards/utils';
import { getCategorySlugForLocale } from '@/lib/utils/url-builder';
import type { Locale } from '@/lib/types';
import type { TrendingArticle } from '@/lib/api/statistics';

/* ================================================================== */
/*  Localized labels                                                   */
/* ================================================================== */

const labels = {
  ro: { mostPopular: 'Cele mai citite', ad: 'Publicitate' },
  en: { mostPopular: 'Most Popular', ad: 'Advertisement' },
  ru: { mostPopular: 'Самое популярное', ad: 'Реклама' },
} as const;

/* ================================================================== */
/*  Content                                                            */
/* ================================================================== */

interface HomepageSidebarProps {
  locale: string;
}

export async function SidebarContent({ locale }: HomepageSidebarProps) {
  let trending: TrendingArticle[] = [];

  try {
    trending = await getTrendingArticles(10, locale as Locale);
  } catch (error) {
    console.error('Failed to fetch trending articles for sidebar:', error);
  }

  const l = labels[locale as keyof typeof labels] || labels.ro;

  return (
    <aside className="sticky top-24 space-y-8">
      {/* Most Popular — "În trend" style with colored category dots */}
      {trending.length > 0 && (
        <div>
          <h2
            className="font-sans font-bold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] mb-4 pb-2 border-b-2 border-[var(--color-accent)]"
            style={{ fontSize: 'var(--font-size-lg)' }}
          >
            {l.mostPopular}
          </h2>

          <ul className="space-y-0">
            {trending.slice(0, 10).map((article) => {
              const catSlug = getCategorySlugForLocale(article.category, locale as Locale);
              const sectionColor = getSectionColor(catSlug);
              const localePrefix = locale === 'ro' ? '' : `${locale}/`;
              const articleUrl = `/${localePrefix}${catSlug}/${article.slug}`;

              return (
                <li
                  key={article.id}
                  className="group py-3 border-b border-[var(--color-border)] dark:border-[var(--color-border-dark)] last:border-b-0"
                >
                  <Link href={articleUrl}>
                    <span
                      className="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider font-sans mb-1"
                      style={{ color: sectionColor }}
                    >
                      <span
                        className="inline-block w-1.5 h-1.5 rounded-full flex-shrink-0"
                        style={{ backgroundColor: sectionColor }}
                      />
                      {article.category?.name}
                    </span>
                    <p
                      className="font-sans font-medium leading-snug text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] group-hover:text-[var(--color-accent)] transition-colors cursor-pointer line-clamp-2"
                      style={{ fontSize: 'var(--font-size-sm)' }}
                    >
                      {article.title}
                    </p>
                  </Link>
                </li>
              );
            })}
          </ul>
        </div>
      )}

      {/* Ad placeholder */}
      <div className="bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)] rounded-[var(--radius-card)] p-6 text-center">
        <span className="text-xs text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] uppercase tracking-wider font-sans">
          {l.ad}
        </span>
        <div className="mt-3 mx-auto w-[250px] h-[250px] bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded-lg flex items-center justify-center">
          <span className="text-xs text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] font-sans">
            250 × 250
          </span>
        </div>
      </div>
    </aside>
  );
}

/* ================================================================== */
/*  Skeleton                                                           */
/* ================================================================== */

function SidebarSkeleton() {
  return (
    <aside className="sticky top-24 space-y-8">
      <div>
        <div className="w-40 h-6 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse mb-4 pb-2 border-b-2 border-[var(--color-skeleton)] dark:border-[var(--color-skeleton-dark)]" />
        {Array.from({ length: 10 }).map((_, i) => (
          <div key={i} className="py-3 animate-pulse space-y-1.5 border-b border-[var(--color-border)] dark:border-[var(--color-border-dark)] last:border-b-0">
            <div className="h-3 w-16 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
            <div className="h-4 w-full bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
          </div>
        ))}
      </div>
    </aside>
  );
}

/* ================================================================== */
/*  Export                                                              */
/* ================================================================== */

export default function HomepageSidebar({ locale }: HomepageSidebarProps) {
  return (
    <Suspense fallback={<SidebarSkeleton />}>
      <SidebarContent locale={locale} />
    </Suspense>
  );
}
