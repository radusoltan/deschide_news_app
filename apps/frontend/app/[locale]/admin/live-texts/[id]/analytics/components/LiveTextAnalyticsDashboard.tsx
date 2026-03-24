'use client';

import { useEffect, useState, useCallback } from 'react';
import {
  Eye,
  Users,
  Clock,
  TrendingUp,
  MessageSquare,
  Heart,
  RefreshCw
} from 'lucide-react';
import { getLiveTextAnalytics } from '@/lib/api/livetext-analytics';
import type { LiveTextAnalytics } from '@/lib/types/livetext';
import { AnalyticsMetricCard } from './AnalyticsMetricCard';
import { ViewsOverTimeChart } from './ViewsOverTimeChart';
import { PostEngagementTable } from './PostEngagementTable';
import { ViewersByPlatformChart } from './ViewersByPlatformChart';

interface LiveTextAnalyticsDashboardProps {
  liveTextId: number;
}

export function LiveTextAnalyticsDashboard({
  liveTextId
}: LiveTextAnalyticsDashboardProps) {
  const [analytics, setAnalytics] = useState<LiveTextAnalytics | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [lastUpdated, setLastUpdated] = useState<Date>(new Date());
  const [isRefreshing, setIsRefreshing] = useState(false);

  const fetchAnalytics = useCallback(async () => {
    try {
      setIsRefreshing(true);
      const data = await getLiveTextAnalytics(liveTextId, true);
      setAnalytics(data);
      setError(null);
      setLastUpdated(new Date());
    } catch (err) {
      console.error('Failed to fetch analytics:', err);
      setError('Failed to load analytics data');
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, [liveTextId]);

  useEffect(() => {
    fetchAnalytics();

    // Auto-refresh every 60 seconds
    const interval = setInterval(fetchAnalytics, 60000);
    return () => clearInterval(interval);
  }, [fetchAnalytics]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (error || !analytics) {
    return (
      <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6">
        <p className="text-red-800 dark:text-red-200">{error || 'No data available'}</p>
      </div>
    );
  }

  const formatDuration = (seconds: number): string => {
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    if (hours > 0) {
      return `${hours}h ${remainingMinutes}m`;
    }
    return `${minutes}m ${Math.floor(seconds % 60)}s`;
  };

  return (
    <div className="space-y-8">
      {/* Header with refresh button */}
      <div className="flex items-center justify-between">
        <div className="text-sm text-gray-600 dark:text-gray-400">
          Last updated: {lastUpdated.toLocaleTimeString()}
        </div>
        <button
          onClick={fetchAnalytics}
          disabled={isRefreshing}
          className="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
        >
          <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
          Refresh
        </button>
      </div>

      {/* Metrics Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <AnalyticsMetricCard
          title="Total Views"
          value={analytics.totalViews.toLocaleString()}
          icon={<Eye className="h-5 w-5" />}
          color="blue"
          description="All viewing sessions"
        />

        <AnalyticsMetricCard
          title="Unique Viewers"
          value={analytics.uniqueViewers.toLocaleString()}
          icon={<Users className="h-5 w-5" />}
          color="green"
          description="Distinct IP addresses"
        />

        <AnalyticsMetricCard
          title="Avg. Time Spent"
          value={formatDuration(analytics.averageTimeSpent)}
          icon={<Clock className="h-5 w-5" />}
          color="purple"
          description="Per viewing session"
        />

        <AnalyticsMetricCard
          title="Peak Concurrent"
          value={analytics.peakConcurrentViewers.toLocaleString()}
          icon={<TrendingUp className="h-5 w-5" />}
          color="orange"
          description="Maximum simultaneous viewers"
        />

        <AnalyticsMetricCard
          title="Current Viewers"
          value={analytics.currentViewers.toLocaleString()}
          icon={<Eye className="h-5 w-5" />}
          color="red"
          description="Watching now"
          isLive
        />

        <AnalyticsMetricCard
          title="Total Posts"
          value={analytics.totalPosts.toLocaleString()}
          icon={<MessageSquare className="h-5 w-5" />}
          color="indigo"
          description="Published updates"
        />

        <AnalyticsMetricCard
          title="Total Reactions"
          value={analytics.totalReactions.toLocaleString()}
          icon={<Heart className="h-5 w-5" />}
          color="pink"
          description="Across all posts"
        />

        <AnalyticsMetricCard
          title="Engagement Rate"
          value={
            analytics.totalViews > 0
              ? `${((analytics.totalReactions / analytics.totalViews) * 100).toFixed(1)}%`
              : '0%'
          }
          icon={<TrendingUp className="h-5 w-5" />}
          color="teal"
          description="Reactions per view"
        />
      </div>

      {/* Charts Row 1 */}
      {analytics.viewsOverTime && analytics.viewsOverTime.length > 0 && (
        <div className="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
            Views Over Time
          </h2>
          <ViewsOverTimeChart data={analytics.viewsOverTime} />
        </div>
      )}

      {/* Charts Row 2 */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Post Engagement */}
        {analytics.postEngagement && analytics.postEngagement.length > 0 && (
          <div className="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
              Top Posts by Engagement
            </h2>
            <PostEngagementTable data={analytics.postEngagement} />
          </div>
        )}

        {/* Viewers by Platform */}
        {analytics.viewersByPlatform && analytics.viewersByPlatform.length > 0 && (
          <div className="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
              Viewers by Platform
            </h2>
            <ViewersByPlatformChart data={analytics.viewersByPlatform} />
          </div>
        )}
      </div>
    </div>
  );
}
