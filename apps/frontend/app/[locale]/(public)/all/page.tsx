import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import ArticleCard from '@/components/article/ArticleCard';
import type { Locale } from '@/lib/types';

interface AllArticlesPageProps {
  params: Promise<{
    locale: string;
  }>;
  searchParams: Promise<{
    page?: string;
    category?: string;
  }>;
}

/**
 * All Articles Page
 *
 * Displays a paginated list of all published articles across all categories
 * Supports filtering by category via query parameter
 */
export default async function AllArticlesPage({
  params,
  searchParams,
}: AllArticlesPageProps) {
  const { locale } = await params;
  const { page = '1', category } = await searchParams;

  const currentPage = parseInt(page, 10);
  const itemsPerPage = 24;

  // Fetch articles from API
  const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

  try {
    const queryParams = new URLSearchParams({
      page: currentPage.toString(),
      itemsPerPage: itemsPerPage.toString(),
      status: 'published',
      'order[publishedAt]': 'DESC',
    });

    if (category) {
      queryParams.append('category', category);
    }

    const response = await fetch(
      `${apiUrl}/api/articles?${queryParams.toString()}`,
      {
        headers: {
          'Accept': 'application/ld+json',
          'Accept-Language': locale,
        },
        next: { revalidate: 300 }, // Revalidate every 5 minutes
      }
    );

    if (!response.ok) {
      throw new Error(`Failed to fetch articles: ${response.status}`);
    }

    const data = await response.json();
    const articles = data['hydra:member'] || [];
    const totalItems = data['hydra:totalItems'] || 0;
    const totalPages = Math.ceil(totalItems / itemsPerPage);

    // Fetch categories for filter
    const categoriesResponse = await fetch(
      `${apiUrl}/api/categories?itemsPerPage=100`,
      {
        headers: {
          'Accept': 'application/ld+json',
          'Accept-Language': locale,
        },
        next: { revalidate: 3600 }, // Revalidate every hour
      }
    );

    const categoriesData = await categoriesResponse.json();
    const categories = categoriesData['hydra:member'] || [];

    return (
      <div className="container mx-auto px-4 py-8">
        {/* Page Header */}
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-4">
            {locale === 'ro' && 'Toate Articolele'}
            {locale === 'en' && 'All Articles'}
            {locale === 'ru' && 'Все Статьи'}
          </h1>
          <p className="text-gray-600 dark:text-gray-400">
            {locale === 'ro' && `Găsiți ${totalItems} articole publicate`}
            {locale === 'en' && `Browse ${totalItems} published articles`}
            {locale === 'ru' && `Найти ${totalItems} опубликованных статей`}
          </p>
        </div>

        {/* Category Filter */}
        {categories.length > 0 && (
          <div className="mb-6">
            <div className="flex flex-wrap gap-2">
              <a
                href={`/${locale}/all`}
                className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                  !category
                    ? 'bg-deschide-tomato text-white'
                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                }`}
              >
                {locale === 'ro' && 'Toate'}
                {locale === 'en' && 'All'}
                {locale === 'ru' && 'Все'}
              </a>
              {categories.map((cat: any) => (
                <a
                  key={cat.id}
                  href={`/${locale}/all?category=${cat.id}`}
                  className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                    category === cat.id.toString()
                      ? 'bg-deschide-tomato text-white'
                      : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                  }`}
                >
                  {cat.title}
                </a>
              ))}
            </div>
          </div>
        )}

        {/* Articles Grid */}
        {articles.length > 0 ? (
          <>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
              {articles.map((article: any) => (
                <ArticleCard key={article.id} article={article} locale={locale as Locale} />
              ))}
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex justify-center items-center gap-2">
                {/* Previous Button */}
                {currentPage > 1 && (
                  <a
                    href={`/${locale}/all?page=${currentPage - 1}${category ? `&category=${category}` : ''}`}
                    className="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                  >
                    {locale === 'ro' && 'Anterior'}
                    {locale === 'en' && 'Previous'}
                    {locale === 'ru' && 'Предыдущий'}
                  </a>
                )}

                {/* Page Numbers */}
                <div className="flex gap-2">
                  {Array.from({ length: Math.min(totalPages, 5) }, (_, i) => {
                    let pageNum;
                    if (totalPages <= 5) {
                      pageNum = i + 1;
                    } else if (currentPage <= 3) {
                      pageNum = i + 1;
                    } else if (currentPage >= totalPages - 2) {
                      pageNum = totalPages - 4 + i;
                    } else {
                      pageNum = currentPage - 2 + i;
                    }

                    return (
                      <a
                        key={pageNum}
                        href={`/${locale}/all?page=${pageNum}${category ? `&category=${category}` : ''}`}
                        className={`px-4 py-2 rounded ${
                          currentPage === pageNum
                            ? 'bg-deschide-tomato text-white'
                            : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                        }`}
                      >
                        {pageNum}
                      </a>
                    );
                  })}
                </div>

                {/* Next Button */}
                {currentPage < totalPages && (
                  <a
                    href={`/${locale}/all?page=${currentPage + 1}${category ? `&category=${category}` : ''}`}
                    className="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                  >
                    {locale === 'ro' && 'Următor'}
                    {locale === 'en' && 'Next'}
                    {locale === 'ru' && 'Следующий'}
                  </a>
                )}
              </div>
            )}
          </>
        ) : (
          <div className="text-center py-12">
            <p className="text-gray-600 dark:text-gray-400 text-lg">
              {locale === 'ro' && 'Nu există articole publicate.'}
              {locale === 'en' && 'No published articles found.'}
              {locale === 'ru' && 'Опубликованных статей не найдено.'}
            </p>
          </div>
        )}
      </div>
    );
  } catch (error) {
    console.error('Error fetching articles:', error);
    notFound();
  }
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({
  params,
}: AllArticlesPageProps): Promise<Metadata> {
  const { locale } = await params;

  const titles = {
    ro: 'Toate Articolele',
    en: 'All Articles',
    ru: 'Все Статьи',
  };

  const descriptions = {
    ro: 'Descoperiți toate articolele publicate pe Deschide News. Știri din politică, economie, cultură și multe altele.',
    en: 'Discover all published articles on Deschide News. News from politics, economy, culture and more.',
    ru: 'Откройте для себя все опубликованные статьи на Deschide News. Новости из политики, экономики, культуры и многого другого.',
  };

  const title = titles[locale as keyof typeof titles] || titles.ro;
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/all`,
      languages: {
        ro: '/all',
        en: '/en/all',
        ru: '/ru/all',
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
 * Revalidate every 5 minutes
 */
export const revalidate = 300;
