import Link from 'next/link';
import { notFound } from 'next/navigation';
import CategoryForm from '../../components/CategoryForm';
import TranslationTabs from './components/TranslationTabs';
import { getCategory, getCategories } from '@/lib/dal';

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

  // Fetch all categories for parent dropdown
  let categories: { id: number; title: string }[] = [];
  try {
    const data = await getCategories({ locale, itemsPerPage: 100 });
    categories = (data.member || []).map((c: { id: number; title: string }) => ({ id: c.id, title: c.title }));
  } catch (err) {
    console.error('Failed to fetch categories for parent dropdown:', err);
  }

  // Extract parent ID
  let parentId: number | null = null;
  if (category.parent) {
    if (typeof category.parent === 'string') {
      const match = category.parent.match(/\/(\d+)$/);
      parentId = match ? parseInt(match[1], 10) : null;
    } else if (typeof category.parent === 'object' && category.parent !== null && 'id' in category.parent) {
      parentId = (category.parent as { id: number }).id;
    }
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
        <h1 className="text-2xl font-semibold text-primary dark:text-primary-dark">
          Edit Category
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Update category details
        </p>
      </div>

      {/* Translation Language Tabs */}
      <TranslationTabs
        entityId={categoryId}
        activeLocale={locale}
        basePath="categories"
      />

      {/* Category Form */}
      <div className="bg-surface dark:bg-surface-dark shadow-md sm:rounded-lg p-6">
        <CategoryForm
          locale={locale}
          categories={categories}
          category={{
            id: category.id,
            title: category.title,
            slug: category.slug,
            status: category.status || 'active',
            onFrontPage: category.onFrontPage || false,
            frontPageLayout: category.frontPageLayout || null,
            inMenu: category.inMenu || false,
            inFooterMenu: category.inFooterMenu || false,
            parentId,
          }}
        />
      </div>
    </div>
  );
}
