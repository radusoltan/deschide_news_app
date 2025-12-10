import type { Metadata } from 'next'
import dynamic from 'next/dynamic';
import ImportantList from "./components/home/important";
import LatestNews from "./components/home/latest-news";
import CategorySection from '@/components/CategorySection';
import { fetchFrontPageCategories } from '@/lib/api/categories';
import { generateHomepageMetadata } from '@/lib/seo/meta-tags';

// Lazy load non-critical components for better initial load performance
const NewsSlider = dynamic(() => import('./components/NewsSlider'), {
  loading: () => <div className="h-96 bg-gray-100 animate-pulse" />,
});

const TrendingArticles = dynamic(() => import('@/components/public/TrendingArticles').then(mod => ({ default: mod.TrendingArticles })), {
  loading: () => <div className="h-64 bg-gray-50 animate-pulse my-12" />,
});

type Locale = 'ro' | 'en' | 'ru';

// Generate dynamic metadata based on locale
export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { locale } = await params;
  const validLocale = (['ro', 'en', 'ru'].includes(locale) ? locale : 'ro') as Locale;
  return generateHomepageMetadata(validLocale);
}

// Homepage uses dynamic metadata based on locale - see generateMetadata below
// Static metadata removed to allow dynamic generation

// Enable ISR (Incremental Static Regeneration) with 60-second revalidation
export const revalidate = 60;

interface PageProps {
  params: Promise<{ locale: string }>
}

export default async function HomePage({ params }: PageProps) {
  const { locale } = await params

  // Fetch categories that should appear on front page
  let frontPageCategories: any[] = [];
  try {
    const response = await fetchFrontPageCategories(locale);
    // Filter only categories with onFrontPage=true (in case API doesn't filter)
    frontPageCategories = (response.member || []).filter(cat => cat.onFrontPage === true);
  } catch (error) {
    console.error('Failed to fetch front page categories:', error);
    frontPageCategories = [];
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

      {/* Hero / Important Articles Section */}
      <ImportantList locale={locale} />

      {/* Latest News Section */}
      <LatestNews locale={locale} />

      {/* Trending Articles Section */}
      <TrendingArticles locale={locale} limit={5} />

      {/* Dynamic Category Sections - Only categories with onFrontPage=true */}
      {frontPageCategories.map((category, index) => (
        <div key={category.id}>
          <CategorySection category={category} locale={locale} />

          {/* Insert Slider News Section after first category */}
          {index === 0 && (
            <NewsSlider
              title="American"
              backgroundImage="https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1920&h=1080&fit=crop"
              locale={locale}
            />
          )}
        </div>
      ))}
    </>
  )
}
