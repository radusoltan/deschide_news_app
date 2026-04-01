import { Suspense } from 'react';
import { Spinner } from 'flowbite-react';
import MenuBuilderManager from './MenuBuilderManager';

interface MenuBuilderPageProps {
  params: Promise<{
    locale: string;
  }>;
}

export default async function MenuBuilderPage({ params }: MenuBuilderPageProps) {
  const { locale } = await params;

  return (
    <div className="p-4">
      {/* Page Header */}
      <div className="mb-4">
        <h1 className="text-2xl font-semibold text-primary dark:text-primary-dark">
          Menu Builder
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Manage navigation menus: add categories, external links, dropdowns with sub-items, reorder via drag-and-drop, and toggle visibility
        </p>
      </div>

      {/* Menu Builder Manager */}
      <div className="bg-surface dark:bg-surface-dark relative shadow-md sm:rounded-lg overflow-hidden">
        <Suspense
          fallback={
            <div className="flex justify-center items-center py-12">
              <Spinner size="xl" />
            </div>
          }
        >
          <MenuBuilderManager locale={locale} />
        </Suspense>
      </div>
    </div>
  );
}
