import type { Metadata } from 'next'
import NewsSlider from './components/NewsSlider';
import ImportantList from "./components/home/important";
import LatestNews from "./components/home/latest-news";
import CategorySection from '@/components/CategorySection';
import { TrendingArticles } from '@/components/public/TrendingArticles';
import { fetchFrontPageCategories } from '@/lib/api/categories';
import { generateHomepageMetadata } from '@/lib/seo/meta-tags';

type Locale = 'ro' | 'en' | 'ru';

// Generate dynamic metadata based on locale
export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { locale } = await params;
  const validLocale = (['ro', 'en', 'ru'].includes(locale) ? locale : 'ro') as Locale;
  return generateHomepageMetadata(validLocale);
}

// Homepage uses dynamic metadata based on locale - see generateMetadata below
// Static metadata removed to allow dynamic generation

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
