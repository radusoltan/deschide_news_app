'use client';

import { Monitor, Smartphone, Tablet, HelpCircle } from 'lucide-react';
import type { ViewersByPlatformData } from '@/lib/types/livetext';

interface ViewersByPlatformChartProps {
  data: ViewersByPlatformData[];
}

const platformIcons = {
  Desktop: Monitor,
  Mobile: Smartphone,
  Tablet: Tablet,
  Other: HelpCircle
};

const platformColors = {
  Desktop: { bg: 'bg-blue-500', text: 'text-blue-600' },
  Mobile: { bg: 'bg-green-500', text: 'text-green-600' },
  Tablet: { bg: 'bg-purple-500', text: 'text-purple-600' },
  Other: { bg: 'bg-gray-500', text: 'text-gray-600' }
};

export function ViewersByPlatformChart({ data }: ViewersByPlatformChartProps) {
  if (!data || data.length === 0) {
    return (
      <div className="text-center text-gray-500 dark:text-gray-400 py-8">
        No platform data available
      </div>
    );
  }

  const total = data.reduce((sum, item) => sum + item.count, 0);

  return (
    <div className="space-y-6">
      {/* Horizontal bar chart */}
      <div className="space-y-3">
        {data.map((platform) => {
          const percentage = total > 0 ? (platform.count / total) * 100 : 0;
          const Icon = platformIcons[platform.platform as keyof typeof platformIcons] || HelpCircle;
          const colors = platformColors[platform.platform as keyof typeof platformColors] || platformColors.Other;

          return (
            <div key={platform.platform} className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <div className="flex items-center gap-2">
                  <Icon className={`h-4 w-4 ${colors.text} dark:opacity-80`} />
                  <span className="font-medium text-gray-900 dark:text-gray-100">
                    {platform.platform}
                  </span>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-gray-600 dark:text-gray-400">
                    {platform.count.toLocaleString()}
                  </span>
                  <span className="text-gray-500 dark:text-gray-500">
                    ({percentage.toFixed(1)}%)
                  </span>
                </div>
              </div>
              <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                  className={`${colors.bg} h-2 rounded-full transition-all duration-500`}
                  style={{ width: `${percentage}%` }}
                />
              </div>
            </div>
          );
        })}
      </div>

      {/* Summary */}
      <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
        <div className="flex items-center justify-between text-sm">
          <span className="text-gray-600 dark:text-gray-400">Total Viewers</span>
          <span className="font-semibold text-gray-900 dark:text-gray-100">
            {total.toLocaleString()}
          </span>
        </div>
      </div>

      {/* Platform breakdown grid */}
      <div className="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        {data.map((platform) => {
          const Icon = platformIcons[platform.platform as keyof typeof platformIcons] || HelpCircle;
          const colors = platformColors[platform.platform as keyof typeof platformColors] || platformColors.Other;

          return (
            <div
              key={platform.platform}
              className="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800"
            >
              <div className={`p-2 rounded-lg ${colors.bg} bg-opacity-10`}>
                <Icon className={`h-5 w-5 ${colors.text}`} />
              </div>
              <div>
                <div className="text-xs text-gray-600 dark:text-gray-400">
                  {platform.platform}
                </div>
                <div className="font-semibold text-gray-900 dark:text-gray-100">
                  {platform.count.toLocaleString()}
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
