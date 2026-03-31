/**
 * OpinionSection — NYT Opinion page layout
 *
 * Matches nytimes.com/section/opinion:
 *
 * Top row — 3 large equal cards:
 * ┌──────────────────┬──────────────────┬──────────────────┐
 * │   [image]        │   [image]        │   [image]        │
 * │   LABEL          │   LABEL          │   AUTHOR NAME    │
 * │   Title (serif)  │   Title (serif)  │   Title (serif)  │
 * │   Excerpt        │   Excerpt        │   Excerpt        │
 * │   2h · By Name   │   1h · By Name   │   10h · By Name  │
 * └──────────────────┴──────────────────┴──────────────────┘
 *
 * Bottom row — 5 smaller equal cards:
 * ┌─────┬─────┬─────┬─────┬─────┐
 * │LABEL│LABEL│LABEL│LABEL│LABEL│
 * │[img]│[img]│[img]│[img]│[img]│
 * │Title│Title│Title│Title│Title│
 * └─────┴─────┴─────┴─────┴─────┘
 *
 * NOTE: Article.authors contains only IRIs (no name/avatar).
 * TODO: Replace PLACEHOLDER_AUTHORS with real expanded author data.
 */

import { Suspense } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { fetchArticlesByCategory } from '@/lib/api/articles';
import {
  getFeaturedImage,
  getThumbnailByProfile,
  buildImageUrl,
} from '@/lib/api/important-articles';
import { buildArticleUrl } from '@/lib/utils/url-builder';
import { formatRelativeTime } from '@/components/cards/utils';
import type { Article, Author } from '@/lib/types/article';
import type { Locale } from '@/lib/types';

/* ================================================================== */
/*  Localized labels                                                   */
/* ================================================================== */

const labels = {
  ro: { opinions: 'Opinii', seeAll: 'Vezi toate', opinionLabel: 'Opinie', by: 'De' },
  en: { opinions: 'Opinions', seeAll: 'See all', opinionLabel: 'Guest Essay', by: 'By' },
  ru: { opinions: 'Мнения', seeAll: 'Смотреть все', opinionLabel: 'Мнение', by: '' },
} as const;

/** Extract the first author's full name from an article */
function getAuthorName(article: Article): string {
  const first = article.authors?.[0];
  if (!first) return 'Redacția';
  if (typeof first === 'string') return 'Redacția';
  return (first as Author).fullName || `${(first as Author).firstName} ${(first as Author).lastName}`.trim() || 'Redacția';
}

/* ================================================================== */
/*  Sub-components                                                     */
/* ================================================================== */

interface OpinionSectionProps {
  locale: string;
  categoryId: number;
}

/** Large opinion card — image + label + bold serif title + excerpt + byline */
function OpinionLargeCard({
  article,
  authorName,
  locale,
  labelText,
  isAuthorLabel,
}: {
  article: Article;
  authorName: string;
  locale: string;
  labelText: string;
  isAuthorLabel?: boolean;
}) {
  const l = labels[locale as keyof typeof labels] || labels.ro;
  const articleUrl = buildArticleUrl(article, locale as Locale);
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, 'card_large')
    : null;
  const imageToUse = thumbnail || featuredImage;
  const relativeTime = article.publishedAt
    ? formatRelativeTime(article.publishedAt, locale)
    : '';

  return (
    <article className="group flex flex-col">
      <Link href={articleUrl} className="block flex-1">
        {/* Image */}
        {imageToUse ? (
          <div className="relative aspect-[3/2] overflow-hidden mb-3">
            <Image
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              fill
              className="object-cover transition-transform duration-500 group-hover:scale-[1.02]"
              sizes="(max-width: 768px) 100vw, 33vw"
            />
          </div>
        ) : (
          <div className="aspect-[3/2] bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)] mb-3 flex items-center justify-center">
            <span className="text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] text-xs font-sans">
              Editorial
            </span>
          </div>
        )}

        {/* Label — small uppercase red for "OPINIE" or uppercase for author name */}
        <p
          className={`font-sans font-bold uppercase tracking-wider mb-1.5 text-[11px] ${
            isAuthorLabel
              ? 'text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)]'
              : 'text-[var(--color-section-opinion)]'
          }`}
        >
          {labelText}
        </p>

        {/* Title — bold serif */}
        <h3
          className="font-serif font-bold leading-tight text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] group-hover:text-[var(--color-text-secondary)] dark:group-hover:text-[var(--color-text-secondary-dark)] transition-colors mb-2 text-[length:var(--font-size-xl)]"
        >
          {article.title}
        </h3>

        {/* Excerpt */}
        {article.lead && (
          <p className="font-serif text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] leading-relaxed line-clamp-2 mb-2 text-sm">
            {article.lead}
          </p>
        )}
      </Link>

      {/* Byline: "2h ago · By AUTHOR" */}
      <p className="font-sans text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] mt-auto text-xs">

        {relativeTime && <>{relativeTime} · </>}
        {l.by && <>{l.by} </>}
        <span className="uppercase tracking-wide">{authorName}</span>
      </p>
    </article>
  );
}

/** Small opinion card — label + image + bold serif title */
function OpinionSmallCard({
  article,
  authorName,
  locale,
  labelText,
  isAuthorLabel,
}: {
  article: Article;
  authorName: string;
  locale: string;
  labelText: string;
  isAuthorLabel?: boolean;
}) {
  const articleUrl = buildArticleUrl(article, locale as Locale);
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, 'card_small')
    : null;
  const imageToUse = thumbnail || featuredImage;

  return (
    <article className="group flex flex-col">
      <Link href={articleUrl} className="block">
        {/* Label — above image */}
        <p
          className={`font-sans font-bold uppercase tracking-wider mb-2 text-[11px] ${
            isAuthorLabel
              ? 'text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)]'
              : 'text-[var(--color-section-opinion)]'
          }`}
        >
          {labelText}
        </p>

        {/* Image */}
        {imageToUse ? (
          <div className="relative aspect-[4/3] overflow-hidden mb-2.5">
            <Image
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              fill
              className="object-cover transition-transform duration-500 group-hover:scale-[1.02]"
              sizes="(max-width: 768px) 50vw, 20vw"
            />
          </div>
        ) : (
          <div className="aspect-[4/3] bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)] mb-2.5" />
        )}

        {/* Title — bold serif */}
        <h3 className="font-serif font-bold leading-snug text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] group-hover:text-[var(--color-text-secondary)] dark:group-hover:text-[var(--color-text-secondary-dark)] transition-colors line-clamp-3 text-sm">
          {article.title}
        </h3>
      </Link>
    </article>
  );
}

/* ================================================================== */
/*  Main section                                                       */
/* ================================================================== */

async function OpinionSectionContent({ locale, categoryId }: OpinionSectionProps) {
  let articles: Article[] = [];

  try {
    const response = await fetchArticlesByCategory(categoryId, locale, 8);
    articles = response.member || [];
  } catch (error) {
    console.error('Failed to fetch opinion articles:', error);
  }

  if (articles.length === 0) return null;

  const l = labels[locale as keyof typeof labels] || labels.ro;

  // Top row: first 3 articles (large cards)
  const topArticles = articles.slice(0, 3);
  // Bottom row: next 5 articles (small cards)
  const bottomArticles = articles.slice(3, 8);

  // Assign labels: alternate between "OPINIE" and author name as label (NYT style)
  const getLabel = (article: Article, index: number) => {
    if (index % 2 === 0) {
      return { text: l.opinionLabel, isAuthor: false };
    }
    return { text: getAuthorName(article), isAuthor: true };
  };

  return (
    <section className="my-10 md:my-14">
      {/* ═══ Top border ═══ */}
      <div className="border-t-[3px] border-double border-[var(--color-text-primary)] dark:border-[var(--color-text-primary-dark)]" />

      {/* Header */}
      <div className="flex items-center justify-between pt-4 pb-3 mb-6 border-b border-[var(--color-border)] dark:border-[var(--color-border-dark)]">
        <h2 className="font-sans font-bold uppercase tracking-widest text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] text-sm">
          {l.opinions}
        </h2>
        <Link
          href={`/${locale === 'ro' ? '' : locale + '/'}opinii`}
          className="font-sans font-medium text-[var(--color-section-opinion)] hover:underline text-sm"
        >
          {l.seeAll} &rarr;
        </Link>
      </div>

      {/* ═══ Top row: 3 large cards ═══ */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-5">
        {topArticles.map((article, index) => {
          const label = getLabel(article, index);
          return (
            <OpinionLargeCard
              key={article.id}
              article={article}
              authorName={getAuthorName(article)}
              locale={locale}
              labelText={label.text}
              isAuthorLabel={label.isAuthor}
            />
          );
        })}
      </div>

      {/* Separator between rows */}
      <div className="my-6 border-t border-[var(--color-border)] dark:border-[var(--color-border-dark)]" />

      {/* ═══ Bottom row: 5 small cards ═══ */}
      {bottomArticles.length > 0 && (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-5">
          {bottomArticles.map((article, index) => {
            const absIndex = index + 3;
            const label = getLabel(article, absIndex);
            return (
              <OpinionSmallCard
                key={article.id}
                article={article}
                authorName={getAuthorName(article)}
                locale={locale}
                labelText={label.text}
                isAuthorLabel={label.isAuthor}
              />
            );
          })}
        </div>
      )}

      {/* ═══ Bottom border ═══ */}
      <div className="mt-6 border-t-[3px] border-double border-[var(--color-text-primary)] dark:border-[var(--color-text-primary-dark)]" />
    </section>
  );
}

/* ================================================================== */
/*  Skeleton                                                           */
/* ================================================================== */

function OpinionSectionSkeleton() {
  return (
    <section className="my-10 md:my-14">
      <div className="border-t-[3px] border-double border-[var(--color-skeleton)] dark:border-[var(--color-skeleton-dark)]" />

      <div className="flex items-center justify-between pt-4 pb-3 mb-6 border-b border-[var(--color-skeleton)] dark:border-[var(--color-skeleton-dark)]">
        <div className="w-20 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse" />
        <div className="w-24 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse" />
      </div>

      {/* Top row skeleton */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {Array.from({ length: 3 }).map((_, i) => (
          <div key={i} className="animate-pulse space-y-3">
            <div className="aspect-[3/2] bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)]" />
            <div className="h-3 w-20 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
            <div className="h-5 w-full bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
            <div className="h-5 w-3/4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
            <div className="h-4 w-full bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
            <div className="h-3 w-36 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
          </div>
        ))}
      </div>

      <div className="my-6 border-t border-[var(--color-skeleton)] dark:border-[var(--color-skeleton-dark)]" />

      {/* Bottom row skeleton */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-5">
        {Array.from({ length: 5 }).map((_, i) => (
          <div key={i} className="animate-pulse space-y-2">
            <div className="h-3 w-20 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
            <div className="aspect-[4/3] bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)]" />
            <div className="h-4 w-full bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
            <div className="h-4 w-2/3 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
          </div>
        ))}
      </div>

      <div className="mt-6 border-t-[3px] border-double border-[var(--color-skeleton)] dark:border-[var(--color-skeleton-dark)]" />
    </section>
  );
}

/* ================================================================== */
/*  Export                                                              */
/* ================================================================== */

export default function OpinionSection({ locale, categoryId }: OpinionSectionProps) {
  return (
    <Suspense fallback={<OpinionSectionSkeleton />}>
      <OpinionSectionContent locale={locale} categoryId={categoryId} />
    </Suspense>
  );
}
