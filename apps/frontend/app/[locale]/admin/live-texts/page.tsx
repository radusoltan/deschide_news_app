import Link from 'next/link';
import { getLiveTexts } from '@/lib/api';
import { LiveTextsTableClient } from './LiveTextsTableClient';

interface LiveTextsPageProps {
  params: Promise<{
    locale: string;
  }>;
  searchParams: Promise<{
    page?: string;
    status?: string;
  }>;
}

export default async function LiveTextsPage({ params, searchParams }: LiveTextsPageProps) {
  const { locale } = await params;
  const { page: pageParam, status: statusParam } = await searchParams;

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 20;

  // Fetch LiveTexts from API
  let liveTextsData: any[] = [];
  let totalItems = 0;
  let error: string | null = null;

  try {
    const filters: any = {
      page: currentPage,
      itemsPerPage,
      orderBy: 'createdAt' as const,
      orderDirection: 'DESC' as const,
    };

    if (statusParam) {
      filters.status = statusParam;
    }

    const data = await getLiveTexts(filters, { locale, cache: 'no-store' });
    liveTextsData = data.items;
    totalItems = data.totalItems || 0;
  } catch (err) {
    console.error('Failed to fetch live texts:', err);
    error = err instanceof Error ? err.message : 'Failed to load live texts';
    liveTextsData = [];
  }

  // Calculate stats
  const liveCount = liveTextsData.filter((lt) => lt.status === 'live').length;
  const pausedCount = liveTextsData.filter((lt) => lt.status === 'paused').length;
  const endedCount = liveTextsData.filter((lt) => lt.status === 'ended').length;
  const draftCount = liveTextsData.filter((lt) => lt.status === 'draft').length;

  const texts = {
    ro: {
      title: 'Live Texts',
      subtitle: 'Gestionare evenimente live',
      createNew: 'Creare Live Text',
      totalLiveTexts: 'Total Live Texts',
      live: 'Live',
      paused: 'Pauzat',
      ended: 'Încheiat',
      draft: 'Draft',
      all: 'Toate',
      error: 'Eroare',
    },
    en: {
      title: 'Live Texts',
      subtitle: 'Manage live events',
      createNew: 'Create Live Text',
      totalLiveTexts: 'Total Live Texts',
      live: 'Live',
      paused: 'Paused',
      ended: 'Ended',
      draft: 'Draft',
      all: 'All',
      error: 'Error',
    },
    ru: {
      title: 'Live Тексты',
      subtitle: 'Управление событиями в прямом эфире',
      createNew: 'Создать Live Текст',
      totalLiveTexts: 'Всего Live Текстов',
      live: 'В эфире',
      paused: 'Приостановлено',
      ended: 'Завершено',
      draft: 'Черновик',
      all: 'Все',
      error: 'Ошибка',
    },
  };

  const t = texts[locale as keyof typeof texts] || texts.ro;

  const totalPages = Math.ceil(totalItems / itemsPerPage);

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-3xl font-bold text-primary dark:text-primary-dark">
            {t.title}
          </h1>
          <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
            {t.subtitle}
          </p>
        </div>
        <Link
          href={`/${locale}/admin/live-texts/new`}
          className="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors"
        >
          <svg
            className="w-5 h-5 mr-2"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 4v16m8-8H4"
            />
          </svg>
          {t.createNew}
        </Link>
      </div>

      {/* Error Message */}
      {error && (
        <div className="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <p className="text-sm text-red-600 dark:text-red-400">
            <strong>{t.error}:</strong> {error}
          </p>
        </div>
      )}

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        {/* Total */}
        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                {t.totalLiveTexts}
              </p>
              <p className="mt-2 text-3xl font-bold text-primary dark:text-primary-dark">
                {totalItems}
              </p>
            </div>
            <div className="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
              <svg
                className="w-6 h-6 text-blue-600 dark:text-blue-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
            </div>
          </div>
        </div>

        {/* Live */}
        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                {t.live}
              </p>
              <p className="mt-2 text-3xl font-bold text-primary dark:text-primary-dark">
                {liveCount}
              </p>
            </div>
            <div className="p-3 bg-red-100 dark:bg-red-900 rounded-full">
              <svg
                className="w-6 h-6 text-red-600 dark:text-red-400 animate-pulse"
                fill="currentColor"
                viewBox="0 0 24 24"
              >
                <circle cx="12" cy="12" r="10" />
              </svg>
            </div>
          </div>
        </div>

        {/* Paused */}
        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                {t.paused}
              </p>
              <p className="mt-2 text-3xl font-bold text-primary dark:text-primary-dark">
                {pausedCount}
              </p>
            </div>
            <div className="p-3 bg-yellow-100 dark:bg-yellow-900 rounded-full">
              <svg
                className="w-6 h-6 text-yellow-600 dark:text-yellow-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
          </div>
        </div>

        {/* Draft */}
        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                {t.draft}
              </p>
              <p className="mt-2 text-3xl font-bold text-primary dark:text-primary-dark">
                {draftCount}
              </p>
            </div>
            <div className="p-3 bg-gray-100 dark:bg-gray-700 rounded-full">
              <svg
                className="w-6 h-6 text-gray-600 dark:text-gray-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                />
              </svg>
            </div>
          </div>
        </div>

        {/* Ended */}
        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                {t.ended}
              </p>
              <p className="mt-2 text-3xl font-bold text-primary dark:text-primary-dark">
                {endedCount}
              </p>
            </div>
            <div className="p-3 bg-gray-100 dark:bg-gray-700 rounded-full">
              <svg
                className="w-6 h-6 text-gray-600 dark:text-gray-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
          </div>
        </div>
      </div>

      {/* Status Filters */}
      <div className="mb-6 flex flex-wrap gap-2">
        <Link
          href={`/${locale}/admin/live-texts`}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
            !statusParam
              ? 'bg-red-600 text-white'
              : 'bg-gray-200 text-primary hover:bg-gray-300 dark:bg-gray-700 dark:text-primary-dark dark:hover:bg-gray-600'
          }`}
        >
          {t.all}
        </Link>
        <Link
          href={`/${locale}/admin/live-texts?status=live`}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
            statusParam === 'live'
              ? 'bg-red-600 text-white'
              : 'bg-gray-200 text-primary hover:bg-gray-300 dark:bg-gray-700 dark:text-primary-dark dark:hover:bg-gray-600'
          }`}
        >
          {t.live}
        </Link>
        <Link
          href={`/${locale}/admin/live-texts?status=paused`}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
            statusParam === 'paused'
              ? 'bg-red-600 text-white'
              : 'bg-gray-200 text-primary hover:bg-gray-300 dark:bg-gray-700 dark:text-primary-dark dark:hover:bg-gray-600'
          }`}
        >
          {t.paused}
        </Link>
        <Link
          href={`/${locale}/admin/live-texts?status=draft`}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
            statusParam === 'draft'
              ? 'bg-red-600 text-white'
              : 'bg-gray-200 text-primary hover:bg-gray-300 dark:bg-gray-700 dark:text-primary-dark dark:hover:bg-gray-600'
          }`}
        >
          {t.draft}
        </Link>
        <Link
          href={`/${locale}/admin/live-texts?status=ended`}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
            statusParam === 'ended'
              ? 'bg-red-600 text-white'
              : 'bg-gray-200 text-primary hover:bg-gray-300 dark:bg-gray-700 dark:text-primary-dark dark:hover:bg-gray-600'
          }`}
        >
          {t.ended}
        </Link>
      </div>

      {/* LiveTexts Table */}
      <LiveTextsTableClient liveTexts={liveTextsData} locale={locale} />

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="mt-8 flex items-center justify-between">
          <div className="text-sm text-primary dark:text-gray-400">
            Showing {(currentPage - 1) * itemsPerPage + 1} to{' '}
            {Math.min(currentPage * itemsPerPage, totalItems)} of {totalItems} results
          </div>
          <div className="flex gap-2">
            {currentPage > 1 && (
              <Link
                href={`/${locale}/admin/live-texts?page=${currentPage - 1}${statusParam ? `&status=${statusParam}` : ''}`}
                className="px-4 py-2 bg-surface dark:bg-surface-dark border border-gray-300 dark:border-gray-700 rounded-lg text-sm font-medium text-primary dark:text-primary-dark hover:bg-surface-sunken dark:hover:bg-gray-700"
              >
                Previous
              </Link>
            )}
            {currentPage < totalPages && (
              <Link
                href={`/${locale}/admin/live-texts?page=${currentPage + 1}${statusParam ? `&status=${statusParam}` : ''}`}
                className="px-4 py-2 bg-surface dark:bg-surface-dark border border-gray-300 dark:border-gray-700 rounded-lg text-sm font-medium text-primary dark:text-primary-dark hover:bg-surface-sunken dark:hover:bg-gray-700"
              >
                Next
              </Link>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

export const metadata = {
  title: 'Live Texts Management',
  description: 'Manage live text events',
};
