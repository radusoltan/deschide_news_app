/**
 * Article Views Chart Component
 * Displays article views over time with line and area chart
 */

'use client';

import {
  LineChart,
  Line,
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';

interface ChartDataPoint {
  date: string;
  views: number;
  unique_visitors: number;
}

interface Props {
  data: ChartDataPoint[];
  title?: string;
  showLegend?: boolean;
}

export function ArticleViewsChart({
  data,
  title = 'Article Views Over Time',
  showLegend = true
}: Props) {
  if (!data || data.length === 0) {
    return (
      <div className="bg-surface p-6 rounded-lg shadow">
        <h3 className="text-lg font-semibold text-primary mb-4">{title}</h3>
        <div className="h-64 flex items-center justify-center bg-surface-sunken rounded-lg">
          <p className="text-secondary">No data available</p>
        </div>
      </div>
    );
  }

  // Format dates for display
  const formattedData = data.map(item => ({
    ...item,
    displayDate: new Date(item.date).toLocaleDateString('ro-RO', {
      month: 'short',
      day: 'numeric'
    }),
  }));

  return (
    <div className="bg-surface p-6 rounded-lg shadow">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold text-primary">{title}</h3>
        <div className="flex items-center gap-4">
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded-full bg-blue-500"></div>
            <span className="text-sm text-gray-600">Total Views</span>
          </div>
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded-full bg-green-500"></div>
            <span className="text-sm text-gray-600">Unique Visitors</span>
          </div>
        </div>
      </div>

      <ResponsiveContainer width="100%" height={300}>
        <AreaChart
          data={formattedData}
          margin={{ top: 10, right: 30, left: 0, bottom: 0 }}
        >
          <defs>
            <linearGradient id="colorViews" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="var(--color-accent)" stopOpacity={0.8} />
              <stop offset="95%" stopColor="var(--color-accent)" stopOpacity={0} />
            </linearGradient>
            <linearGradient id="colorVisitors" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="#10b981" stopOpacity={0.8} />
              <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border)" />
          <XAxis
            dataKey="displayDate"
            stroke="var(--color-text-secondary)"
            style={{ fontSize: '12px' }}
          />
          <YAxis
            stroke="var(--color-text-secondary)"
            style={{ fontSize: '12px' }}
          />
          <Tooltip
            contentStyle={{
              backgroundColor: 'var(--color-surface)',
              border: '1px solid var(--color-border)',
              borderRadius: '0.5rem',
              padding: '0.75rem',
            }}
            labelStyle={{ color: 'var(--color-text-primary)', fontWeight: 600 }}
          />
          {showLegend && <Legend />}
          <Area
            type="monotone"
            dataKey="views"
            stroke="var(--color-accent)"
            fillOpacity={1}
            fill="url(#colorViews)"
            name="Total Views"
            strokeWidth={2}
          />
          <Area
            type="monotone"
            dataKey="unique_visitors"
            stroke="#10b981"
            fillOpacity={1}
            fill="url(#colorVisitors)"
            name="Unique Visitors"
            strokeWidth={2}
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}
