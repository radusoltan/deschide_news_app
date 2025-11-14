import Link from 'next/link';
import ArticleForm from '../components/ArticleForm';
import { getCategories } from '@/lib/dal';
import { getAuthors } from '@/lib/api/authors';

interface NewArticlePageProps {
  params: Promise<{
    locale: string;
  }>;
}

export default async function NewArticlePage({ params }: NewArticlePageProps) {
  const { locale } = await params;

  // Fetch categories for the select dropdown
  let categories: any[] = [];
  try {
    const data = await getCategories({ locale, itemsPerPage: 100 });
    categories = data.member.filter((cat: any) => cat.status === 'active');
  } catch (error) {
    console.error('Failed to fetch categories:', error);
    categories = [];
  }

  // Fetch authors for the article form
  let authors: any[] = [];
  try {
    authors = await getAuthors();
  } catch (error) {
    console.error('Failed to fetch authors:', error);
    authors = [];
  }

  return (
    <div className="p-4">
      {/* Page Header */}
      <div className="mb-4">
        <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mb-2">
          <Link href={`/${locale}/admin/articles`} className="hover:text-blue-600">
            Articles
          </Link>
          <span>/</span>
          <span>New Article</span>
        </div>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">
          Create New Article
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Fill in the details below to create a new article
        </p>
      </div>

      {/* Article Form */}
      <div className="bg-white dark:bg-gray-800 shadow-md sm:rounded-lg p-6">
        <ArticleForm locale={locale} categories={categories} authors={authors} />
      </div>
    </div>
  );
}
