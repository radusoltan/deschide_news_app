'use client';

import { useState, useTransition } from 'react';
import {
  fetchBriefings,
  type TopicBriefingItem,
} from '@/app/actions/briefings';

type Cadence = 'all' | 'hourly' | 'daily' | 'weekly';

const CADENCE_TABS: { value: Cadence; label: string }[] = [
  { value: 'all', label: 'Toate' },
  { value: 'hourly', label: 'Orar' },
  { value: 'daily', label: 'Zilnic' },
  { value: 'weekly', label: 'Săptămânal' },
];

const STATUS_COLORS: Record<string, string> = {
  pending:
    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
  generating:
    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  draft:
    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
  polished:
    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
  published:
    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
  failed:
    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
};

const CADENCE_BADGES: Record<string, string> = {
  hourly:
    'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
  daily:
    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  weekly:
    'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',
};

export function BriefingPreviewPanel() {
  const [cadence, setCadence] = useState<Cadence>('all');
  const [briefings, setBriefings] = useState<TopicBriefingItem[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [isPending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [topicFilter, setTopicFilter] = useState('');
  const [initialized, setInitialized] = useState(false);

  const loadBriefings = (selectedCadence: Cadence) => {
    startTransition(async () => {
      setError(null);
      const result = await fetchBriefings(
        selectedCadence === 'all' ? undefined : selectedCadence,
      );

      if (result.error) {
        setError(result.error);
      } else {
        setBriefings(result.items);
        setTotalItems(result.totalItems);
      }
    });
  };

  // Load on first render
  if (!initialized) {
    setInitialized(true);
    loadBriefings(cadence);
  }

  const handleCadenceChange = (newCadence: Cadence) => {
    setCadence(newCadence);
    loadBriefings(newCadence);
  };

  const filteredBriefings = topicFilter
    ? briefings.filter((b) =>
        b.topic?.title?.toLowerCase().includes(topicFilter.toLowerCase()),
      )
    : briefings;

  return (
    <div className="space-y-4">
      {/* Cadence tabs */}
      <div className="flex flex-wrap gap-2">
        {CADENCE_TABS.map((tab) => (
          <button
            key={tab.value}
            type="button"
            onClick={() => handleCadenceChange(tab.value)}
            className={`px-4 py-2 text-sm rounded-lg border transition-colors ${
              cadence === tab.value
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Topic filter */}
      <div className="flex items-center gap-3">
        <input
          type="text"
          placeholder="Filtrează după topic..."
          value={topicFilter}
          onChange={(e) => setTopicFilter(e.target.value)}
          className="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-blue-500 focus:border-blue-500 w-64"
        />
        <span className="text-sm text-secondary dark:text-gray-400">
          {totalItems} briefing{totalItems !== 1 ? 's' : ''} total
        </span>
      </div>

      {/* Error state */}
      {error && (
        <div className="p-4 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm">
          {error}
        </div>
      )}

      {/* Loading state */}
      {isPending && (
        <div className="space-y-3">
          {[1, 2, 3].map((i) => (
            <div
              key={i}
              className="animate-pulse bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4"
            >
              <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2" />
              <div className="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2" />
            </div>
          ))}
        </div>
      )}

      {/* Empty state */}
      {!isPending && !error && filteredBriefings.length === 0 && (
        <div className="text-center py-12 text-secondary dark:text-gray-400">
          <p className="text-lg font-medium">
            Niciun briefing{' '}
            {cadence !== 'all' && `(${CADENCE_TABS.find((t) => t.value === cadence)?.label})`}
          </p>
          <p className="mt-1 text-sm">
            Briefingurile sunt generate automat pe baza comunicatelor de
            presă asociate topicurilor.
          </p>
        </div>
      )}

      {/* Briefing cards */}
      {!isPending &&
        filteredBriefings.map((briefing) => (
          <BriefingCard
            key={briefing.id}
            briefing={briefing}
            expanded={expandedId === briefing.id}
            onToggle={() =>
              setExpandedId(expandedId === briefing.id ? null : briefing.id)
            }
          />
        ))}
    </div>
  );
}

function BriefingCard({
  briefing,
  expanded,
  onToggle,
}: {
  briefing: TopicBriefingItem;
  expanded: boolean;
  onToggle: () => void;
}) {
  const statusColor = STATUS_COLORS[briefing.status] ?? STATUS_COLORS.pending;
  const cadenceColor = CADENCE_BADGES[briefing.cadence] ?? CADENCE_BADGES.daily;

  const createdDate = new Date(briefing.createdAt);
  const timeAgo = getTimeAgo(createdDate);

  return (
    <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
      {/* Header (always visible) */}
      <button
        type="button"
        onClick={onToggle}
        className="w-full text-left p-4 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors"
      >
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2 mb-1 flex-wrap">
              <span
                className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${cadenceColor}`}
              >
                {briefing.cadence}
              </span>
              <span
                className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColor}`}
              >
                {briefing.status}
              </span>
              {briefing.claudePolished && (
                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                  Claude polished
                </span>
              )}
            </div>
            <h3 className="text-sm font-semibold text-primary dark:text-white truncate">
              {briefing.title ?? 'Briefing fără titlu'}
            </h3>
            <p className="text-xs text-secondary dark:text-gray-400 mt-0.5">
              Topic: {briefing.topic?.title ?? 'N/A'} | {briefing.prCount} PR
              {briefing.prCount !== 1 && 's'} | {timeAgo}
            </p>
          </div>
          <svg
            className={`w-5 h-5 text-gray-400 transition-transform ${expanded ? 'rotate-180' : ''}`}
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M19 9l-7 7-7-7"
            />
          </svg>
        </div>
        {briefing.summaryShort && (
          <p className="text-sm text-secondary dark:text-gray-400 mt-2 line-clamp-2">
            {briefing.summaryShort}
          </p>
        )}
      </button>

      {/* Expanded content */}
      {expanded && (
        <div className="border-t border-gray-200 dark:border-gray-700 p-4 space-y-4">
          {briefing.summaryLong && (
            <div>
              <h4 className="text-xs font-semibold uppercase text-secondary dark:text-gray-400 mb-1">
                Rezumat detaliat
              </h4>
              <p className="text-sm text-primary dark:text-gray-200">
                {briefing.summaryLong}
              </p>
            </div>
          )}

          {briefing.keyFacts && briefing.keyFacts.length > 0 && (
            <div>
              <h4 className="text-xs font-semibold uppercase text-secondary dark:text-gray-400 mb-1">
                Fapte cheie
              </h4>
              <ul className="space-y-1">
                {briefing.keyFacts.map((fact, i) => (
                  <li
                    key={i}
                    className="text-sm text-primary dark:text-gray-200 flex items-start gap-2"
                  >
                    <span className="text-blue-500 mt-0.5 flex-shrink-0">
                      &#8226;
                    </span>
                    {fact}
                  </li>
                ))}
              </ul>
            </div>
          )}

          {briefing.whyItMatters && (
            <div>
              <h4 className="text-xs font-semibold uppercase text-secondary dark:text-gray-400 mb-1">
                De ce contează
              </h4>
              <p className="text-sm text-primary dark:text-gray-200">
                {briefing.whyItMatters}
              </p>
            </div>
          )}

          <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-secondary dark:text-gray-500 pt-2 border-t border-gray-100 dark:border-gray-700">
            <span>
              Perioadă: {new Date(briefing.periodFrom).toLocaleDateString('ro-RO')}{' '}
              &ndash; {new Date(briefing.periodTo).toLocaleDateString('ro-RO')}
            </span>
            {briefing.generatedAt && (
              <span>
                Generat: {new Date(briefing.generatedAt).toLocaleString('ro-RO')}
              </span>
            )}
            <span>ID: #{briefing.id}</span>
          </div>
        </div>
      )}
    </div>
  );
}

function getTimeAgo(date: Date): string {
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60_000);

  if (diffMins < 1) return 'acum';
  if (diffMins < 60) return `acum ${diffMins}m`;

  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `acum ${diffHours}h`;

  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 7) return `acum ${diffDays}z`;

  return date.toLocaleDateString('ro-RO');
}
