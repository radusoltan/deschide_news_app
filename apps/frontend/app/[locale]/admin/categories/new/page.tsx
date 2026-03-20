import Link from 'next/link';
import CategoryForm from '../components/CategoryForm';

interface NewCategoryPageProps {
  params: Promise<{
    locale: string;
  }>;
}

export default async function NewCategoryPage({ params }: NewCategoryPageProps) {
  const { locale } = await params;

  return (
    <div className="p-4">
      {/* Page Header */}
      <div className="mb-4">
        <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mb-2">
          <Link href={`/${locale}/admin`} className="hover:text-blue-600">
            Dashboard
          </Link>
          <span>/</span>
          <Link href={`/${locale}/admin/categories`} className="hover:text-blue-600">
            Categories
          </Link>
          <span>/</span>
          <span>New Category</span>
        </div>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">
          Create New Category
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Add a new category for articles
        </p>
      </div>

      {/* Category Form */}
      <div className="bg-white dark:bg-gray-800 shadow-md sm:rounded-lg p-6">
        <CategoryForm locale={locale} />
      </div>
    </div>
  );
}
