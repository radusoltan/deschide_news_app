'use client';

import { PieChart, Pie, Cell, ResponsiveContainer, Legend, Tooltip } from 'recharts';

interface DeviceTypesChartProps {
  data: Array<{
    deviceType: string;
    count: number;
  }>;
}

const COLORS = {
  mobile: '#3b82f6', // blue
  desktop: '#10b981', // green
  tablet: '#f59e0b', // amber
  other: '#6b7280', // gray
};

const DEVICE_LABELS: Record<string, string> = {
  mobile: 'Mobil',
  desktop: 'Desktop',
  tablet: 'Tabletă',
  other: 'Altele',
};

export default function DeviceTypesChart({ data }: DeviceTypesChartProps) {
  if (!data || data.length === 0) {
    return (
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          Tipuri de Dispozitive
        </h3>
        <div className="h-64 flex items-center justify-center bg-gray-50 dark:bg-gray-700/50 rounded-lg">
          <p className="text-gray-500 dark:text-gray-400">
            Nu există date disponibile
          </p>
        </div>
      </div>
    );
  }

  // Format data for pie chart
  const chartData = data.map((item) => ({
    name: DEVICE_LABELS[item.deviceType] || item.deviceType,
    value: item.count,
    fill:
      COLORS[item.deviceType as keyof typeof COLORS] || COLORS.other,
  }));

  const total = data.reduce((sum, item) => sum + item.count, 0);

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        Tipuri de Dispozitive
      </h3>

      <ResponsiveContainer width="100%" height={250}>
        <PieChart>
          <Pie
            data={chartData}
            cx="50%"
            cy="50%"
            labelLine={false}
            label={({ name, percent }) =>
              `${name}: ${((percent ?? 0) * 100).toFixed(0)}%`
            }
            outerRadius={80}
            fill="#8884d8"
            dataKey="value"
          >
            {chartData.map((entry, index) => (
              <Cell key={`cell-${index}`} fill={entry.fill} />
            ))}
          </Pie>
          <Tooltip
            contentStyle={{
              backgroundColor: '#fff',
              border: '1px solid #e5e7eb',
              borderRadius: '0.5rem',
              padding: '0.75rem',
            }}
          />
        </PieChart>
      </ResponsiveContainer>

      {/* Legend with counts */}
      <div className="mt-4 space-y-2">
        {data.map((item) => (
          <div
            key={item.deviceType}
            className="flex items-center justify-between text-sm"
          >
            <div className="flex items-center gap-2">
              <div
                className="w-3 h-3 rounded-full"
                style={{
                  backgroundColor:
                    COLORS[item.deviceType as keyof typeof COLORS] ||
                    COLORS.other,
                }}
              ></div>
              <span className="text-gray-700 dark:text-gray-300">
                {DEVICE_LABELS[item.deviceType] || item.deviceType}
              </span>
            </div>
            <div className="flex items-center gap-2">
              <span className="font-semibold text-gray-900 dark:text-white">
                {item.count.toLocaleString('ro-RO')}
              </span>
              <span className="text-gray-500 dark:text-gray-400">
                ({((item.count / total) * 100).toFixed(1)}%)
              </span>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
