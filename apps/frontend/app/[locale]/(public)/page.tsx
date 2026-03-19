import type { Metadata } from 'next'
import dynamic from 'next/dynamic';
import ImportantList from "./components/home/important";
import LatestNews from "./components/home/latest-news";
import CategorySection from '@/components/CategorySection';
import { fetchFrontPageCategories } from '@/lib/api/categories';
import { fetchLatestArticles } from '@/lib/api/articles';
import { generateHomepageMetadata } from '@/lib/seo/meta-tags';
import type { Article } from '@/lib/types/article';

// Lazy load non-critical components for better initial load performance
const VideoShowsSlider = dynamic(() => import('@/components/video/VideoShowsSlider').then(mod => ({ default: mod.VideoShowsSlider })), {
  loading: () => <div className="h-96 bg-slate-900 animate-pulse" />,
});

const TrendingArticles = dynamic(() => import('@/components/public/TrendingArticles').then(mod => ({ default: mod.TrendingArticles })), {
  loading: () => <div className="h-64 bg-gray-50 animate-pulse my-12" />,
});

const BreakingNewsTicker = dynamic(() => import('@/components/public/BreakingNewsTicker').then(mod => ({ default: mod.BreakingNewsTicker })), {
  loading: () => null,
});

type Locale = 'ro' | 'en' | 'ru';

// Generate dynamic metadata based on locale
export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { locale } = await params;
  const validLocale = (['ro', 'en', 'ru'].includes(locale) ? locale : 'ro') as Locale;
  return generateHomepageMetadata(validLocale);
}

// Enable ISR (Incremental Static Regeneration) with 60-second revalidation
export const revalidate = 60;

interface PageProps {
  params: Promise<{ locale: string }>
}

export default async function HomePage({ params }: PageProps) {
  const { locale: localeParam } = await params
  const locale = localeParam as Locale

  // Fetch categories and breaking news in parallel
  let frontPageCategories: any[] = [];
  let breakingArticles: Article[] = [];

  const [categoriesResult, breakingResult] = await Promise.allSettled([
    fetchFrontPageCategories(locale),
    fetchLatestArticles(locale, 3), // Use latest for breaking news ticker
  ]);

  if (categoriesResult.status === 'fulfilled') {
    frontPageCategories = (categoriesResult.value.member || []).filter(cat => cat.onFrontPage === true);
  }
  if (breakingResult.status === 'fulfilled') {
    breakingArticles = breakingResult.value.member || [];
  }

  // Fetch special articles (breaking, alert, flash)
  let specialArticles: SpecialArticle[] = [];
  try {
    const articles = await fetchAllSpecialArticles(locale, 3);
    // Transform to SpecialArticle format
    specialArticles = articles
      .filter(article => article.badge)
      .map(article => ({
        id: article.id,
        title: article.title,
        slug: article.slug,
        lead: article.lead,
        badge: article.badge as 'breaking' | 'alert' | 'flash',
        category: article.category as any,
        authors: article.authors as any,
        articleImages: article.articleImages as any,
        publishedAt: article.publishedAt,
      }));
  } catch (error) {
    console.error('Failed to fetch special articles:', error);
    specialArticles = [];
  }

  // Fetch YouTube videos for homepage slider
  let homepageVideos: YouTubeVideo[] = [];
  let videoShows: VideoShow[] = [];
  try {
    const [videosResponse, showsResponse] = await Promise.all([
      fetchHomepageVideos(12, locale),
      fetchVideoShows(locale),
    ]);
    homepageVideos = videosResponse.member || [];
    videoShows = showsResponse.member || [];
  } catch (error) {
    console.error('Failed to fetch homepage videos:', error);
    homepageVideos = [];
    videoShows = [];
  }

  // H1 titles per locale for SEO
  const h1Titles: Record<string, string> = {
    ro: 'Deschide News - Știri de Ultimă Oră din Moldova și din Lume',
    en: 'Deschide News - Breaking News from Moldova and Worldwide',
    ru: 'Deschide News - Последние Новости из Молдовы и Мира',
  };

  return (
    <>
      {/* SEO H1 - visually hidden but present for search engines */}
      <h1 className="sr-only">{h1Titles[locale] || h1Titles.ro}</h1>

      {/* Breaking News Ticker */}
      <BreakingNewsTicker locale={locale} articles={breakingArticles} />

      {/* Hero / Important Articles Section */}
      <ImportantList locale={locale} />

      {/* Live Broadcasts Section - Shows only when there are live LiveTexts */}
      <LiveTextHomepage locale={locale} />

      {/* Latest News Section */}
      <LatestNews locale={locale} />

      {/* Trending Articles Section */}
      <TrendingArticles locale={locale as Locale} limit={5} />

      {/* Video Emissions Slider - Shows only when videos are available */}
      {homepageVideos.length > 0 && (
        <VideoShowsSlider
          videos={homepageVideos}
          videoShows={videoShows}
          locale={locale}
        />
      )}

      {/* Dynamic Category Sections - Only categories with onFrontPage=true */}
      {frontPageCategories.map((category) => (
        <div key={category.id}>
          <CategorySection category={category} locale={locale} />
        </div>
      ))}
    </>
  )
}
