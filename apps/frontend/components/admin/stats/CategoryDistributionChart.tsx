/**
 * Category Distribution Pie Chart Component
 * Displays article distribution across categories
 */

'use client';

import {
  PieChart,
  Pie,
  Cell,
  ResponsiveContainer,
  Tooltip,
  Legend,
} from 'recharts';

interface CategoryData {
  name: string;
  value: number;
  color?: string;
}

interface Props {
  data: CategoryData[];
  title?: string;
}

// Default color palette
const COLORS = [
  'var(--color-accent)', // blue
  '#10b981', // green
  '#f59e0b', // amber
  '#ef4444', // red
  '#8b5cf6', // purple
  '#ec4899', // pink
  '#06b6d4', // cyan
  '#f97316', // orange
  '#14b8a6', // teal
  '#6366f1', // indigo
];

// Custom tooltip component (moved outside to prevent re-creation on each render)
const CustomTooltip = ({ active, payload, total }: any) => {
  if (active && payload && payload.length) {
    const data = payload[0];
    const percentage = ((data.value / total) * 100).toFixed(1);
    return (
      <div className="bg-surface p-3 rounded-lg shadow-lg border border-gray-200">
        <p className="font-semibold text-primary">{data.name}</p>
        <p className="text-sm text-gray-600">
          {data.value} articles ({percentage}%)
        </p>
      </div>
    );
  }
  return null;
};

export function CategoryDistributionChart({
  data,
  title = 'Articles by Category'
}: Props) {
  if (!data || data.length === 0) {
    return (
      <div className="bg-surface p-6 rounded-lg shadow">
        <h3 className="text-lg font-semibold text-primary mb-4">{title}</h3>
        <div className="h-64 flex items-center justify-center bg-surface-sunken rounded-lg">
          <p className="text-secondary">No category data available</p>
        </div>
      </div>
    );
  }

  // Assign colors to categories
  const chartData = data.map((item, index) => ({
    ...item,
    color: item.color || COLORS[index % COLORS.length],
  }));

  // Calculate total
  const total = data.reduce((sum, item) => sum + item.value, 0);

  // Custom label
  const renderLabel = (entry: any) => {
    const percentage = ((entry.value / total) * 100).toFixed(0);
    return `${percentage}%`;
  };

  return (
    <div className="bg-surface p-6 rounded-lg shadow">
      <h3 className="text-lg font-semibold text-primary mb-4">{title}</h3>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Pie Chart */}
        <div>
          <ResponsiveContainer width="100%" height={300}>
            <PieChart>
              <Pie
                data={chartData}
                cx="50%"
                cy="50%"
                labelLine={false}
                label={renderLabel}
                outerRadius={100}
                fill="#8884d8"
                dataKey="value"
              >
                {chartData.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={entry.color} />
                ))}
              </Pie>
              <Tooltip content={<CustomTooltip total={total} />} />
            </PieChart>
          </ResponsiveContainer>
        </div>

        {/* Legend with detailed stats */}
        <div className="flex flex-col justify-center">
          <div className="space-y-2">
            {chartData.map((item, index) => {
              const percentage = ((item.value / total) * 100).toFixed(1);
              return (
                <div
                  key={index}
                  className="flex items-center justify-between p-3 bg-surface-sunken rounded-lg hover:bg-gray-100 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <div
                      className="w-4 h-4 rounded-full"
                      style={{ backgroundColor: item.color }}
                    ></div>
                    <span className="text-sm font-medium text-primary">
                      {item.name}
                    </span>
                  </div>
                  <div className="flex items-center gap-3">
                    <span className="text-sm font-semibold text-primary">
                      {item.value}
                    </span>
                    <span className="text-xs text-secondary w-12 text-right">
                      ({percentage}%)
                    </span>
                  </div>
                </div>
              );
            })}
          </div>

          {/* Total */}
          <div className="mt-4 pt-4 border-t border-gray-200">
            <div className="flex items-center justify-between">
              <span className="text-sm font-semibold text-gray-600">Total Articles:</span>
              <span className="text-lg font-bold text-primary">{total}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
