import { Metadata } from 'next';
import Link from 'next/link';

interface ArchivePageProps {
  params: Promise<{
    locale: string;
  }>;
}

/**
 * Archive Root Page
 *
 * Displays list of years with published articles
 * Entry point to the archive system
 */
export default async function ArchivePage({ params }: ArchivePageProps) {
  const { locale } = await params;

  const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

  try {
    // Fetch articles to get available years
    const response = await fetch(`${apiUrl}/api/articles?itemsPerPage=1000&status=published`, {
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

    // Group articles by year
    const yearMap = new Map<number, number>();

    articles.forEach((article: any) => {
      if (article.publishedAt) {
        const year = new Date(article.publishedAt).getFullYear();
        yearMap.set(year, (yearMap.get(year) || 0) + 1);
      }
    });

    // Convert to array and sort descending
    const years = Array.from(yearMap.entries())
      .map(([year, count]) => ({ year, count }))
      .sort((a, b) => b.year - a.year);

    const texts = {
      ro: {
        title: 'Arhivă',
        subtitle: 'Explorați articolele noastre pe ani',
        articlesCount: 'articole',
        noArchive: 'Nu există articole arhivate încă.',
      },
      en: {
        title: 'Archive',
        subtitle: 'Explore our articles by year',
        articlesCount: 'articles',
        noArchive: 'No archived articles yet.',
      },
      ru: {
        title: 'Архив',
        subtitle: 'Изучите наши статьи по годам',
        articlesCount: 'статей',
        noArchive: 'Архивированных статей пока нет.',
      },
    };

    const t = texts[locale as keyof typeof texts] || texts.ro;

    return (
      <div className="container mx-auto px-4 py-8">
        {/* Page Header */}
        <div className="mb-12">
          <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-4">
            {t.title}
          </h1>
          <p className="text-gray-600 dark:text-gray-400 text-lg">
            {t.subtitle}
          </p>
        </div>

        {/* Years Grid */}
        {years.length > 0 ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {years.map(({ year, count }) => (
              <Link
                key={year}
                href={`/${locale}/archive/${year}`}
                className="group block bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-xl transition-all p-8 text-center border-2 border-transparent hover:border-red-600"
              >
                <div className="text-5xl font-bold text-gray-900 dark:text-white group-hover:text-red-600 transition-colors mb-4">
                  {year}
                </div>
                <div className="text-gray-600 dark:text-gray-400 text-sm">
                  {count} {t.articlesCount}
                </div>
              </Link>
            ))}
          </div>
        ) : (
          <div className="text-center py-12">
            <p className="text-gray-600 dark:text-gray-400 text-lg">
              {t.noArchive}
            </p>
          </div>
        )}

        {/* Archive Info */}
        {years.length > 0 && (
          <div className="mt-12 bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-600 p-6 rounded">
            <div className="flex items-start">
              <svg
                className="w-6 h-6 text-blue-600 mt-0.5 mr-3"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              <div>
                <h3 className="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">
                  {locale === 'ro' && 'Despre Arhivă'}
                  {locale === 'en' && 'About Archive'}
                  {locale === 'ru' && 'Об Архиве'}
                </h3>
                <p className="text-blue-800 dark:text-blue-200">
                  {locale === 'ro' &&
                    'Arhiva noastră conține toate articolele publicate de-a lungul anilor. Selectați un an pentru a vedea articolele publicate în acel an, sau navigați prin luni pentru o căutare mai detaliată.'}
                  {locale === 'en' &&
                    'Our archive contains all articles published over the years. Select a year to see articles published in that year, or navigate through months for more detailed browsing.'}
                  {locale === 'ru' &&
                    'Наш архив содержит все статьи, опубликованные на протяжении многих лет. Выберите год, чтобы просмотреть статьи, опубликованные в этом году, или перейдите по месяцам для более детального просмотра.'}
                </p>
              </div>
            </div>
          </div>
        )}
      </div>
    );
  } catch (error) {
    console.error('Error fetching archive data:', error);

    return (
      <div className="container mx-auto px-4 py-8">
        <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-4">
          {locale === 'ro' && 'Arhivă'}
          {locale === 'en' && 'Archive'}
          {locale === 'ru' && 'Архив'}
        </h1>
        <p className="text-gray-600 dark:text-gray-400">
          {locale === 'ro' && 'Ne pare rău, a apărut o eroare la încărcarea arhivei.'}
          {locale === 'en' && 'Sorry, an error occurred while loading the archive.'}
          {locale === 'ru' && 'Извините, произошла ошибка при загрузке архива.'}
        </p>
      </div>
    );
  }
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({ params }: ArchivePageProps): Promise<Metadata> {
  const { locale } = await params;

  const titles = {
    ro: 'Arhivă Articole',
    en: 'Article Archive',
    ru: 'Архив Статей',
  };

  const descriptions = {
    ro: 'Explorați arhiva completă a articolelor publicate pe Deschide News, organizată pe ani și luni.',
    en: 'Explore the complete archive of articles published on Deschide News, organized by years and months.',
    ru: 'Изучите полный архив статей, опубликованных на Deschide News, организованный по годам и месяцам.',
  };

  const title = titles[locale as keyof typeof titles] || titles.ro;
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/archive`,
      languages: {
        ro: '/archive',
        en: '/en/archive',
        ru: '/ru/archive',
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
