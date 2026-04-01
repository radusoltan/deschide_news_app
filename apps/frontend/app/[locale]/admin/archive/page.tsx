/**
 * Admin Archive Management Page
 * Displays archive statistics, bulk archiving tools, and archived articles list
 */

import { Suspense } from 'react';
import { getAccessToken } from '@/lib/dal';
import { redirect } from 'next/navigation';
import { ArchiveStats } from '@/components/admin/archive/ArchiveStats';
import BulkArchiveForm from '@/components/admin/archive/BulkArchiveForm';
import ArchivedArticlesList from '@/components/admin/archive/ArchivedArticlesList';

interface Props {
  params: Promise<{
    locale: string;
  }>;
  searchParams: Promise<{
    page?: string;
  }>;
}

// Translations object
const translations = {
  ro: {
    title: 'Gestionare Arhivă',
    description: 'Gestionați articolele arhivate și configurați regulile de arhivare automată.',
    stats: 'Statistici Arhivă',
    bulkArchive: 'Arhivare în Masă',
    archivedArticles: 'Articole Arhivate',
    loading: 'Se încarcă...',
  },
  en: {
    title: 'Archive Management',
    description: 'Manage archived articles and configure automatic archiving rules.',
    stats: 'Archive Statistics',
    bulkArchive: 'Bulk Archive',
    archivedArticles: 'Archived Articles',
    loading: 'Loading...',
  },
  ru: {
    title: 'Управление архивом',
    description: 'Управляйте архивными статьями и настраивайте правила автоматической архивации.',
    stats: 'Статистика архива',
    bulkArchive: 'Массовая архивация',
    archivedArticles: 'Архивные статьи',
    loading: 'Загрузка...',
  },
};

async function ArchiveContent({
  locale,
  page
}: {
  locale: string;
  page: number;
}) {
  // Get access token from session
  const token = await getAccessToken();

  // Redirect to login if not authenticated
  if (!token) {
    redirect(`/${locale}/login?redirect=${encodeURIComponent(`/${locale}/admin/archive`)}`);
  }

  const t = translations[locale as keyof typeof translations] || translations.ro;

  return (
    <div className="space-y-6">
      {/* Page Header with Amber/Orange gradient for Archive theme */}
      <div className="bg-gradient-to-r from-amber-600 to-orange-600 rounded-lg shadow-lg p-6 text-white">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold mb-2">
              {t.title}
            </h1>
            <p className="text-amber-100">
              {t.description}
            </p>
          </div>
          <div className="hidden md:block">
            {/* Archive box icon */}
            <svg
              className="w-20 h-20 text-amber-400 opacity-50"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z" />
              <path
                fillRule="evenodd"
                d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"
                clipRule="evenodd"
              />
            </svg>
          </div>
        </div>
      </div>

      {/* Archive Statistics Section */}
      <div>
        <h2 className="text-xl font-semibold text-primary dark:text-primary-dark mb-4">
          {t.stats}
        </h2>
        <Suspense fallback={<StatsLoadingSkeleton />}>
          <ArchiveStats token={token} />
        </Suspense>
      </div>

      {/* Bulk Archive Section */}
      <div>
        <h2 className="text-xl font-semibold text-primary dark:text-primary-dark mb-4">
          {t.bulkArchive}
        </h2>
        <BulkArchiveForm token={token} locale={locale} />
      </div>

      {/* Archived Articles List Section */}
      <div>
        <h2 className="text-xl font-semibold text-primary dark:text-primary-dark mb-4">
          {t.archivedArticles}
        </h2>
        <Suspense fallback={<TableLoadingSkeleton />}>
          <ArchivedArticlesList
            token={token}
            locale={locale}
          />
        </Suspense>
      </div>

      {/* Info Footer */}
      <div className="bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800 p-4">
        <div className="flex items-start gap-3">
          <svg className="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
          </svg>
          <div className="flex-1">
            <p className="text-sm text-amber-900 dark:text-amber-100 font-medium">
              {locale === 'ro' && 'Articolele arhivate rămân accesibile'}
              {locale === 'en' && 'Archived articles remain accessible'}
              {locale === 'ru' && 'Архивные статьи остаются доступными'}
            </p>
            <p className="text-xs text-amber-700 dark:text-amber-300 mt-1">
              {locale === 'ro' && 'Arhivarea nu șterge articolele, doar le marchează ca arhivate. Puteți restaura orice articol arhivat în orice moment.'}
              {locale === 'en' && 'Archiving does not delete articles, it only marks them as archived. You can restore any archived article at any time.'}
              {locale === 'ru' && 'Архивация не удаляет статьи, а только помечает их как архивные. Вы можете восстановить любую архивную статью в любое время.'}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

function StatsLoadingSkeleton() {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      {[1, 2, 3, 4].map((i) => (
        <div key={i} className="stat-card bg-surface-sunken animate-pulse">
          <div className="h-6 bg-gray-200 rounded w-1/2 mb-4"></div>
          <div className="h-10 bg-gray-200 rounded w-3/4 mb-2"></div>
          <div className="h-4 bg-gray-200 rounded w-1/3"></div>
        </div>
      ))}
    </div>
  );
}

function TableLoadingSkeleton() {
  return (
    <div className="admin-card animate-pulse">
      <div className="h-6 bg-gray-200 rounded w-1/4 mb-4"></div>
      <div className="space-y-3">
        {[1, 2, 3, 4, 5].map((i) => (
          <div key={i} className="h-16 bg-gray-200 rounded"></div>
        ))}
      </div>
    </div>
  );
}

export default async function ArchivePage({ params, searchParams }: Props) {
  const { locale } = await params;
  const { page: pageParam } = await searchParams;
  const page = parseInt(pageParam || '1', 10);

  return (
    <Suspense fallback={<PageLoadingSkeleton />}>
      <ArchiveContent locale={locale} page={page} />
    </Suspense>
  );
}

function PageLoadingSkeleton() {
  return (
    <div className="space-y-6">
      <div className="bg-gray-200 rounded-lg h-32 animate-pulse"></div>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="bg-gray-200 rounded-lg h-32 animate-pulse"></div>
        ))}
      </div>
      <div className="bg-gray-200 rounded-lg h-64 animate-pulse"></div>
    </div>
  );
}
