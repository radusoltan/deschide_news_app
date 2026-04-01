/**
 * Admin Dashboard - Main Overview Page
 * Displays comprehensive statistics, charts, and quick actions
 */

import { Suspense } from 'react';
import Link from 'next/link';
import { getAccessToken } from '@/lib/dal';
import { getSiteStats, getTrendingArticles } from '@/lib/api/statistics';
import { apiRequest } from '@/lib/api/client';
import { ArticleViewsChart } from '@/components/admin/stats/ArticleViewsChart';
import { TrafficOverviewChart } from '@/components/admin/stats/TrafficOverviewChart';
import { BounceRateChart } from '@/components/admin/stats/BounceRateChart';
import { RealTimeStats } from '@/components/admin/stats/RealTimeStats';

interface Props {
  params: Promise<{
    locale: string;
  }>;
}

async function DashboardContent({ locale }: { locale: string }) {
  // Get access token from session
  const token = await getAccessToken();

  // Debug: log token status
  console.log('[Dashboard] Token status:', token ? `Token present (${token.substring(0, 20)}...)` : 'No token');

  // Fetch statistics data
  let siteStats;
  let trendingArticles;
  let articleCounts;

  try {
    // Fetch trending articles (public endpoint)
    trendingArticles = await getTrendingArticles(5, locale);
  } catch (error) {
    console.error('Failed to fetch trending articles:', error);
  }

  // Only fetch authenticated endpoints if token exists
  if (token) {
    try {
      // Fetch site-wide stats (last 7 days)
      siteStats = await getSiteStats('7days', token);
    } catch (error) {
      console.error('Failed to fetch site stats:', error);
    }

    try {
      // Fetch article counts by status
      articleCounts = await apiRequest<any>('/api/admin/stats/article-counts', {
        token,
        next: { revalidate: 60 },
      });
    } catch (error) {
      console.error('Failed to fetch article counts:', error);
    }
  }

  // Calculate totals (with fallbacks)
  const totalArticles = articleCounts?.total ?? 0;
  const publishedArticles = articleCounts?.published ?? 0;
  const draftArticles = (articleCounts?.new ?? 0) + (articleCounts?.submitted ?? 0);

  // Get total categories count from API
  let totalCategories = 0;
  try {
    const categoriesData = await apiRequest<any>('/api/categories?itemsPerPage=1', {
      next: { revalidate: 60 },
    });
    totalCategories = categoriesData?.totalItems ?? 0;
  } catch (error) {
    console.error('Failed to fetch categories count:', error);
  }

  // Calculate growth percentages (compared to average)
  const calculateGrowth = (current: number, previous: number) => {
    if (previous === 0) return 0;
    return ((current - previous) / previous * 100).toFixed(1);
  };

  const recentStats = siteStats?.stats || [];
  const latestDayVisits = recentStats[recentStats.length - 1]?.total_visits || 0;
  const previousDayVisits = recentStats[recentStats.length - 2]?.total_visits || 0;
  const visitsGrowth = calculateGrowth(latestDayVisits, previousDayVisits);

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-lg p-6 text-white">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold mb-2">
              Dashboard
            </h1>
            <p className="text-blue-100">
              Bine ai venit în panoul de administrare. Aici găsești statistici complete despre site.
            </p>
          </div>
          <div className="hidden md:block">
            <svg className="w-20 h-20 text-blue-400 opacity-50" fill="currentColor" viewBox="0 0 20 20">
              <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
            </svg>
          </div>
        </div>
      </div>

      {/* Statistics Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Total Articles */}
        <div className="stat-card bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200">
          <div className="flex items-center justify-between mb-4">
            <div>
              <p className="text-sm font-medium text-blue-700">
                Total Articole
              </p>
              <h3 className="text-3xl font-bold text-blue-900 mt-1">
                {totalArticles.toLocaleString()}
              </h3>
            </div>
            <div className="p-3 bg-blue-200 rounded-full">
              <svg
                className="w-8 h-8 text-blue-700"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M2 5a2 2 0 012-2h8a2 2 0 012 2v10a2 2 0 002 2H4a2 2 0 01-2-2V5zm3 1h6v4H5V6zm6 6H5v2h6v-2z"
                  clipRule="evenodd"
                />
                <path d="M15 7h1a2 2 0 012 2v5.5a1.5 1.5 0 01-3 0V7z" />
              </svg>
            </div>
          </div>
          <Link
            href={`/${locale}/admin/articles`}
            className="text-sm text-blue-700 hover:text-blue-900 font-medium flex items-center gap-1"
          >
            Vezi toate
            <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
            </svg>
          </Link>
        </div>

        {/* Published Articles */}
        <div className="stat-card bg-gradient-to-br from-green-50 to-green-100 border-green-200">
          <div className="flex items-center justify-between mb-4">
            <div>
              <p className="text-sm font-medium text-green-700">
                Publicate
              </p>
              <h3 className="text-3xl font-bold text-green-900 mt-1">
                {publishedArticles.toLocaleString()}
              </h3>
            </div>
            <div className="p-3 bg-green-200 rounded-full">
              <svg
                className="w-8 h-8 text-green-700"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clipRule="evenodd"
                />
              </svg>
            </div>
          </div>
          <div className="flex items-center text-sm">
            <span className="text-green-700 font-medium">
              {totalArticles > 0 ? ((publishedArticles / totalArticles) * 100).toFixed(1) : 0}% din total
            </span>
          </div>
        </div>

        {/* Draft Articles */}
        <div className="stat-card bg-gradient-to-br from-yellow-50 to-yellow-100 border-yellow-200">
          <div className="flex items-center justify-between mb-4">
            <div>
              <p className="text-sm font-medium text-yellow-700">
                Drafturi
              </p>
              <h3 className="text-3xl font-bold text-yellow-900 mt-1">
                {draftArticles.toLocaleString()}
              </h3>
            </div>
            <div className="p-3 bg-yellow-200 rounded-full">
              <svg
                className="w-8 h-8 text-yellow-700"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
              </svg>
            </div>
          </div>
          <div className="flex items-center text-sm">
            <span className="text-yellow-700 font-medium">
              {totalArticles > 0 ? ((draftArticles / totalArticles) * 100).toFixed(1) : 0}% din total
            </span>
          </div>
        </div>

        {/* Total Categories */}
        <div className="stat-card bg-gradient-to-br from-purple-50 to-purple-100 border-purple-200">
          <div className="flex items-center justify-between mb-4">
            <div>
              <p className="text-sm font-medium text-purple-700">
                Categorii
              </p>
              <h3 className="text-3xl font-bold text-purple-900 mt-1">
                {totalCategories}
              </h3>
            </div>
            <div className="p-3 bg-purple-200 rounded-full">
              <svg
                className="w-8 h-8 text-purple-700"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
              </svg>
            </div>
          </div>
          <Link
            href={`/${locale}/admin/categories`}
            className="text-sm text-purple-700 hover:text-purple-900 font-medium flex items-center gap-1"
          >
            Gestionează
            <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
            </svg>
          </Link>
        </div>
      </div>

      {/* Main Charts Grid */}
      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {/* Traffic Overview Chart - Full Width on left */}
        <div className="xl:col-span-2">
          {!token ? (
            <div className="bg-amber-50 p-8 rounded-lg shadow border-2 border-amber-200">
              <div className="flex items-center gap-3 mb-4">
                <svg className="w-8 h-8 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                </svg>
                <h3 className="text-lg font-semibold text-amber-900">Autentificare Necesară</h3>
              </div>
              <p className="text-amber-800 mb-4">
                Pentru a vizualiza statisticile detaliate și graficele, trebuie să fiți autentificat.
              </p>
              <Link
                href={`/${locale}/login?redirect=${encodeURIComponent(`/${locale}/admin`)}`}
                className="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors"
              >
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M3 3a1 1 0 011 1v12a1 1 0 11-2 0V4a1 1 0 011-1zm7.707 3.293a1 1 0 010 1.414L9.414 9H17a1 1 0 110 2H9.414l1.293 1.293a1 1 0 01-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0z" clipRule="evenodd" />
                </svg>
                Autentifică-te
              </Link>
            </div>
          ) : siteStats?.stats && siteStats.stats.length > 0 ? (
            <TrafficOverviewChart data={siteStats.stats} />
          ) : (
            <div className="bg-surface p-6 rounded-lg shadow">
              <p className="text-secondary">Nu există date de trafic disponibile</p>
            </div>
          )}
        </div>

        {/* Real-time Stats Widget */}
        <div>
          <Suspense fallback={<LoadingSkeleton />}>
            {token ? (
              <RealTimeStats token={token || ''} pollInterval={10000} />
            ) : (
              <div className="bg-yellow-50 p-6 rounded-lg border border-yellow-200">
                <p className="text-yellow-800">Autentificare necesară pentru statistici live</p>
              </div>
            )}
          </Suspense>
        </div>
      </div>

      {/* Secondary Charts Grid - Only show if authenticated */}
      {token && siteStats?.stats && siteStats.stats.length > 0 && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Article Views Chart */}
          <ArticleViewsChart
            data={siteStats.stats.map(stat => ({
              date: stat.date,
              views: stat.total_visits,
              unique_visitors: stat.unique_visitors,
            }))}
            title="Vizualizări Articole (Ultimele 7 zile)"
          />

          {/* Bounce Rate & Engagement */}
          <BounceRateChart
            data={siteStats.stats}
            title="Metrici de Angajament"
          />
        </div>
      )}

      {/* Recent Activity & Quick Actions */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Trending Articles */}
        <div className="admin-card">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-primary dark:text-primary-dark flex items-center gap-2">
              <span className="text-2xl">🔥</span>
              Trending (Ultimele 24h)
            </h2>
            <Link
              href={`/${locale}/admin/statistics`}
              className="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
            >
              Vezi toate
            </Link>
          </div>
          <div className="space-y-3">
            {trendingArticles && trendingArticles.length > 0 ? (
              trendingArticles.map((article, idx) => (
                <div
                  key={article.id}
                  className="flex items-center justify-between p-3 bg-surface-sunken dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                >
                  <div className="flex items-center gap-3 flex-1">
                    <span className="text-2xl font-bold text-primary-dark">
                      #{idx + 1}
                    </span>
                    <div className="flex-1">
                      <Link
                        href={`/${locale}/admin/articles/${article.id}/edit`}
                        className="text-sm font-medium text-primary dark:text-primary-dark hover:text-blue-600 block"
                      >
                        {article.title || 'Fără titlu'}
                      </Link>
                      {article.category && (
                        <span className="inline-block mt-1 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">
                          {article.category.name}
                        </span>
                      )}
                    </div>
                  </div>
                  <div className="text-right">
                    <p className="text-lg font-bold text-primary dark:text-primary-dark">
                      {article.views_24h.toLocaleString()}
                    </p>
                    <p className="text-xs text-secondary">views</p>
                  </div>
                </div>
              ))
            ) : (
              <p className="text-secondary text-sm text-center py-4">
                Nu există articole trending momentan
              </p>
            )}
          </div>
        </div>

        {/* Quick Actions */}
        <div className="admin-card">
          <h2 className="text-lg font-semibold text-primary dark:text-primary-dark mb-4">
            Acțiuni Rapide
          </h2>
          <div className="grid grid-cols-2 gap-4">
            <Link
              href={`/${locale}/admin/articles/new`}
              className="flex flex-col items-center justify-center p-6 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors group"
            >
              <svg
                className="w-10 h-10 text-blue-600 dark:text-blue-400 mb-2 group-hover:scale-110 transition-transform"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                  clipRule="evenodd"
                />
              </svg>
              <span className="text-sm font-medium text-primary dark:text-primary-dark">
                Articol Nou
              </span>
            </Link>

            <Link
              href={`/${locale}/admin/categories/new`}
              className="flex flex-col items-center justify-center p-6 bg-green-50 dark:bg-green-900/20 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors group"
            >
              <svg
                className="w-10 h-10 text-green-600 dark:text-green-400 mb-2 group-hover:scale-110 transition-transform"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
              </svg>
              <span className="text-sm font-medium text-primary dark:text-primary-dark">
                Categorie Nouă
              </span>
            </Link>

            <Link
              href={`/${locale}/admin/images/upload`}
              className="flex flex-col items-center justify-center p-6 bg-purple-50 dark:bg-purple-900/20 rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors group"
            >
              <svg
                className="w-10 h-10 text-purple-600 dark:text-purple-400 mb-2 group-hover:scale-110 transition-transform"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path fillRule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clipRule="evenodd" />
              </svg>
              <span className="text-sm font-medium text-primary dark:text-primary-dark">
                Încarcă Imagini
              </span>
            </Link>

            <Link
              href={`/${locale}/admin/statistics`}
              className="flex flex-col items-center justify-center p-6 bg-surface-sunken dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors group"
            >
              <svg
                className="w-10 h-10 text-gray-600 dark:text-gray-400 mb-2 group-hover:scale-110 transition-transform"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
              </svg>
              <span className="text-sm font-medium text-primary dark:text-primary-dark">
                Statistici Detaliate
              </span>
            </Link>
          </div>
        </div>
      </div>

      {/* Info Footer */}
      <div className="bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 p-4">
        <div className="flex items-start gap-3">
          <svg className="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
          </svg>
          <div className="flex-1">
            <p className="text-sm text-blue-900 dark:text-blue-100 font-medium">
              Datele sunt actualizate automat
            </p>
            <p className="text-xs text-blue-700 dark:text-blue-300 mt-1">
              Statisticile live se actualizează la fiecare 10 secunde. Graficele sunt cache-uite pentru 60 de secunde pentru performanță optimă.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

function LoadingSkeleton() {
  return (
    <div className="bg-surface p-6 rounded-lg shadow animate-pulse">
      <div className="h-6 bg-gray-200 rounded w-1/3 mb-4"></div>
      <div className="space-y-3">
        <div className="h-20 bg-gray-200 rounded"></div>
        <div className="h-20 bg-gray-200 rounded"></div>
      </div>
    </div>
  );
}

export default async function AdminDashboard({ params }: Props) {
  const { locale } = await params;

  return (
    <Suspense fallback={<LoadingSkeleton />}>
      <DashboardContent locale={locale} />
    </Suspense>
  );
}
