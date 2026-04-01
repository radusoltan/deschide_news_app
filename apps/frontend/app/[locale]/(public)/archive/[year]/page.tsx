import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import Link from 'next/link';
import ArticleCard from '@/components/article/ArticleCard';
import type { Locale } from '@/lib/types';

interface YearArchivePageProps {
  params: Promise<{
    locale: string;
    year: string;
  }>;
}

/**
 * Year Archive Page
 *
 * Displays articles from a specific year, grouped by month
 * Shows monthly breakdown and article count per month
 */
export default async function YearArchivePage({ params }: YearArchivePageProps) {
  const { locale, year } = await params;

  // Validate year
  const yearNum = parseInt(year, 10);
  const currentYear = new Date().getFullYear();

  if (isNaN(yearNum) || yearNum < 2000 || yearNum > currentYear) {
    notFound();
  }

  const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? '';

  try {
    // Fetch articles for the year
    const startDate = new Date(yearNum, 0, 1).toISOString();
    const endDate = new Date(yearNum, 11, 31, 23, 59, 59).toISOString();

    const queryParams = new URLSearchParams({
      status: 'published',
      'publishedAt[after]': startDate,
      'publishedAt[before]': endDate,
      itemsPerPage: '1000',
      'order[publishedAt]': 'DESC',
    });

    const response = await fetch(`${apiUrl}/api/articles?${queryParams.toString()}`, {
      headers: {
        'Accept': 'application/ld+json',
        'Accept-Language': locale,
      },
      next: { revalidate: 3600 }, // Revalidate every hour
    });

    if (!response.ok) {
      throw new Error(`Failed to fetch articles: ${response.status}`);
    }

    const data = await response.json();
    const articles = data['hydra:member'] || [];

    // Group articles by month
    const monthMap = new Map<number, any[]>();

    articles.forEach((article: any) => {
      if (article.publishedAt) {
        const month = new Date(article.publishedAt).getMonth();
        if (!monthMap.has(month)) {
          monthMap.set(month, []);
        }
        monthMap.get(month)!.push(article);
      }
    });

    // Convert to array and sort descending
    const months = Array.from(monthMap.entries())
      .map(([month, articles]) => ({ month, articles, count: articles.length }))
      .sort((a, b) => b.month - a.month);

    const monthNames = {
      ro: [
        'Ianuarie',
        'Februarie',
        'Martie',
        'Aprilie',
        'Mai',
        'Iunie',
        'Iulie',
        'August',
        'Septembrie',
        'Octombrie',
        'Noiembrie',
        'Decembrie',
      ],
      en: [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
      ],
      ru: [
        'Январь',
        'Февраль',
        'Март',
        'Апрель',
        'Май',
        'Июнь',
        'Июль',
        'Август',
        'Сентябрь',
        'Октябрь',
        'Ноябрь',
        'Декабрь',
      ],
    };

    const texts = {
      ro: {
        title: `Arhivă ${yearNum}`,
        subtitle: 'Articole publicate în',
        backToArchive: '← Înapoi la arhivă',
        articlesCount: 'articole',
        viewAll: 'Vezi toate articolele din',
        noArticles: `Nu există articole publicate în ${yearNum}.`,
        totalArticles: 'Total articole',
      },
      en: {
        title: `${yearNum} Archive`,
        subtitle: 'Articles published in',
        backToArchive: '← Back to archive',
        articlesCount: 'articles',
        viewAll: 'View all articles from',
        noArticles: `No articles published in ${yearNum}.`,
        totalArticles: 'Total articles',
      },
      ru: {
        title: `Архив ${yearNum}`,
        subtitle: 'Статьи опубликованные в',
        backToArchive: '← Назад к архиву',
        articlesCount: 'статей',
        viewAll: 'Просмотреть все статьи из',
        noArticles: `Нет статей, опубликованных в ${yearNum}.`,
        totalArticles: 'Всего статей',
      },
    };

    const t = texts[locale as keyof typeof texts] || texts.ro;
    const monthNamesLoc = monthNames[locale as keyof typeof monthNames] || monthNames.ro;

    return (
      <div className="container mx-auto px-4 py-8">
        {/* Breadcrumb */}
        <div className="mb-6">
          <Link
            href={`/${locale}/archive`}
            className="text-brand-tomato-500 hover:text-brand-tomato-600 font-medium"
          >
            {t.backToArchive}
          </Link>
        </div>

        {/* Page Header */}
        <div className="mb-12">
          <h1 className="text-4xl font-bold text-primary dark:text-primary-dark mb-4">
            {t.title}
          </h1>
          <p className="text-gray-600 dark:text-gray-400 text-lg">
            {articles.length} {t.articlesCount} {t.subtitle.toLowerCase()} {yearNum}
          </p>
        </div>

        {/* Content */}
        {articles.length > 0 ? (
          <div className="space-y-12">
            {/* Month Sections */}
            {months.map(({ month, articles: monthArticles, count }) => (
              <section key={month} id={`month-${month + 1}`}>
                {/* Month Header */}
                <div className="flex items-center justify-between mb-6 pb-4 border-b-2 border-gray-200 dark:border-gray-700">
                  <h2 className="text-3xl font-bold text-primary dark:text-primary-dark">
                    {monthNamesLoc[month]} {yearNum}
                  </h2>
                  <div className="flex items-center gap-4">
                    <span className="text-gray-600 dark:text-gray-400">
                      {count} {t.articlesCount}
                    </span>
                    <Link
                      href={`/${locale}/archive/${yearNum}/${month + 1}`}
                      className="text-brand-tomato-500 hover:text-brand-tomato-600 font-medium text-sm"
                    >
                      {t.viewAll} →
                    </Link>
                  </div>
                </div>

                {/* Articles Grid - Show first 8 articles */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                  {monthArticles.slice(0, 8).map((article: any) => (
                    <ArticleCard key={article.id} article={article} locale={locale as Locale} />
                  ))}
                </div>
              </section>
            ))}

            {/* Quick Navigation */}
            <div className="bg-gray-100 dark:bg-surface-dark rounded-lg p-6">
              <h3 className="text-lg font-semibold text-primary dark:text-primary-dark mb-4">
                {locale === 'ro' && 'Navigare Rapidă'}
                {locale === 'en' && 'Quick Navigation'}
                {locale === 'ru' && 'Быстрая Навигация'}
              </h3>
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                {months.map(({ month, count }) => (
                  <a
                    key={month}
                    href={`#month-${month + 1}`}
                    className="px-4 py-2 bg-surface dark:bg-gray-700 rounded-lg text-center hover:bg-red-50 dark:hover:bg-gray-600 transition-colors"
                  >
                    <div className="font-semibold text-primary dark:text-primary-dark text-sm">
                      {monthNamesLoc[month].substring(0, 3)}
                    </div>
                    <div className="text-xs text-secondary dark:text-gray-400">{count}</div>
                  </a>
                ))}
              </div>
            </div>
          </div>
        ) : (
          <div className="text-center py-12">
            <p className="text-gray-600 dark:text-gray-400 text-lg">{t.noArticles}</p>
          </div>
        )}
      </div>
    );
  } catch (error) {
    console.error('Error fetching year archive:', error);
    notFound();
  }
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({ params }: YearArchivePageProps): Promise<Metadata> {
  const { locale, year } = await params;

  const titles = {
    ro: `Arhivă ${year} - Toate Articolele`,
    en: `${year} Archive - All Articles`,
    ru: `Архив ${year} - Все Статьи`,
  };

  const descriptions = {
    ro: `Explorați toate articolele publicate în ${year} pe Deschide News, organizate pe luni.`,
    en: `Explore all articles published in ${year} on Deschide News, organized by month.`,
    ru: `Изучите все статьи, опубликованные в ${year} году на Deschide News, организованные по месяцам.`,
  };

  const title = titles[locale as keyof typeof titles] || titles.ro;
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/archive/${year}`,
      languages: {
        ro: `/archive/${year}`,
        en: `/en/archive/${year}`,
        ru: `/ru/archive/${year}`,
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
 * Revalidate every hour
 */
export const revalidate = 3600;
