import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import Link from 'next/link';
import ArticleCard from '@/components/article/ArticleCard';
import type { Locale } from '@/lib/types';

interface MonthArchivePageProps {
  params: Promise<{
    locale: string;
    year: string;
    month: string;
  }>;
}

/**
 * Month Archive Page
 *
 * Displays all articles from a specific month
 * Shows daily breakdown and full article list
 */
export default async function MonthArchivePage({ params }: MonthArchivePageProps) {
  const { locale, year, month } = await params;

  // Validate year and month
  const yearNum = parseInt(year, 10);
  const monthNum = parseInt(month, 10);
  const currentYear = new Date().getFullYear();

  if (
    isNaN(yearNum) ||
    yearNum < 2000 ||
    yearNum > currentYear ||
    isNaN(monthNum) ||
    monthNum < 1 ||
    monthNum > 12
  ) {
    notFound();
  }

  const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? '';

  try {
    // Fetch articles for the month
    const startDate = new Date(yearNum, monthNum - 1, 1).toISOString();
    const endDate = new Date(yearNum, monthNum, 0, 23, 59, 59).toISOString();

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

    // Group articles by day
    const dayMap = new Map<number, any[]>();

    articles.forEach((article: any) => {
      if (article.publishedAt) {
        const day = new Date(article.publishedAt).getDate();
        if (!dayMap.has(day)) {
          dayMap.set(day, []);
        }
        dayMap.get(day)!.push(article);
      }
    });

    // Convert to array and sort descending
    const days = Array.from(dayMap.entries())
      .map(([day, articles]) => ({ day, articles, count: articles.length }))
      .sort((a, b) => b.day - a.day);

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
        title: 'Arhivă',
        backToYear: `← Înapoi la ${yearNum}`,
        articlesCount: 'articole',
        noArticles: 'Nu există articole publicate în această lună.',
        totalArticles: 'Total articole',
        publishedOn: 'Publicate pe',
      },
      en: {
        title: 'Archive',
        backToYear: `← Back to ${yearNum}`,
        articlesCount: 'articles',
        noArticles: 'No articles published in this month.',
        totalArticles: 'Total articles',
        publishedOn: 'Published on',
      },
      ru: {
        title: 'Архив',
        backToYear: `← Назад к ${yearNum}`,
        articlesCount: 'статей',
        noArticles: 'В этом месяце статей не опубликовано.',
        totalArticles: 'Всего статей',
        publishedOn: 'Опубликовано',
      },
    };

    const t = texts[locale as keyof typeof texts] || texts.ro;
    const monthNamesLoc = monthNames[locale as keyof typeof monthNames] || monthNames.ro;
    const monthName = monthNamesLoc[monthNum - 1];

    return (
      <div className="container mx-auto px-4 py-8">
        {/* Breadcrumb */}
        <div className="mb-6 flex items-center gap-2 text-sm">
          <Link href={`/${locale}/archive`} className="text-brand-tomato-500 hover:text-brand-tomato-600">
            {t.title}
          </Link>
          <span className="text-gray-400">/</span>
          <Link
            href={`/${locale}/archive/${yearNum}`}
            className="text-brand-tomato-500 hover:text-brand-tomato-600 font-medium"
          >
            {t.backToYear}
          </Link>
        </div>

        {/* Page Header */}
        <div className="mb-12">
          <h1 className="text-4xl font-bold text-primary dark:text-primary-dark mb-4">
            {monthName} {yearNum}
          </h1>
          <p className="text-gray-600 dark:text-gray-400 text-lg">
            {articles.length} {t.articlesCount}
          </p>
        </div>

        {/* Content */}
        {articles.length > 0 ? (
          <div className="space-y-10">
            {/* Day Sections */}
            {days.map(({ day, articles: dayArticles }) => (
              <section key={day}>
                {/* Day Header */}
                <div className="mb-6">
                  <h2 className="text-2xl font-bold text-primary dark:text-primary-dark mb-1">
                    {day} {monthName} {yearNum}
                  </h2>
                  <p className="text-gray-600 dark:text-gray-400 text-sm">
                    {dayArticles.length} {t.articlesCount}
                  </p>
                </div>

                {/* Articles Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                  {dayArticles.map((article: any) => (
                    <ArticleCard key={article.id} article={article} locale={locale as Locale} />
                  ))}
                </div>
              </section>
            ))}

            {/* Calendar View */}
            <div className="bg-gray-100 dark:bg-surface-dark rounded-lg p-6">
              <h3 className="text-lg font-semibold text-primary dark:text-primary-dark mb-4">
                {locale === 'ro' && 'Calendar'}
                {locale === 'en' && 'Calendar'}
                {locale === 'ru' && 'Календарь'}
              </h3>
              <div className="grid grid-cols-7 gap-2">
                {/* Calendar days */}
                {Array.from({ length: new Date(yearNum, monthNum, 0).getDate() }, (_, i) => {
                  const day = i + 1;
                  const hasArticles = dayMap.has(day);
                  const count = dayMap.get(day)?.length || 0;

                  return (
                    <div
                      key={day}
                      className={`aspect-square flex flex-col items-center justify-center rounded-lg text-sm ${
                        hasArticles
                          ? 'bg-brand-tomato-500 text-white cursor-pointer hover:bg-brand-tomato-600'
                          : 'bg-gray-200 dark:bg-gray-700 text-gray-400'
                      }`}
                    >
                      <div className="font-bold">{day}</div>
                      {hasArticles && <div className="text-xs">{count}</div>}
                    </div>
                  );
                })}
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
    console.error('Error fetching month archive:', error);
    notFound();
  }
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({
  params,
}: MonthArchivePageProps): Promise<Metadata> {
  const { locale, year, month } = await params;

  const monthNum = parseInt(month, 10);

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

  const monthNamesLoc = monthNames[locale as keyof typeof monthNames] || monthNames.ro;
  const monthName = monthNamesLoc[monthNum - 1];

  const titles = {
    ro: `Arhivă ${monthName} ${year}`,
    en: `${monthName} ${year} Archive`,
    ru: `Архив ${monthName} ${year}`,
  };

  const descriptions = {
    ro: `Toate articolele publicate în ${monthName} ${year} pe Deschide News.`,
    en: `All articles published in ${monthName} ${year} on Deschide News.`,
    ru: `Все статьи, опубликованные в ${monthName} ${year} на Deschide News.`,
  };

  const title = titles[locale as keyof typeof titles] || titles.ro;
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/archive/${year}/${month}`,
      languages: {
        ro: `/archive/${year}/${month}`,
        en: `/en/archive/${year}/${month}`,
        ru: `/ru/archive/${year}/${month}`,
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
