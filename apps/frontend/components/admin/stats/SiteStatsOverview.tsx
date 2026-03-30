/**
 * Site Stats Overview Component
 * Displays key site-wide metrics in card format
 * Server component - fetches data on server side
 */

import { getSiteStats, type DateRange } from '@/lib/api/statistics';
import { getAccessToken } from '@/lib/dal';

interface Props {
  dateRange?: DateRange;
}

export async function SiteStatsOverview({ dateRange = '7days' }: Props) {
  const token = await getAccessToken();

  let data;
  try {
    data = await getSiteStats(dateRange, token || undefined);
  } catch (error) {
    console.error('Failed to fetch site stats:', error);
    return (
      <div className="lg:col-span-2 p-6 bg-red-50 rounded-lg">
        <p className="text-red-600">Failed to load site statistics</p>
      </div>
    );
  }

  // Calculate aggregates
  const totalVisits = data.stats.reduce((sum, day) => sum + day.total_visits, 0);
  const avgUniqueVisitors = Math.round(
    data.stats.reduce((sum, day) => sum + day.unique_visitors, 0) / data.stats.length
  );
  const avgBounceRate = Math.round(
    (data.stats.reduce((sum, day) => sum + day.bounce_rate, 0) / data.stats.length) * 100
  );
  const avgSessionDuration = Math.round(
    data.stats.reduce((sum, day) => sum + day.avg_session_duration, 0) / data.stats.length
  );

  return (
    <div className="lg:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-4">
      <StatCard
        title="Total Visits"
        value={totalVisits.toLocaleString()}
        icon={
          <svg className="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
            <path fillRule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clipRule="evenodd" />
          </svg>
        }
        color="blue"
      />
      <StatCard
        title="Unique Visitors/day"
        value={avgUniqueVisitors.toLocaleString()}
        icon={
          <svg className="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
          </svg>
        }
        color="green"
      />
      <StatCard
        title="Bounce Rate"
        value={`${avgBounceRate}%`}
        icon={
          <svg className="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
          </svg>
        }
        color="yellow"
      />
      <StatCard
        title="Avg Session"
        value={`${Math.floor(avgSessionDuration / 60)}m ${avgSessionDuration % 60}s`}
        icon={
          <svg className="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clipRule="evenodd" />
          </svg>
        }
        color="purple"
      />
    </div>
  );
}

interface StatCardProps {
  title: string;
  value: string;
  icon: React.ReactNode;
  color: 'blue' | 'green' | 'yellow' | 'purple';
}

function StatCard({ title, value, icon, color }: StatCardProps) {
  const colorClasses = {
    blue: 'bg-blue-50 text-blue-600 border-blue-200',
    green: 'bg-green-50 text-green-600 border-green-200',
    yellow: 'bg-yellow-50 text-yellow-600 border-yellow-200',
    purple: 'bg-purple-50 text-purple-600 border-purple-200',
  };

  return (
    <div className={`p-6 rounded-lg shadow-sm border-2 ${colorClasses[color]} transition-all hover:shadow-md`}>
      <div className="flex items-center justify-between mb-3">
        <div className="opacity-80">{icon}</div>
      </div>
      <h3 className="text-sm font-medium text-gray-600 uppercase tracking-wide mb-1">
        {title}
      </h3>
      <p className="text-3xl font-bold">{value}</p>
    </div>
  );
}
