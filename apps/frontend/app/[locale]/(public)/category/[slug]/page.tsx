import { notFound } from 'next/navigation';
import { fetchArticlesByCategory } from '@/lib/api/articles';
import { fetchCategories } from '@/lib/api/categories';
import CategoryHeroArticle from '@/components/CategoryHeroArticle';
import ArticleCard from '@/components/ArticleCard';
import MostPopular from '@/components/MostPopular';

export const dynamic = 'force-dynamic';
export const revalidate = 120; // Revalidate every 2 minutes

interface CategoryPageProps {
  params: Promise<{
    locale: string;
    slug: string;
  }>;
  searchParams: Promise<{
    page?: string;
  }>;
}

export default async function CategoryPage({ params, searchParams }: CategoryPageProps) {
  const { locale, slug } = await params;
  const { page: pageParam } = await searchParams;

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 7; // 1 hero + 6 grid articles

  // Fetch all categories to find the one matching the slug
  let category: any = null;
  try {
    const categoriesResponse = await fetchCategories(locale);
    const categories = categoriesResponse.member || [];
    category = categories.find((cat: any) => cat.slug === slug);
  } catch (error) {
    console.error('Failed to fetch categories:', error);
  }

  if (!category) {
    notFound();
  }

  // Fetch articles for this category
  let articles: any[] = [];
  let totalItems = 0;
  try {
    const response = await fetchArticlesByCategory(category.id, locale, itemsPerPage);
    articles = response.member || [];
    totalItems = response.totalItems || 0;
  } catch (error) {
    console.error('Failed to fetch articles:', error);
    articles = [];
  }

  const heroArticle = articles[0];
  const gridArticles = articles.slice(1, 7);
  const totalPages = Math.ceil(totalItems / itemsPerPage);

  return (
    <>
      {/* Category Section */}
      <div className="bg-gray-50 py-6">
        <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
          <div className="flex flex-row flex-wrap">
            {/* Left - Main Content */}
            <div className="flex-shrink max-w-full w-full lg:w-2/3 overflow-hidden">
              {/* Category Title */}
              <div className="w-full py-3">
                <h2 className="text-gray-800 text-2xl font-bold">
                  <span className="inline-block h-5 border-l-3 border-red-600 mr-2"></span>
                  {category.title}
                </h2>
              </div>

              <div className="flex flex-row flex-wrap -mx-3">
                {/* Hero Article */}
                {heroArticle && (
                  <CategoryHeroArticle article={heroArticle} locale={locale} />
                )}

                {/* Grid Articles */}
                {gridArticles.map((article) => (
                  <ArticleCard
                    key={article.id}
                    article={article}
                    locale={locale}
                    thumbnailProfile="article_card"
                  />
                ))}

                {/* No Articles Message */}
                {articles.length === 0 && (
                  <div className="flex-shrink max-w-full w-full px-3 py-12 text-center">
                    <p className="text-gray-600 text-lg">
                      No articles found in this category.
                    </p>
                  </div>
                )}
              </div>

              {/* Pagination */}
              {totalPages > 1 && (
                <div className="mt-6 px-3">
                  <div className="flex items-center justify-between">
                    <p className="text-gray-600">
                      Page {currentPage} of {totalPages}
                    </p>
                    <div className="flex gap-2">
                      {currentPage > 1 && (
                        <a
                          href={`/${locale}/category/${slug}?page=${currentPage - 1}`}
                          className="px-4 py-2 bg-gray-800 text-white rounded hover:bg-gray-700"
                        >
                          Previous
                        </a>
                      )}
                      {currentPage < totalPages && (
                        <a
                          href={`/${locale}/category/${slug}?page=${currentPage + 1}`}
                          className="px-4 py-2 bg-gray-800 text-white rounded hover:bg-gray-700"
                        >
                          Next
                        </a>
                      )}
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* Right Sidebar - Most Popular */}
            <div className="flex-shrink max-w-full w-full lg:w-1/3 lg:pl-8 lg:pt-14 lg:pb-8 order-first lg:order-last">
              <MostPopular locale={locale} categoryId={category.id} limit={5} />

              {/* Advertisement Placeholder (optional) */}
              <div className="text-sm py-6 sticky">
                <div className="w-full text-center">
                  <a className="uppercase text-gray-500" href="#">Advertisement</a>
                  <div className="mt-2 bg-gray-200 h-64 flex items-center justify-center">
                    <span className="text-gray-400">Ad Space 250x250</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
