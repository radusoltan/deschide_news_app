'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import {
  getFeaturedImage,
  getThumbnailByProfile,
  buildImageUrl,
  fetchImportantArticles,
} from '@/lib/api/important-articles';
import { fetchLatestArticles } from '@/lib/api/articles';
import { buildArticleUrl } from '@/lib/utils/url-builder';
import {
  getSectionColor,
  getCategorySlugFromArticle,
  getCategoryTitle,
  formatRelativeTime,
  getLocalizedBadgeText,
} from '@/components/cards/utils';
import { useArticleUpdates } from '@/lib/hooks/useArticleUpdates';
import type { Article, ImportantArticle } from '@/lib/types/article';
import type { Locale } from '@/lib/types';

/* ------------------------------------------------------------------ */
/*  Constants                                                          */
/* ------------------------------------------------------------------ */

const ROTATION_INTERVAL = 10_000;
const HERO_SLOTS = 7;
const CARD_FADE_MS = 400;
const STAGGER_MS = 80;
const SEQUENCE_MS = CARD_FADE_MS + (HERO_SLOTS - 1) * STAGGER_MS;

/* Keyframes injected once — crossfade so no empty space ever appears */
const KEYFRAMES_CSS = `
@keyframes heroSlotOut {
  from { opacity: 1; transform: scale(1); }
  to   { opacity: 0; transform: scale(0.97); }
}
@keyframes heroSlotIn {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}`;

/* ------------------------------------------------------------------ */
/*  Shuffle (Fisher-Yates)                                             */
/* ------------------------------------------------------------------ */

function shuffle<T>(arr: T[]): T[] {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

/* ------------------------------------------------------------------ */
/*  Overlay Card                                                       */
/* ------------------------------------------------------------------ */

interface OverlayCardProps {
  article: Article;
  locale: string;
  priority?: boolean;
  thumbnailProfile?: string;
  sizes?: string;
  headingLevel?: 2 | 3;
  showExcerpt?: boolean;
  headlineClass?: string;
  className?: string;
}

function OverlayCard({
  article,
  locale,
  priority = false,
  thumbnailProfile = 'card_large',
  sizes = '(max-width: 768px) 100vw, 33vw',
  headingLevel = 3,
  showExcerpt = false,
  headlineClass = 'text-lg md:text-xl',
  className = '',
}: OverlayCardProps) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, thumbnailProfile)
    : null;
  const imageToUse = thumbnail || featuredImage;

  const articleUrl = buildArticleUrl(article, locale as Locale);
  const categorySlug = getCategorySlugFromArticle(article.category);
  const categoryTitle = getCategoryTitle(article.category);
  const sectionColor = getSectionColor(categorySlug);
  const relativeTime = article.publishedAt
    ? formatRelativeTime(article.publishedAt, locale)
    : '';
  const badgeText = getLocalizedBadgeText(article.badge, locale);
  const Heading = headingLevel === 2 ? 'h2' : 'h3';

  // Badge-colored gradient overlay using CSS variables
  const badgeGradientStyle: React.CSSProperties | undefined = article.badge
    ? {
        background: article.badge === 'breaking'
          ? 'linear-gradient(to top, color-mix(in oklch, var(--color-breaking), black 30%) 0%, oklch(55% 0.22 25 / 0.35) 50%, transparent 100%)'
          : article.badge === 'alert'
            ? 'linear-gradient(to top, color-mix(in oklch, var(--color-alert), black 45%) 0%, oklch(70% 0.18 85 / 0.30) 50%, transparent 100%)'
            : 'linear-gradient(to top, color-mix(in oklch, var(--color-flash), black 30%) 0%, oklch(55% 0.20 280 / 0.35) 50%, transparent 100%)',
      }
    : undefined;

  return (
    <article className={`group relative overflow-hidden ${className}`}>
      <Link href={articleUrl} className="block h-full">
        <figure className="relative w-full h-full">
          {imageToUse ? (
            <Image
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              fill
              sizes={sizes}
              className="object-cover transition-transform duration-700 group-hover:scale-105"
              loading={priority ? 'eager' : 'lazy'}
              fetchPriority={priority ? 'high' : 'auto'}
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-neutral-700 via-neutral-800 to-neutral-900" />
          )}
        </figure>

        <div
          className={`absolute inset-0 ${!article.badge ? 'bg-gradient-to-t from-black/80 via-black/40 to-transparent' : ''}`}
          style={badgeGradientStyle}
        />

        {/* Featured article indicator — top-right star */}
        {article.isFeatured && (
          <span className="absolute top-3 right-3 z-10 flex items-center gap-1 px-2 py-1 rounded bg-amber-500/90 text-white text-[11px] font-bold uppercase tracking-wider font-sans shadow-lg">
            <svg className="w-3.5 h-3.5 fill-current" viewBox="0 0 20 20" aria-hidden="true">
              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
            </svg>
            Featured
          </span>
        )}

        <div className="absolute inset-x-0 bottom-0 p-4 md:p-5 lg:p-6">
          <div className="flex items-center gap-2 mb-2">
            {categoryTitle && (
              <span
                className="inline-block px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white rounded font-sans"
                style={{ backgroundColor: sectionColor }}
              >
                {categoryTitle}
              </span>
            )}
            {badgeText && (
              <span className="inline-block px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white rounded bg-[var(--color-breaking)] animate-breaking-pulse font-sans">
                {badgeText}
              </span>
            )}
          </div>

          <Heading
            className={`text-white font-sans font-bold leading-tight text-on-photo-strong ${headlineClass}`}
          >
            {article.title}
          </Heading>

          {showExcerpt && article.lead && (
            <p className="mt-2 text-sm text-white/80 text-on-photo line-clamp-2 font-serif hidden md:block">
              {article.lead}
            </p>
          )}

          {relativeTime && (
            <time
              dateTime={article.publishedAt || ''}
              className="block mt-2 text-xs text-white/70 text-on-photo font-sans"
            >
              {relativeTime}
            </time>
          )}
        </div>

        <div className="absolute inset-0 opacity-0 group-focus-within:opacity-100 ring-2 ring-inset ring-[var(--color-focus)] dark:ring-[var(--color-focus-dark)] pointer-events-none transition-opacity duration-200" />
      </Link>
    </article>
  );
}

/* ------------------------------------------------------------------ */
/*  CrossfadeSlot — old card fades out while new card fades in         */
/*  Uses CSS Grid stacking (both layers in same cell) so the cards     */
/*  keep their full height from the parent grid.                       */
/* ------------------------------------------------------------------ */

interface CrossfadeSlotProps {
  index: number;
  crossfading: boolean;
  pinned?: boolean;
  current: React.ReactNode;
  previous?: React.ReactNode;
  className?: string;
}

function CrossfadeSlot({
  index,
  crossfading,
  pinned = false,
  current,
  previous,
  className = '',
}: CrossfadeSlotProps) {
  /* When idle or pinned, render flat — no animation */
  if (!crossfading || pinned) {
    return <div className={className}>{current}</div>;
  }

  /* During crossfade: both layers occupy the same grid cell */
  return (
    <div className={`grid overflow-hidden ${className}`}>
      {/* Outgoing — fades to transparent */}
      {previous && (
        <div
          className="col-start-1 row-start-1 z-[2]"
          style={{
            animation: `heroSlotOut ${CARD_FADE_MS}ms ease ${index * STAGGER_MS}ms both`,
          }}
        >
          {previous}
        </div>
      )}

      {/* Incoming — fades in underneath */}
      <div
        className="col-start-1 row-start-1 z-[1]"
        style={{
          animation: `heroSlotIn ${CARD_FADE_MS}ms ease ${index * STAGGER_MS}ms both`,
        }}
      >
        {current}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Hero Rotator                                                       */
/* ------------------------------------------------------------------ */

interface HeroRotatorProps {
  allArticles: Article[];
  locale: string;
}

/**
 * Sort articles so that badged articles come first (pinned positions),
 * then the rest in their original order.
 * isFeatured is only a visual indicator — it does NOT affect pinning or rotation.
 */
function prioritizeBadged(articles: Article[]): Article[] {
  const badged = articles.filter((a) => a.badge);
  const rest = articles.filter((a) => !a.badge);
  return [...badged, ...rest];
}

export default function HeroRotator({ allArticles: initialArticles, locale }: HeroRotatorProps) {
  const [allArticles, setAllArticles] = useState<Article[]>(initialArticles);
  const [articles, setArticles] = useState<Article[]>(() =>
    prioritizeBadged(initialArticles).slice(0, HERO_SLOTS),
  );
  const [prevArticles, setPrevArticles] = useState<Article[] | null>(null);
  const [crossfading, setCrossfading] = useState(false);

  /* Refs to avoid stale closures in setInterval callback */
  const articlesRef = useRef(articles);
  articlesRef.current = articles;
  const allArticlesRef = useRef(allArticles);
  allArticlesRef.current = allArticles;
  const busyRef = useRef(false);
  const cleanupRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  /* ---- Mercure SSE: debounced re-fetch when backend notifies changes ---- */
  const mercureTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useArticleUpdates(
    useCallback(() => {
      // Debounce: multiple rapid events (e.g. 3 badge changes) coalesce into one fetch
      if (mercureTimerRef.current) clearTimeout(mercureTimerRef.current);
      mercureTimerRef.current = setTimeout(async () => {
        try {
          const [importantRes, latestRes] = await Promise.allSettled([
            fetchImportantArticles(locale),
            fetchLatestArticles(locale, 10),
          ]);

          const important =
            importantRes.status === 'fulfilled'
              ? (importantRes.value.member || []).map((item: ImportantArticle) => item.article)
              : [];
          const latest =
            latestRes.status === 'fulfilled' ? latestRes.value.member || [] : [];

          const badged = important.filter((a: Article) => a.badge);
          const rest = important.filter((a: Article) => !a.badge);
          let fresh = [...badged, ...rest];

          if (fresh.length < HERO_SLOTS) {
            const usedIds = new Set(fresh.map((a) => a.id));
            const fillers = latest.filter((a: Article) => !usedIds.has(a.id));
            fresh = [...fresh, ...fillers].slice(0, Math.max(fresh.length, HERO_SLOTS));
          }

          // Clean rebuild: badged articles first, then rest — no stale merge
          const display = prioritizeBadged(fresh).slice(0, HERO_SLOTS);
          setAllArticles(fresh);
          setArticles(display);
        } catch {
          // silently ignore
        }
      }, 1500); // 1.5s debounce to coalesce rapid events
    }, [locale]),
  );

  const rotate = useCallback(() => {
    if (busyRef.current) return;
    busyRef.current = true;

    const current = allArticlesRef.current;
    const prev = articlesRef.current;

    // Pinned articles (badge only) keep their exact slot positions
    // isFeatured is visual-only — does NOT pin
    const pinnedIds = new Set(prev.filter((a) => a.badge).map((a) => a.id));

    // Unpinned pool: all articles that are NOT currently pinned
    const unpinnedPool = current.filter((a) => !pinnedIds.has(a.id));
    const shuffled = shuffle(unpinnedPool);

    // Build next array: pinned slots stay, unpinned slots get new articles
    let poolIdx = 0;
    const next = prev.map((a) => {
      if (a.badge) return a; // pinned — keep
      return poolIdx < shuffled.length ? shuffled[poolIdx++] : a;
    });

    setPrevArticles([...prev]);
    setArticles(next);
    setCrossfading(true);

    cleanupRef.current = setTimeout(() => {
      setPrevArticles(null);
      setCrossfading(false);
      busyRef.current = false;
    }, SEQUENCE_MS + 50);
  }, []);

  /* Main interval */
  useEffect(() => {
    if (allArticles.length <= HERO_SLOTS) return;
    intervalRef.current = setInterval(rotate, ROTATION_INTERVAL);
    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
      if (cleanupRef.current) clearTimeout(cleanupRef.current);
    };
  }, [allArticles.length, rotate]);

  /* Pause when tab hidden */
  useEffect(() => {
    function onVisibility() {
      if (document.hidden) {
        if (intervalRef.current) {
          clearInterval(intervalRef.current);
          intervalRef.current = null;
        }
      } else if (allArticlesRef.current.length > HERO_SLOTS && !intervalRef.current) {
        intervalRef.current = setInterval(rotate, ROTATION_INTERVAL);
      }
    }
    document.addEventListener('visibilitychange', onVisibility);
    return () => document.removeEventListener('visibilitychange', onVisibility);
  }, [rotate]);

  /* ---- Derive slots ---- */
  const hero = articles[0];
  const secondary = articles.slice(1, 3);
  const small = articles.slice(3, 7);

  const prevHero = prevArticles?.[0];
  const prevSecondary = prevArticles?.slice(1, 3) ?? [];
  const prevSmall = prevArticles?.slice(3, 7) ?? [];

  if (!hero) return null;

  let slot = 0;

  return (
    <>
      <style>{KEYFRAMES_CSS}</style>

      <div className="w-full">
        {/* ---- Top Row ---- */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-1">
          {/* Hero */}
          <CrossfadeSlot
            index={slot++}
            crossfading={crossfading}
            pinned={!!hero.badge}
            previous={
              prevHero && (
                <OverlayCard
                  article={prevHero}
                  locale={locale}
                  thumbnailProfile="hero_big"
                  sizes="(max-width: 768px) 100vw, (max-width: 1024px) 100vw, 58vw"
                  headingLevel={2}
                  showExcerpt
                  headlineClass="text-2xl md:text-3xl lg:text-4xl"
                  className="h-full rounded-[var(--radius-card)] lg:rounded-none lg:rounded-tl-[var(--radius-card)]"
                />
              )
            }
            current={
              <OverlayCard
                article={hero}
                locale={locale}
                priority
                thumbnailProfile="hero_big"
                sizes="(max-width: 768px) 100vw, (max-width: 1024px) 100vw, 58vw"
                headingLevel={2}
                showExcerpt
                headlineClass="text-2xl md:text-3xl lg:text-4xl"
                className="h-full rounded-[var(--radius-card)] lg:rounded-none lg:rounded-tl-[var(--radius-card)]"
              />
            }
            className="md:col-span-2 lg:col-span-7 lg:row-span-2 min-h-[280px] md:min-h-[360px] lg:min-h-[480px]"
          />

          {/* Secondary */}
          {secondary.map((article, i) => {
            const si = slot++;
            const roundClass =
              i === 0
                ? 'rounded-[var(--radius-card)] lg:rounded-none lg:rounded-tr-[var(--radius-card)]'
                : 'rounded-[var(--radius-card)] lg:rounded-none';
            return (
              <CrossfadeSlot
                key={`sec-${i}`}
                index={si}
                crossfading={crossfading}
                pinned={!!article.badge}
                previous={
                  prevSecondary[i] && (
                    <OverlayCard
                      article={prevSecondary[i]}
                      locale={locale}
                      thumbnailProfile="hero_small"
                      sizes="(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 42vw"
                      headlineClass="text-lg lg:text-xl"
                      className={`h-full ${roundClass}`}
                    />
                  )
                }
                current={
                  <OverlayCard
                    article={article}
                    locale={locale}
                    thumbnailProfile="hero_small"
                    sizes="(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 42vw"
                    headlineClass="text-lg lg:text-xl"
                    className={`h-full ${roundClass}`}
                  />
                }
                className="min-h-[180px] md:min-h-[180px] lg:min-h-0 lg:col-span-5"
              />
            );
          })}
        </div>

        {/* ---- Bottom Row ---- */}
        {small.length > 0 && (
          <div className="grid grid-cols-2 md:grid-cols-4 gap-1 mt-1">
            {small.map((article, i) => {
              const si = slot++;
              const roundClass =
                i === 0
                  ? 'lg:rounded-bl-[var(--radius-card)]'
                  : i === small.length - 1
                    ? 'lg:rounded-br-[var(--radius-card)]'
                    : '';
              return (
                <CrossfadeSlot
                  key={`sm-${i}`}
                  index={si}
                  crossfading={crossfading}
                  pinned={!!article.badge}
                  previous={
                    prevSmall[i] && (
                      <OverlayCard
                        article={prevSmall[i]}
                        locale={locale}
                        thumbnailProfile="card_medium"
                        sizes="(max-width: 768px) 50vw, 25vw"
                        headlineClass="text-sm md:text-base lg:text-lg"
                        className={`h-full rounded-[var(--radius-card)] lg:rounded-none ${roundClass}`}
                      />
                    )
                  }
                  current={
                    <OverlayCard
                      article={article}
                      locale={locale}
                      thumbnailProfile="card_medium"
                      sizes="(max-width: 768px) 50vw, 25vw"
                      headlineClass="text-sm md:text-base lg:text-lg"
                      className={`h-full rounded-[var(--radius-card)] lg:rounded-none ${roundClass}`}
                    />
                  }
                  className="min-h-[160px] md:min-h-[200px]"
                />
              );
            })}
          </div>
        )}
      </div>
    </>
  );
}
