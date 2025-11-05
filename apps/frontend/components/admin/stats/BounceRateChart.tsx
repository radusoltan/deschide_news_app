/**
 * Bounce Rate Chart Component
 * Displays bounce rate and average session duration trends
 */

'use client';

import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';

interface BounceRateDataPoint {
  date: string;
  bounce_rate: number;
  avg_session_duration: number;
}

interface Props {
  data: BounceRateDataPoint[];
  title?: string;
}

export function BounceRateChart({
  data,
  title = 'Engagement Metrics'
}: Props) {
  if (!data || data.length === 0) {
    return (
      <div className="bg-white p-6 rounded-lg shadow">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">{title}</h3>
        <div className="h-64 flex items-center justify-center bg-gray-50 rounded-lg">
          <p className="text-gray-500">No engagement data available</p>
        </div>
      </div>
    );
  }

  // Format dates and convert bounce rate to percentage
  const formattedData = data.map(item => ({
    displayDate: new Date(item.date).toLocaleDateString('ro-RO', {
      month: 'short',
      day: 'numeric'
    }),
    bounceRate: (item.bounce_rate * 100).toFixed(1),
    sessionMinutes: (item.avg_session_duration / 60).toFixed(1),
  }));

  // Calculate averages
  const avgBounceRate = (
    (data.reduce((sum, item) => sum + item.bounce_rate, 0) / data.length) * 100
  ).toFixed(1);

  const avgSessionDuration = Math.round(
    data.reduce((sum, item) => sum + item.avg_session_duration, 0) / data.length
  );

  const minutes = Math.floor(avgSessionDuration / 60);
  const seconds = avgSessionDuration % 60;

  return (
    <div className="bg-white p-6 rounded-lg shadow">
      <div className="flex items-center justify-between mb-6">
        <h3 className="text-lg font-semibold text-gray-900">{title}</h3>

        {/* Quick Stats */}
        <div className="flex items-center gap-6">
          <div className="text-center">
            <p className="text-2xl font-bold text-amber-600">
              {avgBounceRate}%
            </p>
            <p className="text-xs text-gray-500">Avg Bounce Rate</p>
          </div>
          <div className="text-center">
            <p className="text-2xl font-bold text-blue-600">
              {minutes}m {seconds}s
            </p>
            <p className="text-xs text-gray-500">Avg Session</p>
          </div>
        </div>
      </div>

      <ResponsiveContainer width="100%" height={300}>
        <LineChart
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
            yAxisId="left"
            stroke="#6b7280"
            style={{ fontSize: '12px' }}
            label={{ value: 'Bounce Rate (%)', angle: -90, position: 'insideLeft' }}
          />
          <YAxis
            yAxisId="right"
            orientation="right"
            stroke="#6b7280"
            style={{ fontSize: '12px' }}
            label={{ value: 'Session (min)', angle: 90, position: 'insideRight' }}
          />
          <Tooltip
            contentStyle={{
              backgroundColor: '#fff',
              border: '1px solid #e5e7eb',
              borderRadius: '0.5rem',
              padding: '0.75rem',
            }}
            labelStyle={{ color: '#111827', fontWeight: 600 }}
          />
          <Legend />
          <Line
            yAxisId="left"
            type="monotone"
            dataKey="bounceRate"
            stroke="#f59e0b"
            strokeWidth={2}
            name="Bounce Rate (%)"
            dot={{ r: 4 }}
            activeDot={{ r: 6 }}
          />
          <Line
            yAxisId="right"
            type="monotone"
            dataKey="sessionMinutes"
            stroke="#3b82f6"
            strokeWidth={2}
            name="Session Duration (min)"
            dot={{ r: 4 }}
            activeDot={{ r: 6 }}
          />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}
