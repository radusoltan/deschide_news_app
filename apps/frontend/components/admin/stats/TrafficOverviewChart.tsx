/**
 * Traffic Overview Chart Component
 * Displays site-wide traffic metrics with bar chart
 */

'use client';

import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
  Cell,
} from 'recharts';

interface TrafficDataPoint {
  date: string;
  total_visits: number;
  unique_visitors: number;
  new_visitors: number;
}

interface Props {
  data: TrafficDataPoint[];
  title?: string;
}

export function TrafficOverviewChart({
  data,
  title = 'Traffic Overview'
}: Props) {
  if (!data || data.length === 0) {
    return (
      <div className="bg-white p-6 rounded-lg shadow">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">{title}</h3>
        <div className="h-64 flex items-center justify-center bg-gray-50 rounded-lg">
          <p className="text-gray-500">No traffic data available</p>
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

  // Calculate totals for stats cards
  const totalVisits = data.reduce((sum, item) => sum + item.total_visits, 0);
  const avgUniqueVisitors = Math.round(
    data.reduce((sum, item) => sum + item.unique_visitors, 0) / data.length
  );
  const totalNewVisitors = data.reduce((sum, item) => sum + item.new_visitors, 0);

  return (
    <div className="bg-white p-6 rounded-lg shadow">
      <div className="flex items-center justify-between mb-6">
        <h3 className="text-lg font-semibold text-gray-900">{title}</h3>

        {/* Quick Stats */}
        <div className="flex items-center gap-6">
          <div className="text-center">
            <p className="text-2xl font-bold text-blue-600">
              {totalVisits.toLocaleString()}
            </p>
            <p className="text-xs text-gray-500">Total Visits</p>
          </div>
          <div className="text-center">
            <p className="text-2xl font-bold text-green-600">
              {avgUniqueVisitors.toLocaleString()}
            </p>
            <p className="text-xs text-gray-500">Avg Unique/Day</p>
          </div>
          <div className="text-center">
            <p className="text-2xl font-bold text-purple-600">
              {totalNewVisitors.toLocaleString()}
            </p>
            <p className="text-xs text-gray-500">New Visitors</p>
          </div>
        </div>
      </div>

      <ResponsiveContainer width="100%" height={350}>
        <BarChart
          data={formattedData}
          margin={{ top: 10, right: 30, left: 0, bottom: 0 }}
        >
          <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
          <XAxis
            dataKey="displayDate"
            stroke="#6b7280"
            style={{ fontSize: '12px' }}
          />
          <YAxis
            stroke="#6b7280"
            style={{ fontSize: '12px' }}
          />
          <Tooltip
            contentStyle={{
              backgroundColor: '#fff',
              border: '1px solid #e5e7eb',
              borderRadius: '0.5rem',
              padding: '0.75rem',
            }}
            labelStyle={{ color: '#111827', fontWeight: 600 }}
            cursor={{ fill: 'rgba(59, 130, 246, 0.1)' }}
          />
          <Legend />
          <Bar
            dataKey="total_visits"
            fill="#3b82f6"
            name="Total Visits"
            radius={[8, 8, 0, 0]}
          />
          <Bar
            dataKey="unique_visitors"
            fill="#10b981"
            name="Unique Visitors"
            radius={[8, 8, 0, 0]}
          />
          <Bar
            dataKey="new_visitors"
            fill="#8b5cf6"
            name="New Visitors"
            radius={[8, 8, 0, 0]}
          />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}
