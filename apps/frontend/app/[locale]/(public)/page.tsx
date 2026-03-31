/**
 * Homepage - Deschide News App
 * TailNews-inspired layout: Hero full-width, then 8+4 main+sidebar grid
 */

import type { Metadata } from 'next'
import dynamic from 'next/dynamic';
import { fetchFrontPageCategories } from '@/lib/api/categories';
import { fetchLatestArticles } from '@/lib/api/articles';
import { fetchImportantArticles } from '@/lib/api/important-articles';
import { getTrendingArticles } from '@/lib/api/statistics';
import { generateHomepageMetadata } from '@/lib/seo/meta-tags';
import { fetchHomepageVideos, fetchVideoShows } from '@/lib/api/video-shows';
import type { YouTubeVideo, VideoShow } from '@/lib/types/video';
import type { Article, ImportantArticle } from '@/lib/types/article';
import type { Locale } from '@/lib/types';

// Section components
import HeroSection from './components/home/HeroSection';
import CategorySection from './components/home/CategorySection';
import type { CategorySectionLayout } from './components/home/CategorySection';
import HomepageSidebar from './components/home/HomepageSidebar';
import OpinionSection from './components/home/OpinionSection';
import LatestNewsSection from './components/home/LatestNewsSection';
import NewsletterCTA from './components/home/NewsletterCTA';
import TelegramCTA from './components/home/TelegramCTA';
import DRRMBanner from '@/components/banners/DRRMBanner';
import LiveRefresh from './components/home/LiveRefresh';

// ============================================================
// TEMPORAR DEZACTIVAT: Secțiunea Transmisiuni Live
// Motiv: Redesign în curs, va fi reactivată ulterior
// Data dezactivării: 2026-03-25
// Componente păstrate: components/live/LiveTextHomepage.tsx
// ============================================================
// import { LiveTextHomepage } from '@/components/live';

// Lazy load non-critical components
const VideoShowsSlider = dynamic(
  () => import('@/components/video/VideoShowsSlider').then(mod => ({ default: mod.VideoShowsSlider })),
  { loading: () => <div className="h-96 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] animate-pulse rounded-[var(--radius-card)]" /> },
);

const BreakingNewsTicker = dynamic(
  () => import('@/components/public/BreakingNewsTicker').then(mod => ({ default: mod.BreakingNewsTicker })),
  { loading: () => null },
);

// Cycle through layout variants for visual rhythm
const LAYOUT_CYCLE: CategorySectionLayout[] = [
  'featured-grid',  // D — most visually impactful, first category
  'grid-3col',      // A — classic 3-col grid
  'compact-list',   // B — dense compact rows
  'grid-4col',      // C — 4-col wide grid
];

// Generate dynamic metadata based on locale
export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { locale } = await params;
  const validLocale = (['ro', 'en', 'ru'].includes(locale) ? locale : 'ro') as Locale;
  return generateHomepageMetadata(validLocale);
}

// Tag-based revalidation — homepage refreshes on-demand via /api/revalidate-articles
export const revalidate = false;

interface PageProps {
  params: Promise<{ locale: string }>
}

export default async function HomePage({ params }: PageProps) {
  const { locale: localeParam } = await params
  const locale = localeParam as Locale

  // Fetch all homepage data in parallel
  let frontPageCategories: any[] = [];
  let homepageVideos: YouTubeVideo[] = [];
  let videoShows: VideoShow[] = [];
  let latestArticles: Article[] = [];
  let importantArticles: ImportantArticle[] = [];
  let trendingArticles: any[] = [];

  const [
    categoriesResult,
    videosResult,
    showsResult,
    latestResult,
    importantResult,
    trendingResult,
  ] = await Promise.allSettled([
    fetchFrontPageCategories(locale),
    fetchHomepageVideos(12, locale),
    fetchVideoShows(locale),
    fetchLatestArticles(locale, 18),
    fetchImportantArticles(locale),
    getTrendingArticles(10, locale),
  ]);

  if (categoriesResult.status === 'fulfilled') {
    frontPageCategories = (categoriesResult.value.member || []).filter(
      (cat: any) => cat.onFrontPage === true
    );
  }

  if (videosResult.status === 'fulfilled') {
    homepageVideos = videosResult.value.member || [];
  }

  if (showsResult.status === 'fulfilled') {
    videoShows = showsResult.value.member || [];
  }

  if (latestResult.status === 'fulfilled') {
    latestArticles = latestResult.value.member || [];
  }

  if (importantResult.status === 'fulfilled') {
    importantArticles = importantResult.value.member || [];
  }

  if (trendingResult.status === 'fulfilled') {
    trendingArticles = trendingResult.value || [];
  }

  // Separate "Opinii" category from other front-page categories.
  // OpinionSection has a dedicated layout; exclude it from the generic loop.
  const opiniiCategory = frontPageCategories.find(
    (cat: any) => cat.slug === 'opinii'
  );
  const categoriesForSections = frontPageCategories.filter(
    (cat: any) => cat.slug !== 'opinii'
  );

  // Deduplicate: compute IDs used by HeroSection (important articles + backfill)
  // Must mirror HeroSection logic: badged first, then rest, backfill from latest only if < 7
  const heroUsedIds = new Set<number>();
  const extracted = importantArticles.map((item) => item.article).filter(Boolean);
  const badged = extracted.filter((a: Article) => a.badge);
  const rest = extracted.filter((a: Article) => !a.badge);
  const heroArticles = [...badged, ...rest].slice(0, 7);
  heroArticles.forEach((a) => {
    if (a.id) heroUsedIds.add(a.id);
  });
  // Only add latest articles if hero needs backfill (< 7 important)
  if (heroArticles.length < 7) {
    const backfillNeeded = 7 - heroArticles.length;
    latestArticles
      .filter((a) => !heroUsedIds.has(a.id))
      .slice(0, backfillNeeded)
      .forEach((a) => {
        if (a.id) heroUsedIds.add(a.id);
      });
  }

  // Filter latest articles excluding hero IDs, take first 8
  const latestForSection = latestArticles
    .filter((a) => !heroUsedIds.has(a.id))
    .slice(0, 8);

  // H1 titles per locale for SEO
  const h1Titles: Record<string, string> = {
    ro: 'Deschide News - Știri de Ultimă Oră din Moldova și din Lume',
    en: 'Deschide News - Breaking News from Moldova and Worldwide',
    ru: 'Deschide News - Последние Новости из Молдовы и Мира',
  };

  return (
    <>
      {/* SEO H1 - visually hidden */}
      <h1 className="sr-only">{h1Titles[locale] || h1Titles.ro}</h1>

      {/* Live refresh: listens for Mercure SSE events, auto-refreshes on new published articles */}
      <LiveRefresh />

      {/* Breaking News Ticker */}
      <BreakingNewsTicker locale={locale} articles={[]} />

      {/* TEMPORAR DEZACTIVAT — Transmisiuni Live — vezi comentariul de import de mai sus
      <LiveTextHomepage locale={locale} />
      */}

      {/* ============================================================ */}
      {/*  MAIN HOMEPAGE CONTAINER                                      */}
      {/* ============================================================ */}
      <div className="bg-[var(--color-surface)] dark:bg-[var(--color-surface-dark)] min-h-screen">
        <div className="max-w-[1440px] mx-auto px-4 lg:px-6">

          {/* ── Hero Zone (full-width) ── */}
          <section className="pt-6 pb-10">
            <HeroSection locale={locale} />
          </section>

          {/* ── DRRM Partnership Banner ── */}
          <section className="pb-10">
            <DRRMBanner />
          </section>

          {/* ── Latest News Section (Featured + Grid + Sidebar) ── */}
          {latestForSection.length > 0 && (
            <LatestNewsSection
              articles={latestForSection}
              popularArticles={trendingArticles}
              locale={locale}
            />
          )}

          {/* ── Opinions — NYT-style editorial section (full-width) ── */}
          {opiniiCategory && (
            <OpinionSection locale={locale} categoryId={opiniiCategory.id} />
          )}

          {/* ── Main + Sidebar (8 + 4 columns) ── */}
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 pb-12">

            {/* Main content column */}
            <div className="lg:col-span-8 space-y-12">

              {/* Category sections with alternating layouts */}
              {categoriesForSections.map((category, index) => (
                <CategorySection
                  key={category.id}
                  category={category}
                  locale={locale}
                  layout={LAYOUT_CYCLE[index % LAYOUT_CYCLE.length]}
                />
              ))}

              {/* Video section (full-width within main column) */}
              {homepageVideos.length > 0 && (
                <section>
                  <div className="flex items-center gap-4 mb-6">
                    <h2
                      className="font-sans font-bold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] whitespace-nowrap border-b-2 border-[var(--color-section-tech)] pb-1"
                      style={{ fontSize: 'var(--font-size-2xl)' }}
                    >
                      {locale === 'ru' ? 'Видео' : locale === 'en' ? 'Video' : 'Video'}
                    </h2>
                    <div className="flex-1 h-px bg-[var(--color-border)] dark:bg-[var(--color-border-dark)]" />
                  </div>
                  <VideoShowsSlider
                    videos={homepageVideos}
                    videoShows={videoShows}
                    locale={locale}
                  />
                </section>
              )}

            </div>

            {/* Sidebar column */}
            <div className="lg:col-span-4">
              <HomepageSidebar locale={locale} />
            </div>

          </div>

          {/* ── Full-width CTAs (below main+sidebar grid) ── */}
          <div className="space-y-12 pb-16">
            <NewsletterCTA locale={locale} />
            <TelegramCTA locale={locale} />
          </div>

        </div>
      </div>
    </>
  )
}
