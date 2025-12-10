import Link from 'next/link';
import { getShortLinkStats } from '@/lib/api/short-links';
import { getAccessToken } from '@/lib/auth/session';
import ClicksOverTimeChart from './components/ClicksOverTimeChart';
import DeviceTypesChart from './components/DeviceTypesChart';
import TopReferrersChart from './components/TopReferrersChart';
import TopCountriesTable from './components/TopCountriesTable';

interface StatsPageProps {
  params: Promise<{
    locale: string;
    id: string;
  }>;
}

export default async function ShortLinkStatsPage({ params }: StatsPageProps) {
  const { locale, id } = await params;
  const linkId = parseInt(id, 10);

  // Get access token
  const token = await getAccessToken();

  // Fetch statistics
  let stats: any = null;
  let error: string | null = null;

  try {
    if (!token) {
      throw new Error('Nu sunteți autentificat');
    }

    stats = await getShortLinkStats(linkId, token);
  } catch (err) {
    console.error('Failed to fetch stats:', err);
    error =
      err instanceof Error
        ? err.message
        : 'Eroare la încărcarea statisticilor';
  }

  if (error || !stats) {
    return (
      <div className="p-4">
        <div className="mb-6">
          <Link
            href={`/${locale}/admin/short-links`}
            className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm"
          >
            ← Înapoi la listă
          </Link>
        </div>
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <p className="text-sm text-red-600 dark:text-red-400">
            <strong>Eroare:</strong> {error || 'Eroare necunoscută'}
          </p>
        </div>
      </div>
    );
  }

  const { shortLink, clicksPerDay, topReferrers, deviceTypes, countries } =
    stats;

  // Calculate total clicks from daily data
  const totalClicksFromDays = clicksPerDay.reduce(
    (sum: number, day: any) => sum + day.clicks,
    0
  );

  return (
    <div className="p-4">
      {/* Breadcrumb */}
      <div className="mb-6">
        <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mb-4">
          <Link
            href={`/${locale}/admin/short-links`}
            className="hover:text-blue-600 dark:hover:text-blue-400"
          >
            Linkuri Scurte
          </Link>
          <span>/</span>
          <span className="text-gray-900 dark:text-white">Statistici</span>
        </div>

        <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">
          Statistici Link Scurt
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Cod: <span className="font-mono font-semibold">{shortLink.code}</span>
        </p>
      </div>

      {/* Link Info Card */}
      <div className="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 className="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
              Link Scurt
            </h3>
            <p className="text-blue-600 dark:text-blue-400 font-mono">
              {shortLink.shortUrl}
            </p>
          </div>
          <div>
            <h3 className="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
              URL Original
            </h3>
            <p className="text-gray-900 dark:text-white truncate">
              {shortLink.originalUrl}
            </p>
          </div>
          {shortLink.title && (
            <div>
              <h3 className="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                Titlu
              </h3>
              <p className="text-gray-900 dark:text-white">
                {shortLink.title}
              </p>
            </div>
          )}
          <div>
            <h3 className="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
              Creat
            </h3>
            <p className="text-gray-900 dark:text-white">
              {new Date(shortLink.createdAt).toLocaleDateString('ro-RO', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
              })}
            </p>
          </div>
        </div>
      </div>

      {/* Key Metrics */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                Total Clicuri
              </p>
              <p className="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {shortLink.clickCount.toLocaleString('ro-RO')}
              </p>
            </div>
            <div className="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
              <svg
                className="w-6 h-6 text-blue-600 dark:text-blue-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"
                />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                Clicuri (30 zile)
              </p>
              <p className="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {totalClicksFromDays.toLocaleString('ro-RO')}
              </p>
            </div>
            <div className="p-3 bg-green-100 dark:bg-green-900 rounded-full">
              <svg
                className="w-6 h-6 text-green-600 dark:text-green-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                Surse Unice
              </p>
              <p className="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {topReferrers.length}
              </p>
            </div>
            <div className="p-3 bg-purple-100 dark:bg-purple-900 rounded-full">
              <svg
                className="w-6 h-6 text-purple-600 dark:text-purple-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"
                />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                Țări
              </p>
              <p className="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {countries.length}
              </p>
            </div>
            <div className="p-3 bg-orange-100 dark:bg-orange-900 rounded-full">
              <svg
                className="w-6 h-6 text-orange-600 dark:text-orange-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
          </div>
        </div>
      </div>

      {/* Charts Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {/* Clicks Over Time Chart */}
        <div className="lg:col-span-2">
          <ClicksOverTimeChart data={clicksPerDay} />
        </div>

        {/* Device Types Chart */}
        <DeviceTypesChart data={deviceTypes} />

        {/* Top Referrers Chart */}
        <TopReferrersChart data={topReferrers} />
      </div>

      {/* Top Countries Table */}
      <TopCountriesTable data={countries} />
    </div>
  );
}
