'use client';

import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Cell,
} from 'recharts';

interface TopReferrersChartProps {
  data: Array<{
    referrer: string;
    count: number;
  }>;
}

const COLORS = ['var(--color-accent)', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

export default function TopReferrersChart({ data }: TopReferrersChartProps) {
  if (!data || data.length === 0) {
    return (
      <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
        <h3 className="text-lg font-semibold text-primary dark:text-primary-dark mb-4">
          Top Surse de Trafic
        </h3>
        <div className="h-64 flex items-center justify-center bg-surface-sunken dark:bg-gray-700/50 rounded-lg">
          <p className="text-secondary dark:text-gray-400">
            Nu există date disponibile
          </p>
        </div>
      </div>
    );
  }

  // Take only top 10 referrers
  const topReferrers = data.slice(0, 10);

  // Format referrer names (shorten long URLs)
  const chartData = topReferrers.map((item) => ({
    ...item,
    displayName:
      item.referrer === 'direct'
        ? 'Direct'
        : item.referrer.length > 30
        ? item.referrer.substring(0, 27) + '...'
        : item.referrer,
  }));

  const total = data.reduce((sum, item) => sum + item.count, 0);

  return (
    <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
      <h3 className="text-lg font-semibold text-primary dark:text-primary-dark mb-4">
        Top Surse de Trafic
      </h3>

      <ResponsiveContainer width="100%" height={250}>
        <BarChart
          data={chartData}
          margin={{ top: 10, right: 10, left: 10, bottom: 40 }}
        >
          <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border)" />
          <XAxis
            dataKey="displayName"
            stroke="var(--color-text-secondary)"
            style={{ fontSize: '11px' }}
            angle={-45}
            textAnchor="end"
            height={80}
          />
          <YAxis stroke="var(--color-text-secondary)" style={{ fontSize: '12px' }} />
          <Tooltip
            contentStyle={{
              backgroundColor: 'var(--color-surface)',
              border: '1px solid var(--color-border)',
              borderRadius: '0.5rem',
              padding: '0.75rem',
            }}
            labelFormatter={(value) => {
              const item = topReferrers.find(
                (r) => r.referrer.startsWith(value.toString())
              );
              return item?.referrer || value;
            }}
          />
          <Bar dataKey="count" radius={[8, 8, 0, 0]}>
            {chartData.map((entry, index) => (
              <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
            ))}
          </Bar>
        </BarChart>
      </ResponsiveContainer>

      {/* Top referrers list */}
      <div className="mt-4 space-y-2">
        <h4 className="text-sm font-medium text-primary dark:text-primary-dark">
          Top 5 Surse:
        </h4>
        {topReferrers.slice(0, 5).map((item, index) => (
          <div
            key={item.referrer}
            className="flex items-center justify-between text-sm"
          >
            <div className="flex items-center gap-2 flex-1 min-w-0">
              <div
                className="w-3 h-3 rounded-full flex-shrink-0"
                style={{ backgroundColor: COLORS[index % COLORS.length] }}
              ></div>
              <span
                className="text-primary dark:text-primary-dark truncate"
                title={item.referrer}
              >
                {item.referrer === 'direct' ? 'Direct' : item.referrer}
              </span>
            </div>
            <div className="flex items-center gap-2 flex-shrink-0">
              <span className="font-semibold text-primary dark:text-primary-dark">
                {item.count.toLocaleString('ro-RO')}
              </span>
              <span className="text-secondary dark:text-gray-400">
                ({((item.count / total) * 100).toFixed(1)}%)
              </span>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
