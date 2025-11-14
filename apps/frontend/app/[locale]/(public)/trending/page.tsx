import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import ArticleCard from '@/components/article/ArticleCard';
import type { Locale } from '@/lib/types';

interface TrendingPageProps {
  params: Promise<{
    locale: string;
  }>;
  searchParams: Promise<{
    period?: 'today' | 'week' | 'month';
  }>;
}

/**
 * Trending Articles Page
 *
 * Displays trending/popular articles based on view count and engagement
 * Supports filtering by time period (today, week, month)
 */
export default async function TrendingPage({
  params,
  searchParams,
}: TrendingPageProps) {
  const { locale } = await params;
  const { period = 'week' } = await searchParams;

  const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

  try {
    // Calculate date filter based on period
    const now = new Date();
    let publishedAfter: Date;

    switch (period) {
      case 'today':
        publishedAfter = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        break;
      case 'month':
        publishedAfter = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate());
        break;
      case 'week':
      default:
        publishedAfter = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 7);
        break;
    }

    const queryParams = new URLSearchParams({
      status: 'published',
      'publishedAt[after]': publishedAfter.toISOString(),
      'order[viewCount]': 'DESC', // Assuming viewCount exists
      itemsPerPage: '30',
    });

    const response = await fetch(
      `${apiUrl}/api/articles?${queryParams.toString()}`,
      {
        headers: {
          'Accept': 'application/ld+json',
          'Accept-Language': locale,
        },
        next: { revalidate: 600 }, // Revalidate every 10 minutes
      }
    );

    if (!response.ok) {
      throw new Error(`Failed to fetch trending articles: ${response.status}`);
    }

    const data = await response.json();
    const articles = data['hydra:member'] || [];

    const texts = {
      ro: {
        title: 'Articole Populare',
        subtitle: 'Cele mai citite articole',
        today: 'Astăzi',
        week: 'Săptămâna aceasta',
        month: 'Luna aceasta',
        noArticles: 'Nu există articole populare în această perioadă.',
        viewCount: 'vizualizări',
      },
      en: {
        title: 'Trending Articles',
        subtitle: 'Most read articles',
        today: 'Today',
        week: 'This Week',
        month: 'This Month',
        noArticles: 'No trending articles in this period.',
        viewCount: 'views',
      },
      ru: {
        title: 'Популярные Статьи',
        subtitle: 'Самые читаемые статьи',
        today: 'Сегодня',
        week: 'На этой неделе',
        month: 'В этом месяце',
        noArticles: 'Нет популярных статей в этом периоде.',
        viewCount: 'просмотров',
      },
    };

    const t = texts[locale as keyof typeof texts] || texts.ro;

    return (
      <div className="container mx-auto px-4 py-8">
        {/* Page Header */}
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-4">
            {t.title}
          </h1>
          <p className="text-gray-600 dark:text-gray-400">{t.subtitle}</p>
        </div>

        {/* Period Filter */}
        <div className="mb-6">
          <div className="flex flex-wrap gap-2">
            <a
              href={`/${locale}/trending?period=today`}
              className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                period === 'today'
                  ? 'bg-red-600 text-white'
                  : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
              }`}
            >
              {t.today}
            </a>
            <a
              href={`/${locale}/trending?period=week`}
              className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                period === 'week'
                  ? 'bg-red-600 text-white'
                  : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
              }`}
            >
              {t.week}
            </a>
            <a
              href={`/${locale}/trending?period=month`}
              className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                period === 'month'
                  ? 'bg-red-600 text-white'
                  : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
              }`}
            >
              {t.month}
            </a>
          </div>
        </div>

        {/* Trending Articles Grid */}
        {articles.length > 0 ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {articles.map((article: any, index: number) => (
              <div key={article.id} className="relative">
                {/* Trending Badge */}
                {index < 3 && (
                  <div className="absolute top-4 left-4 z-10">
                    <div
                      className={`w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-lg ${
                        index === 0
                          ? 'bg-yellow-500'
                          : index === 1
                            ? 'bg-gray-400'
                            : 'bg-orange-600'
                      }`}
                    >
                      {index + 1}
                    </div>
                  </div>
                )}
                <ArticleCard article={article} locale={locale as Locale} />
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-12">
            <p className="text-gray-600 dark:text-gray-400 text-lg">
              {t.noArticles}
            </p>
          </div>
        )}
      </div>
    );
  } catch (error) {
    console.error('Error fetching trending articles:', error);
    notFound();
  }
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({
  params,
  searchParams,
}: TrendingPageProps): Promise<Metadata> {
  const { locale } = await params;
  const { period = 'week' } = await searchParams;

  const titles = {
    ro: {
      today: 'Articole Populare Astăzi',
      week: 'Articole Populare Săptămâna Aceasta',
      month: 'Articole Populare Luna Aceasta',
    },
    en: {
      today: 'Trending Articles Today',
      week: 'Trending Articles This Week',
      month: 'Trending Articles This Month',
    },
    ru: {
      today: 'Популярные Статьи Сегодня',
      week: 'Популярные Статьи На Этой Неделе',
      month: 'Популярные Статьи В Этом Месяце',
    },
  };

  const descriptions = {
    ro: 'Descoperiți cele mai citite și populare articole de pe Deschide News.',
    en: 'Discover the most read and popular articles on Deschide News.',
    ru: 'Откройте для себя самые читаемые и популярные статьи на Deschide News.',
  };

  const titleTexts = titles[locale as keyof typeof titles] || titles.ro;
  const title = titleTexts[period as keyof typeof titleTexts];
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/trending`,
      languages: {
        ro: '/trending',
        en: '/en/trending',
        ru: '/ru/trending',
      },
    },
    openGraph: {
      title,
      description,
      locale: locale === 'ro' ? 'ro_RO' : locale === 'en' ? 'en_US' : 'ru_RU',
      type: 'website',
    },
  };
}

/**
 * Revalidate every 10 minutes
 */
export const revalidate = 600;
