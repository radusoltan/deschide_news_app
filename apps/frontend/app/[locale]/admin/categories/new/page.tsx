import Link from 'next/link';
import CategoryForm from '../components/CategoryForm';
import { getCategories } from '@/lib/dal';

interface NewCategoryPageProps {
  params: Promise<{
    locale: string;
  }>;
}

export default async function NewCategoryPage({ params }: NewCategoryPageProps) {
  const { locale } = await params;

  // Fetch categories for parent dropdown
  let categories: any[] = [];
  try {
    const data = await getCategories({ locale, itemsPerPage: 100 });
    categories = (data.member || []).map((c: any) => ({ id: c.id, title: c.title }));
  } catch (err) {
    console.error('Failed to fetch categories for parent dropdown:', err);
  }

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
        <h1 className="text-2xl font-semibold text-primary dark:text-primary-dark">
          Create New Category
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Add a new category for articles
        </p>
      </div>

      {/* Category Form */}
      <div className="bg-surface dark:bg-surface-dark shadow-md sm:rounded-lg p-6">
        <CategoryForm locale={locale} categories={categories} />
      </div>
    </div>
  );
}
