/**
 * Admin Statistics Dashboard Page
 * Displays comprehensive analytics and performance metrics
 */

import { Suspense } from 'react';
import { SiteStatsOverview } from '@/components/admin/stats/SiteStatsOverview';
import { TrendingArticlesTable } from '@/components/admin/stats/TrendingArticlesTable';
import { RealTimeStats } from '@/components/admin/stats/RealTimeStats';
import { DateRangePicker } from '@/components/admin/stats/DateRangePicker';
import { LoadingSkeleton } from '@/components/ui/LoadingSkeleton';
import { getAccessToken } from '@/lib/auth/session';

interface Props {
  searchParams: Promise<{
    range?: string;
  }>;
  params: Promise<{
    locale: string;
  }>;
}

export const metadata = {
  title: 'Statistics Dashboard | Admin',
  description: 'View site analytics, trending articles, and real-time metrics',
};

export default async function StatisticsPage({ searchParams, params }: Props) {
  const resolvedSearchParams = await searchParams;
  const resolvedParams = await params;
  const dateRange = (resolvedSearchParams.range || '7days') as any;
  const locale = resolvedParams.locale || 'ro';

  // Get auth token from session
  const token = await getAccessToken();

  return (
    <div className="container mx-auto p-6 max-w-7xl">
      {/* Header */}
      <header className="mb-8">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
              <svg className="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
              </svg>
              Statistics Dashboard
            </h1>
            <p className="text-gray-600 mt-1">
              Site analytics, trending content, and real-time metrics
            </p>
          </div>

          {/* Date Range Selector */}
          <DateRangePicker defaultRange={dateRange} />
        </div>
      </header>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {/* Site-wide overview cards */}
        <Suspense fallback={<LoadingSkeleton variant="stat" count={4} />}>
          <SiteStatsOverview dateRange={dateRange} />
        </Suspense>

        {/* Real-time stats widget */}
        <div className="lg:col-span-1">
          <Suspense fallback={<LoadingSkeleton variant="card" />}>
            {token ? (
              <RealTimeStats token={token || ''} />
            ) : (
              <div className="bg-yellow-50 p-6 rounded-lg border border-yellow-200">
                <p className="text-yellow-800">Authentication required for real-time stats</p>
              </div>
            )}
          </Suspense>
        </div>
      </div>

      {/* Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Trending articles */}
        <Suspense fallback={<LoadingSkeleton variant="table" count={5} />}>
          <TrendingArticlesTable limit={10} locale={locale} />
        </Suspense>

        {/* Traffic overview placeholder */}
        <div className="bg-white p-6 rounded-lg shadow">
          <h2 className="text-xl font-semibold mb-4 flex items-center gap-2">
            <svg className="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
              <path fillRule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 0l-2 2a1 1 0 101.414 1.414L8 10.414l1.293 1.293a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
            </svg>
            Traffic Overview
          </h2>
          <div className="flex items-center justify-center h-64 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
            <div className="text-center">
              <svg className="w-16 h-16 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
              </svg>
              <p className="text-gray-600 font-medium">Traffic Chart</p>
              <p className="text-sm text-gray-500 mt-1">Coming soon with recharts integration</p>
            </div>
          </div>
        </div>
      </div>

      {/* Info Footer */}
      <div className="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
        <div className="flex items-start gap-3">
          <svg className="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
          </svg>
          <div className="flex-1">
            <p className="text-sm text-blue-900 font-medium">
              Data is cached for 60 seconds for optimal performance
            </p>
            <p className="text-xs text-blue-700 mt-1">
              Real-time stats update every 5 seconds. All statistics are aggregated from Redis and PostgreSQL.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
