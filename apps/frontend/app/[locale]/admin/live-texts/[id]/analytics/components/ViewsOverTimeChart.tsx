'use client';

import type { ViewsOverTimeData } from '@/lib/types/livetext';

interface ViewsOverTimeChartProps {
  data: ViewsOverTimeData[];
}

export function ViewsOverTimeChart({ data }: ViewsOverTimeChartProps) {
  if (!data || data.length === 0) {
    return (
      <div className="text-center text-secondary dark:text-gray-400 py-8">
        No data available
      </div>
    );
  }

  // Find max value for scaling
  const maxCount = Math.max(...data.map(d => d.count));
  const chartHeight = 200;

  // Format hour for display
  const formatHour = (hour: string) => {
    try {
      const date = new Date(hour);
      return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
      });
    } catch {
      return hour;
    }
  };

  return (
    <div className="space-y-4">
      {/* Chart */}
      <div className="relative" style={{ height: chartHeight }}>
        <div className="absolute inset-0 flex items-end justify-between gap-1">
          {data.map((item, index) => {
            const height = maxCount > 0 ? (item.count / maxCount) * chartHeight : 0;

            return (
              <div
                key={index}
                className="flex-1 flex flex-col items-center justify-end group"
              >
                {/* Bar */}
                <div
                  className="w-full bg-blue-500 hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-500 transition-all rounded-t cursor-pointer relative"
                  style={{ height: `${height}px` }}
                >
                  {/* Tooltip on hover */}
                  <div className="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                    <div className="bg-gray-900 text-white text-xs rounded py-1 px-2 whitespace-nowrap">
                      <div className="font-semibold">{item.count} views</div>
                      <div className="text-gray-400">{formatHour(item.hour)}</div>
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>

        {/* Y-axis reference lines */}
        <div className="absolute inset-0 flex flex-col justify-between pointer-events-none">
          {[...Array(5)].map((_, i) => (
            <div
              key={i}
              className="border-t border-gray-200 dark:border-gray-700 w-full"
            />
          ))}
        </div>
      </div>

      {/* X-axis labels */}
      <div className="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
        {data.map((item, index) => {
          // Show every nth label to avoid crowding
          const showLabel = data.length <= 12 || index % Math.ceil(data.length / 12) === 0;

          return (
            <div key={index} className="flex-1 text-center">
              {showLabel ? formatHour(item.hour) : ''}
            </div>
          );
        })}
      </div>

      {/* Legend */}
      <div className="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 pt-4 border-t border-gray-200 dark:border-gray-700">
        <div>
          Total: <span className="font-semibold text-primary dark:text-gray-100">
            {data.reduce((sum, item) => sum + item.count, 0).toLocaleString()}
          </span> views
        </div>
        <div>
          Peak: <span className="font-semibold text-primary dark:text-gray-100">
            {maxCount.toLocaleString()}
          </span> views/hour
        </div>
      </div>
    </div>
  );
}
