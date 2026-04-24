'use client';

interface AggregatorBadgeProps {
  sourceType: string;
}

const SOURCE_CONFIG: Record<string, { icon: string; label: string; color: string }> = {
  google_news_rss: { icon: '📰', label: 'Google News', color: 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200' },
  google_alerts: { icon: '🔔', label: 'Alerts', color: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' },
  news_api: { icon: '📡', label: 'NewsAPI', color: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' },
  bing_news: { icon: '🔍', label: 'Bing', color: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200' },
  telegram: { icon: '✈️', label: 'Telegram', color: 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200' },
  facebook_rss: { icon: '👤', label: 'Facebook', color: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200' },
  direct_portal: { icon: '🌐', label: 'Portal', color: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' },
};

export default function AggregatorBadge({ sourceType }: AggregatorBadgeProps) {
  const config = SOURCE_CONFIG[sourceType] ?? { icon: '📰', label: sourceType, color: 'bg-gray-100 text-gray-800' };
  return (
    <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${config.color}`}>
      {config.icon} {config.label}
    </span>
  );
}
