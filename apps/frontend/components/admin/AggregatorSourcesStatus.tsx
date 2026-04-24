'use client';

import { useEffect, useState } from 'react';

interface SourceStatus {
  source: string;
  lastRun: string;
  articlesFound: number;
  duplicatesSkipped: number;
  pendingReview: number;
  status: 'healthy' | 'error' | 'disabled';
}

export default function AggregatorSourcesStatus() {
  const [sources, setSources] = useState<SourceStatus[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch('/api/aggregator/stats')
      .then(r => r.ok ? r.json() : [])
      .then(data => { setSources(Array.isArray(data) ? data : []); setLoading(false); })
      .catch(() => setLoading(false));
  }, []);

  if (loading) return <div className="animate-pulse h-32 bg-gray-100 dark:bg-gray-800 rounded-lg" />;

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      {sources.map(s => (
        <div key={s.source} className="p-4 rounded-lg border border-gray-200 dark:border-gray-700">
          <div className="flex items-center justify-between mb-2">
            <span className="font-medium text-sm">{s.source}</span>
            <span className={`w-2 h-2 rounded-full ${s.status === 'healthy' ? 'bg-green-500' : s.status === 'error' ? 'bg-red-500' : 'bg-gray-400'}`} />
          </div>
          <div className="text-xs text-gray-500 dark:text-gray-400 space-y-1">
            <div>Last run: {s.lastRun ? new Date(s.lastRun).toLocaleString() : 'Never'}</div>
            <div>Found: {s.articlesFound} | Dupes: {s.duplicatesSkipped} | Pending: {s.pendingReview}</div>
          </div>
        </div>
      ))}
      {sources.length === 0 && <div className="col-span-full text-center text-gray-400 py-8">No aggregator stats yet. Run app:aggregator:run first.</div>}
    </div>
  );
}
