import { Suspense } from 'react';
import { Spinner } from 'flowbite-react';
import ImportantArticlesManager from './ImportantArticlesManager';

interface ImportantArticlesPageProps {
  params: Promise<{
    locale: string;
  }>;
}

export default async function ImportantArticlesPage({ params }: ImportantArticlesPageProps) {
  const { locale } = await params;

  return (
    <div className="p-4">
      {/* Page Header */}
      <div className="mb-4">
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">
          Important Articles List
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Manage articles displayed in the Hero Big Grid on the homepage (min 5, max 25 articles)
        </p>
      </div>

      {/* Important Articles Manager */}
      <div className="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden">
        <Suspense
          fallback={
            <div className="flex justify-center items-center py-12">
              <Spinner size="xl" />
            </div>
          }
        >
          <ImportantArticlesManager locale={locale} />
        </Suspense>
      </div>
    </div>
  );
}
