'use client';

import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';

interface ClicksOverTimeChartProps {
  data: Array<{
    date: string;
    clicks: number;
  }>;
}

export default function ClicksOverTimeChart({
  data,
}: ClicksOverTimeChartProps) {
  if (!data || data.length === 0) {
    return (
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          Clicuri în Timp (Ultimele 30 Zile)
        </h3>
        <div className="h-64 flex items-center justify-center bg-gray-50 dark:bg-gray-700/50 rounded-lg">
          <p className="text-gray-500 dark:text-gray-400">
            Nu există date disponibile
          </p>
        </div>
      </div>
    );
  }

  // Format dates for display
  const formattedData = data.map((item) => ({
    ...item,
    displayDate: new Date(item.date).toLocaleDateString('ro-RO', {
      month: 'short',
      day: 'numeric',
    }),
  }));

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
          Clicuri în Timp (Ultimele 30 Zile)
        </h3>
        <div className="flex items-center gap-2">
          <div className="w-3 h-3 rounded-full bg-blue-500"></div>
          <span className="text-sm text-gray-600 dark:text-gray-400">
            Clicuri Zilnice
          </span>
        </div>
      </div>

      <ResponsiveContainer width="100%" height={300}>
        <AreaChart
          data={formattedData}
          margin={{ top: 10, right: 30, left: 0, bottom: 0 }}
        >
          <defs>
            <linearGradient id="colorClicks" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.8} />
              <stop offset="95%" stopColor="#3b82f6" stopOpacity={0} />
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
          <XAxis
            dataKey="displayDate"
            stroke="#6b7280"
            style={{ fontSize: '12px' }}
          />
          <YAxis stroke="#6b7280" style={{ fontSize: '12px' }} />
          <Tooltip
            contentStyle={{
              backgroundColor: '#fff',
              border: '1px solid #e5e7eb',
              borderRadius: '0.5rem',
              padding: '0.75rem',
            }}
            labelStyle={{ color: '#111827', fontWeight: 600 }}
          />
          <Area
            type="monotone"
            dataKey="clicks"
            stroke="#3b82f6"
            fillOpacity={1}
            fill="url(#colorClicks)"
            strokeWidth={2}
          />
        </AreaChart>
      </ResponsiveContainer>

      {/* Summary Stats */}
      <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 grid grid-cols-3 gap-4">
        <div className="text-center">
          <p className="text-xs text-gray-500 dark:text-gray-400">Total</p>
          <p className="text-lg font-bold text-gray-900 dark:text-white">
            {data.reduce((sum, day) => sum + day.clicks, 0).toLocaleString('ro-RO')}
          </p>
        </div>
        <div className="text-center">
          <p className="text-xs text-gray-500 dark:text-gray-400">Medie/Zi</p>
          <p className="text-lg font-bold text-gray-900 dark:text-white">
            {Math.round(
              data.reduce((sum, day) => sum + day.clicks, 0) / data.length
            ).toLocaleString('ro-RO')}
          </p>
        </div>
        <div className="text-center">
          <p className="text-xs text-gray-500 dark:text-gray-400">Maxim</p>
          <p className="text-lg font-bold text-gray-900 dark:text-white">
            {Math.max(...data.map((day) => day.clicks)).toLocaleString('ro-RO')}
          </p>
        </div>
      </div>
    </div>
  );
}
