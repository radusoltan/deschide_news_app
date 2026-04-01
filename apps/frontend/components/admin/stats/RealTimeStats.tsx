/**
 * Real-Time Stats Widget
 * Displays live statistics with auto-refresh polling
 * Client component - uses state and effects for polling
 */

'use client';

import { useEffect, useState, useCallback } from 'react';
import { fetchRealTimeStatsClient, type RealTimeStats as RealTimeStatsType } from '@/lib/api/statistics';

interface Props {
  token: string;
  pollInterval?: number;
}

export function RealTimeStats({ token, pollInterval = 5000 }: Props) {
  const [data, setData] = useState<RealTimeStatsType | null>(null);
  const [isLive, setIsLive] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    try {
      const result = await fetchRealTimeStatsClient(token);
      setData(result);
      setError(null);
    } catch (err) {
      console.error('Failed to fetch real-time stats:', err);
      setError('Failed to load real-time data');
    }
  }, [token]);

  useEffect(() => {
    // Initial fetch - wrap in timeout to avoid synchronous setState
    const timeoutId = setTimeout(() => {
      fetchData();
    }, 0);

    // Poll every interval
    const interval = setInterval(() => {
      if (isLive) {
        fetchData();
      }
    }, pollInterval);

    return () => {
      clearTimeout(timeoutId);
      clearInterval(interval);
    };
  }, [isLive, pollInterval, fetchData]);

  if (error) {
    return (
      <div className="bg-surface p-6 rounded-lg shadow">
        <h2 className="text-xl font-semibold mb-4 text-red-600">Real-Time Stats</h2>
        <p className="text-red-500">{error}</p>
        <button
          onClick={fetchData}
          className="mt-4 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
        >
          Retry
        </button>
      </div>
    );
  }

  if (!data) {
    return (
      <div className="bg-surface p-6 rounded-lg shadow">
        <div className="animate-pulse">
          <div className="h-6 bg-gray-200 rounded w-1/2 mb-4"></div>
          <div className="h-24 bg-gray-200 rounded mb-4"></div>
          <div className="h-24 bg-gray-200 rounded"></div>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-surface p-6 rounded-lg shadow">
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-xl font-semibold flex items-center gap-2">
          <svg className="w-6 h-6 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clipRule="evenodd" />
          </svg>
          Real-Time Stats
        </h2>
        <div className="flex items-center gap-2">
          <span
            className={`w-2.5 h-2.5 rounded-full ${
              isLive ? 'bg-green-500 animate-pulse' : 'bg-gray-400'
            }`}
          ></span>
          <span className="text-sm font-medium text-gray-600">
            {isLive ? 'Live' : 'Paused'}
          </span>
        </div>
      </div>

      <div className="space-y-4">
        {/* Active Sessions Card */}
        <div className="flex items-center justify-between p-5 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg border-2 border-green-200 shadow-sm">
          <div>
            <p className="text-sm font-medium text-gray-600 mb-1">Active Sessions</p>
            <p className="text-4xl font-bold text-green-600">
              {data.active_sessions}
            </p>
          </div>
          <svg className="w-16 h-16 text-green-500 opacity-50" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
          </svg>
        </div>

        {/* Unique Visitors Today Card */}
        <div className="flex items-center justify-between p-5 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border-2 border-blue-200 shadow-sm">
          <div>
            <p className="text-sm font-medium text-gray-600 mb-1">Unique Visitors Today</p>
            <p className="text-4xl font-bold text-blue-600">
              {data.unique_visitors_today.toLocaleString()}
            </p>
          </div>
          <svg className="w-16 h-16 text-blue-500 opacity-50" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
          </svg>
        </div>

        {/* Trending Now */}
        {data.trending_now && data.trending_now.length > 0 && (
          <div className="pt-3 border-t border-gray-200">
            <p className="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">
              Trending Right Now
            </p>
            <div className="space-y-2">
              {data.trending_now.slice(0, 3).map((item, index) => (
                <div
                  key={item.article_id}
                  className="flex items-center justify-between p-2 bg-surface-sunken rounded"
                >
                  <div className="flex items-center gap-2">
                    <span className="text-lg">{index === 0 ? '🔥' : '📈'}</span>
                    <span className="text-sm text-gray-600">
                      Article #{item.article_id}
                    </span>
                  </div>
                  <span className="text-sm font-semibold text-primary">
                    {item.views} views
                  </span>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Last Updated */}
        <div className="pt-3 border-t border-gray-200">
          <p className="text-xs text-gray-400 flex items-center gap-1">
            <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
              <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clipRule="evenodd" />
            </svg>
            Last updated: {new Date(data.timestamp * 1000).toLocaleTimeString()}
          </p>
        </div>

        {/* Control Button */}
        <button
          onClick={() => setIsLive(!isLive)}
          className={`w-full py-2.5 px-4 rounded-lg text-sm font-medium transition-colors ${
            isLive
              ? 'bg-gray-100 hover:bg-gray-200 text-primary'
              : 'bg-green-100 hover:bg-green-200 text-green-700'
          }`}
        >
          {isLive ? '⏸ Pause Live Updates' : '▶ Resume Live Updates'}
        </button>
      </div>
    </div>
  );
}
