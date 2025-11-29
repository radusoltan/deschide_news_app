import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import ArticleCard from '@/components/article/ArticleCard';
import { lookupAuthor } from '@/lib/api/slug-lookup';
import type { Locale } from '@/lib/types';

interface AuthorPageProps {
  params: Promise<{
    locale: string;
    slug: string;
  }>;
  searchParams: Promise<{
    page?: string;
  }>;
}

/**
 * Author Profile Page
 *
 * Displays author information and their published articles
 * Authors are non-translatable (same slug across all locales)
 */
export default async function AuthorPage({ params, searchParams }: AuthorPageProps) {
  const { locale, slug } = await params;
  const { page = '1' } = await searchParams;

  // Lookup author by slug
  const authorResult = await lookupAuthor(slug);

  if (!authorResult.found || !authorResult.entity) {
    notFound();
  }

  const author = authorResult.entity;

  const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';
  const currentPage = parseInt(page, 10);
  const itemsPerPage = 24;

  try {
    // Fetch author's articles
    const queryParams = new URLSearchParams({
      status: 'published',
      'authors': author.id.toString(),
      page: currentPage.toString(),
      itemsPerPage: itemsPerPage.toString(),
      'order[publishedAt]': 'DESC',
    });

    const response = await fetch(`${apiUrl}/api/articles?${queryParams.toString()}`, {
      headers: {
        'Accept': 'application/ld+json',
        'Accept-Language': locale,
      },
      next: { revalidate: 600 }, // Revalidate every 10 minutes
    });

    if (!response.ok) {
      throw new Error(`Failed to fetch articles: ${response.status}`);
    }

    const data = await response.json();
    const articles = data.member || data['hydra:member'] || [];
    const totalItems = data.totalItems || data['hydra:totalItems'] || 0;
    const totalPages = Math.ceil(totalItems / itemsPerPage);

    const texts = {
      ro: {
        articles: 'Articole',
        allArticles: 'Toate articolele de',
        articleCount: 'articole publicate',
        bio: 'Biografie',
        email: 'Email',
        noArticles: 'Acest autor nu are articole publicate încă.',
        previous: 'Anterior',
        next: 'Următor',
      },
      en: {
        articles: 'Articles',
        allArticles: 'All articles by',
        articleCount: 'published articles',
        bio: 'Biography',
        email: 'Email',
        noArticles: 'This author has no published articles yet.',
        previous: 'Previous',
        next: 'Next',
      },
      ru: {
        articles: 'Статьи',
        allArticles: 'Все статьи автора',
        articleCount: 'опубликованных статей',
        bio: 'Биография',
        email: 'Email',
        noArticles: 'У этого автора пока нет опубликованных статей.',
        previous: 'Предыдущий',
        next: 'Следующий',
      },
    };

    const t = texts[locale as keyof typeof texts] || texts.ro;

    return (
      <div className="container mx-auto px-4 py-8">
        {/* Author Header */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 mb-8">
          <div className="flex flex-col md:flex-row gap-6 items-start">
            {/* Author Avatar */}
            <div className="flex-shrink-0">
              <div className="w-32 h-32 rounded-full bg-gradient-to-br from-red-600 to-red-800 flex items-center justify-center text-white text-4xl font-bold">
                {author.firstName?.[0]}
                {author.lastName?.[0]}
              </div>
            </div>

            {/* Author Info */}
            <div className="flex-1">
              <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-2">
                {author.fullName || `${author.firstName} ${author.lastName}`}
              </h1>
              {author.title && (
                <p className="text-lg text-gray-600 dark:text-gray-400 mb-4">
                  {author.title}
                </p>
              )}
              <p className="text-gray-700 dark:text-gray-300 mb-4">
                {totalItems} {t.articleCount}
              </p>

              {/* Bio */}
              {author.bio && (
                <div className="mt-6">
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    {t.bio}
                  </h3>
                  <p className="text-gray-700 dark:text-gray-300 leading-relaxed">
                    {author.bio}
                  </p>
                </div>
              )}

              {/* Contact */}
              {author.email && (
                <div className="mt-4">
                  <a
                    href={`mailto:${author.email}`}
                    className="inline-flex items-center gap-2 text-red-600 hover:text-red-700"
                  >
                    <svg
                      className="w-5 h-5"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                      />
                    </svg>
                    {author.email}
                  </a>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Articles Section */}
        <div>
          <h2 className="text-3xl font-bold text-gray-900 dark:text-white mb-6">
            {t.articles}
          </h2>

          {articles.length > 0 ? (
            <>
              {/* Articles Grid */}
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
                      href={`/${locale}/author/${slug}?page=${currentPage - 1}`}
                      className="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                      {t.previous}
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
                          href={`/${locale}/author/${slug}?page=${pageNum}`}
                          className={`px-4 py-2 rounded ${
                            currentPage === pageNum
                              ? 'bg-red-600 text-white'
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
                      href={`/${locale}/author/${slug}?page=${currentPage + 1}`}
                      className="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                      {t.next}
                    </a>
                  )}
                </div>
              )}
            </>
          ) : (
            <div className="text-center py-12">
              <p className="text-gray-600 dark:text-gray-400 text-lg">{t.noArticles}</p>
            </div>
          )}
        </div>
      </div>
    );
  } catch (error) {
    console.error('Error fetching author articles:', error);
    notFound();
  }
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({ params }: AuthorPageProps): Promise<Metadata> {
  const { locale, slug } = await params;

  const authorResult = await lookupAuthor(slug);

  if (!authorResult.found || !authorResult.entity) {
    return {
      title: 'Author Not Found',
    };
  }

  const author = authorResult.entity;
  const fullName = author.fullName || `${author.firstName} ${author.lastName}`;

  const titles = {
    ro: `${fullName} - Autor`,
    en: `${fullName} - Author`,
    ru: `${fullName} - Автор`,
  };

  const descriptions = {
    ro: `Citiți toate articolele scrise de ${fullName} pe Deschide News.`,
    en: `Read all articles written by ${fullName} on Deschide News.`,
    ru: `Читайте все статьи, написанные ${fullName} на Deschide News.`,
  };

  const title = titles[locale as keyof typeof titles] || titles.ro;
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/author/${slug}`,
      languages: {
        ro: `/author/${slug}`,
        en: `/en/author/${slug}`,
        ru: `/ru/author/${slug}`,
      },
    },
    openGraph: {
      title,
      description,
      locale: locale === 'ro' ? 'ro_RO' : locale === 'en' ? 'en_US' : 'ru_RU',
      type: 'profile',
    },
  };
}

/**
 * Revalidate every 10 minutes
 */
export const revalidate = 600;
