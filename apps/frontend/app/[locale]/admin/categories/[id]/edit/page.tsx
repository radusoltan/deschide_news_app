import Link from 'next/link';
import { notFound } from 'next/navigation';
import CategoryForm from '../../components/CategoryForm';
import { getCategory } from '@/lib/dal';

interface EditCategoryPageProps {
  params: Promise<{
    locale: string;
    id: string;
  }>;
}

export default async function EditCategoryPage({ params }: EditCategoryPageProps) {
  const { locale, id } = await params;
  const categoryId = parseInt(id, 10);

  if (isNaN(categoryId)) {
    notFound();
  }

  // Fetch category data
  let category;
  try {
    category = await getCategory(categoryId, locale);
  } catch (error) {
    console.error('Failed to fetch category:', error);
    notFound();
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
          <span>Edit Category</span>
        </div>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">
          Edit Category
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Update category details
        </p>
      </div>

      {/* Category Form */}
      <div className="bg-white dark:bg-gray-800 shadow-md sm:rounded-lg p-6">
        <CategoryForm
          locale={locale}
          category={{
            id: category.id,
            title: category.title,
            slug: category.slug,
            status: category.status || 'active',
            onFrontPage: category.onFrontPage || false,
          }}
        />
      </div>
    </div>
  );
}
