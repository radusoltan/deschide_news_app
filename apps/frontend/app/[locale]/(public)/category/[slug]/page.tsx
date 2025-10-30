import { notFound } from 'next/navigation';
import { fetchArticlesByCategory } from '@/lib/api/articles';
import { fetchCategories } from '@/lib/api/categories';

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
    <main id="content">
      {/* Category Header */}
      <div className="bg-gray-50 py-6">
        <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
          <div className="flex flex-row flex-wrap">
            {/* Left - Main Content */}
            <div className="flex-shrink max-w-full w-full lg:w-2/3 overflow-hidden">
              <div className="w-full py-3">
                <h2 className="text-gray-800 text-2xl font-bold">
                  <span className="inline-block h-5 border-l-3 border-red-600 mr-2"></span>
                  {category.title}
                </h2>
              </div>

              <div className="flex flex-row flex-wrap -mx-3">
                {/* Hero Article */}
                {heroArticle && (
                  <div className="flex-shrink max-w-full w-full px-3 pb-5">
                    <div className="relative hover-img max-h-98 overflow-hidden">
                      {/* TODO: Add hero article component */}
                      <div className="p-8 bg-gray-200">
                        <h3 className="text-2xl font-bold">{heroArticle.title}</h3>
                      </div>
                    </div>
                  </div>
                )}

                {/* Grid Articles */}
                {gridArticles.map((article) => (
                  <div
                    key={article.id}
                    className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100"
                  >
                    {/* TODO: Add grid article component */}
                    <div className="p-4 bg-gray-100">
                      <h4 className="font-bold">{article.title}</h4>
                    </div>
                  </div>
                ))}
              </div>

              {/* Pagination */}
              {totalPages > 1 && (
                <div className="mt-6">
                  <p className="text-gray-600">
                    Page {currentPage} of {totalPages}
                  </p>
                  {/* TODO: Add pagination component */}
                </div>
              )}
            </div>

            {/* Right Sidebar - Most Popular */}
            <div className="flex-shrink max-w-full w-full lg:w-1/3 lg:pl-8 lg:pt-14 lg:pb-8 order-first lg:order-last">
              <div className="w-full bg-white">
                <div className="mb-6">
                  <div className="p-4 bg-gray-100">
                    <h2 className="text-lg font-bold">Most Popular</h2>
                  </div>
                  <ul className="post-number">
                    {/* TODO: Add most popular articles */}
                    <li className="border-b border-gray-100 hover:bg-gray-50">
                      <a className="text-lg font-bold px-6 py-3 flex flex-row items-center" href="#">
                        Popular Article 1
                      </a>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}
