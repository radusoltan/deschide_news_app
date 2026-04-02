'use client';

import { useState, useEffect, useCallback } from 'react';

/**
 * Archive Statistics Component
 * Displays archive metrics in admin dashboard
 * Client component - handles loading and refresh states
 */

interface ArchiveStatsData {
  total_articles: number;
  archived_articles: number;
  archive_percentage: number;
  by_reason: Record<string, number>;
  archived_this_month: number;
  archived_this_year: number;
  oldest_archived?: {
    id: number;
    title: string;
    archived_at: string;
    reason: string;
  };
  most_recent_archived?: {
    id: number;
    title: string;
    archived_at: string;
    reason: string;
  };
}

interface ArchiveStatsProps {
  token: string;
  onRefresh?: () => void;
}

const translations = {
  ro: {
    totalArchived: 'Total Arhivat',
    archivedThisMonth: 'Arhivat Luna Aceasta',
    archivePercentage: 'Procent Arhivat',
    byReason: 'După Motiv',
    refresh: 'Actualizează',
    loading: 'Se încarcă...',
    error: 'Eroare la încărcarea statisticilor',
    retry: 'Reîncearcă',
    articles: 'articole',
    ofTotal: 'din total',
  },
  en: {
    totalArchived: 'Total Archived',
    archivedThisMonth: 'Archived This Month',
    archivePercentage: 'Archive Percentage',
    byReason: 'By Reason',
    refresh: 'Refresh',
    loading: 'Loading...',
    error: 'Error loading statistics',
    retry: 'Retry',
    articles: 'articles',
    ofTotal: 'of total',
  },
  ru: {
    totalArchived: 'Всего архивировано',
    archivedThisMonth: 'Архивировано в этом месяце',
    archivePercentage: 'Процент архива',
    byReason: 'По причине',
    refresh: 'Обновить',
    loading: 'Загрузка...',
    error: 'Ошибка загрузки статистики',
    retry: 'Повторить',
    articles: 'статей',
    ofTotal: 'от общего',
  },
};

export function ArchiveStats({ token, onRefresh }: ArchiveStatsProps) {
  const [data, setData] = useState<ArchiveStatsData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [locale] = useState<keyof typeof translations>('ro'); // Default to Romanian
  const t = translations[locale];

  const fetchStats = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/api/admin/archive/stats`,
        {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
        }
      );

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();
      setData(result);
    } catch (err) {
      console.error('Failed to fetch archive stats:', err);
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  const handleRefresh = () => {
    fetchStats();
    onRefresh?.();
  };

  if (loading) {
    return <LoadingSkeleton />;
  }

  if (error || !data) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg shadow p-6">
        <div className="flex items-center justify-between">
          <div>
            <h3 className="text-lg font-semibold text-red-900">{t.error}</h3>
            <p className="text-sm text-red-600 mt-1">{error}</p>
          </div>
          <button
            onClick={handleRefresh}
            className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
          >
            {t.retry}
          </button>
        </div>
      </div>
    );
  }

  // Get top 3 reasons
  const topReasons = Object.entries(data.by_reason)
    .sort(([, a], [, b]) => b - a)
    .slice(0, 3);

  return (
    <div>
      {/* Header with refresh button */}
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-2xl font-bold text-primary">Archive Statistics</h2>
        <button
          onClick={handleRefresh}
          className="flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-primary rounded-lg transition-colors"
          disabled={loading}
        >
          <svg
            className={`w-5 h-5 ${loading ? 'animate-spin' : ''}`}
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
            />
          </svg>
          {t.refresh}
        </button>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {/* Total Archived */}
        <StatCard
          title={t.totalArchived}
          value={data.archived_articles.toLocaleString()}
          subtitle={`${t.ofTotal} ${data.total_articles.toLocaleString()} ${t.articles}`}
          icon={
            <svg className="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
              <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z" />
              <path
                fillRule="evenodd"
                d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"
                clipRule="evenodd"
              />
            </svg>
          }
          gradient="from-amber-50 to-amber-100"
          borderColor="border-amber-200"
          iconBg="bg-amber-200"
          textColor="text-amber-700"
          valueColor="text-amber-900"
        />

        {/* Archived This Month */}
        <StatCard
          title={t.archivedThisMonth}
          value={data.archived_this_month.toLocaleString()}
          subtitle={`${data.archived_this_year.toLocaleString()} this year`}
          icon={
            <svg className="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
              <path
                fillRule="evenodd"
                d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                clipRule="evenodd"
              />
            </svg>
          }
          gradient="from-green-50 to-green-100"
          borderColor="border-green-200"
          iconBg="bg-green-200"
          textColor="text-green-700"
          valueColor="text-green-900"
        />

        {/* Archive Percentage */}
        <StatCard
          title={t.archivePercentage}
          value={`${data.archive_percentage.toFixed(1)}%`}
          subtitle={`${data.archived_articles.toLocaleString()} / ${data.total_articles.toLocaleString()}`}
          icon={
            <svg className="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
              <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
            </svg>
          }
          gradient="from-blue-50 to-blue-100"
          borderColor="border-blue-200"
          iconBg="bg-blue-200"
          textColor="text-blue-700"
          valueColor="text-blue-900"
        />

        {/* By Reason - Top 3 */}
        <div className="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-lg shadow p-6">
          <div className="flex items-center justify-between mb-4">
            <div className="flex-1">
              <p className="text-sm font-medium text-purple-700">{t.byReason}</p>
            </div>
            <div className="p-3 bg-purple-200 rounded-full">
              <svg className="w-8 h-8 text-purple-700" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                <path
                  fillRule="evenodd"
                  d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                  clipRule="evenodd"
                />
              </svg>
            </div>
          </div>
          <div className="space-y-3">
            {topReasons.map(([reason, count]) => (
              <div key={reason} className="flex items-center justify-between">
                <span className="text-sm font-medium text-purple-800 capitalize">
                  {reason.replace(/_/g, ' ')}
                </span>
                <span className="text-lg font-bold text-purple-900">
                  {count.toLocaleString()}
                </span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

interface StatCardProps {
  title: string;
  value: string;
  subtitle: string;
  icon: React.ReactNode;
  gradient: string;
  borderColor: string;
  iconBg: string;
  textColor: string;
  valueColor: string;
}

function StatCard({
  title,
  value,
  subtitle,
  icon,
  gradient,
  borderColor,
  iconBg,
  textColor,
  valueColor,
}: StatCardProps) {
  return (
    <div className={`bg-gradient-to-br ${gradient} border ${borderColor} rounded-lg shadow p-6`}>
      <div className="flex items-center justify-between mb-4">
        <div className="flex-1">
          <p className={`text-sm font-medium ${textColor}`}>{title}</p>
          <h3 className={`text-3xl font-bold ${valueColor} mt-1`}>{value}</h3>
        </div>
        <div className={`p-3 ${iconBg} rounded-full`}>
          <div className={textColor}>{icon}</div>
        </div>
      </div>
      <p className="text-xs text-gray-600">{subtitle}</p>
    </div>
  );
}

function LoadingSkeleton() {
  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div className="h-8 w-48 bg-gray-200 rounded animate-pulse" />
        <div className="h-10 w-32 bg-gray-200 rounded animate-pulse" />
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {[...Array(4)].map((_, i) => (
          <div key={i} className="bg-gray-100 border border-gray-200 rounded-lg shadow p-6">
            <div className="flex items-center justify-between mb-4">
              <div className="flex-1 space-y-3">
                <div className="h-4 w-24 bg-gray-200 rounded animate-pulse" />
                <div className="h-8 w-20 bg-gray-300 rounded animate-pulse" />
              </div>
              <div className="w-14 h-14 bg-gray-200 rounded-full animate-pulse" />
            </div>
            <div className="h-3 w-32 bg-gray-200 rounded animate-pulse" />
          </div>
        ))}
      </div>
    </div>
  );
}
