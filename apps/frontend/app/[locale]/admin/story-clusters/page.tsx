'use client';

import { Fragment, useEffect, useState, useCallback } from 'react';
import {
  fetchStoryClusters,
  fetchStoryClusterDetail,
  searchStoryClusters,
  updateClusterStatus,
  updateClusterBoost,
  promoteCluster,
  regenerateSummary,
  removePressReleaseFromCluster,
  getAutoPromoteThreshold,
  setAutoPromoteThreshold,
  fetchCurationSuggestions,
  acceptCurationSuggestion,
  rejectCurationSuggestion,
  type StoryClusterItem,
  type StoryClusterDetail,
  type CurationSuggestionItem,
} from '@/app/actions/story-clusters';

const STATUS_COLORS: Record<string, string> = {
  auto: 'bg-gray-100 text-gray-800 dark:bg-gray-700/30 dark:text-gray-300',
  reviewed: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
  approved: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
  rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
  promoted: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
  archived: 'bg-gray-200 text-gray-600 dark:bg-gray-600/30 dark:text-gray-400',
};

const STATUS_LABELS: Record<string, string> = {
  auto: 'Auto',
  reviewed: 'Reviewed',
  approved: 'Approved',
  rejected: 'Rejected',
  promoted: 'Promoted',
  archived: 'Archived',
};

function ScoreBar({ score }: { score: number }) {
  const pct = Math.min(100, Math.round(score * 100));
  const color =
    score >= 0.7
      ? 'bg-green-500 dark:bg-green-400'
      : score >= 0.4
        ? 'bg-yellow-500 dark:bg-yellow-400'
        : 'bg-gray-400 dark:bg-gray-500';

  return (
    <div className="flex items-center gap-2">
      <div className="w-20 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
        <div className={`h-full rounded-full ${color}`} style={{ width: `${pct}%` }} />
      </div>
      <span className="text-xs font-mono tabular-nums">{score.toFixed(2)}</span>
    </div>
  );
}

function SourceBadge({ count }: { count: number }) {
  const color =
    count >= 3
      ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
      : count >= 2
        ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'
        : 'bg-gray-100 text-gray-600 dark:bg-gray-700/30 dark:text-gray-400';

  return (
    <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ${color}`}>
      {count} {count === 1 ? 'source' : 'sources'}
    </span>
  );
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('ro-RO', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

const TYPE_ICONS: Record<string, string> = {
  merge: '🔀',
  archive: '📦',
  retag: '🏷️',
};

const TYPE_LABELS: Record<string, string> = {
  merge: 'Fuzionare',
  archive: 'Arhivare',
  retag: 'Recategorizare',
};

function CurationPanel({
  onToast,
}: {
  onToast: (msg: string, type: 'success' | 'error') => void;
}) {
  const [suggestions, setSuggestions] = useState<CurationSuggestionItem[]>([]);
  const [count, setCount] = useState(0);
  const [collapsed, setCollapsed] = useState(true);
  const [processingId, setProcessingId] = useState<number | null>(null);

  const loadSuggestions = useCallback(async () => {
    const result = await fetchCurationSuggestions('pending');
    if (!result.error) {
      setSuggestions(result.items);
      setCount(result.totalItems);
    }
  }, []);

  useEffect(() => {
    loadSuggestions();
  }, [loadSuggestions]);

  const handleAccept = async (id: number) => {
    setProcessingId(id);
    const result = await acceptCurationSuggestion(id);
    if (result.success) {
      onToast('Sugestie acceptată', 'success');
      setSuggestions((prev) => prev.filter((s) => s.id !== id));
      setCount((c) => c - 1);
    } else {
      onToast(result.error || 'Eroare', 'error');
    }
    setProcessingId(null);
  };

  const handleReject = async (id: number) => {
    setProcessingId(id);
    const result = await rejectCurationSuggestion(id);
    if (result.success) {
      onToast('Sugestie respinsă', 'success');
      setSuggestions((prev) => prev.filter((s) => s.id !== id));
      setCount((c) => c - 1);
    } else {
      onToast(result.error || 'Eroare', 'error');
    }
    setProcessingId(null);
  };

  return (
    <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
      <button
        onClick={() => setCollapsed(!collapsed)}
        className="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
      >
        <div className="flex items-center gap-2">
          <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
            Sugestii de curatoriat
          </h3>
          <span className={`inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full ${
            count > 0
              ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
              : 'bg-gray-100 text-gray-500 dark:bg-gray-700/30 dark:text-gray-400'
          }`}>
            {count}
          </span>
        </div>
        <span className="text-gray-400">{collapsed ? '▼' : '▲'}</span>
      </button>

      {!collapsed && (
        <div className="border-t border-gray-200 dark:border-gray-700">
          {suggestions.length === 0 ? (
            <p className="px-4 py-6 text-sm text-gray-500 dark:text-gray-400 text-center">
              Nicio sugestie în așteptare
            </p>
          ) : (
            <div className="divide-y divide-gray-100 dark:divide-gray-700">
              {suggestions.map((s) => (
                <div key={s.id} className="px-4 py-3">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="text-lg" title={TYPE_LABELS[s.type]}>{TYPE_ICONS[s.type]}</span>
                        <span className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">
                          {TYPE_LABELS[s.type]}
                        </span>
                        <div className="flex items-center gap-1">
                          <div className="w-16 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div
                              className="h-full bg-blue-500 rounded-full"
                              style={{ width: `${Math.round(s.confidence * 100)}%` }}
                            />
                          </div>
                          <span className="text-[10px] font-mono text-gray-400">
                            {(s.confidence * 100).toFixed(0)}%
                          </span>
                        </div>
                      </div>
                      {s.clusterHeadlines?.map((h, i) => (
                        <p key={i} className="text-sm text-gray-900 dark:text-white truncate">
                          {h}
                        </p>
                      ))}
                      <p className="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                        {s.reason}
                      </p>
                    </div>
                    <div className="flex gap-1.5 flex-shrink-0">
                      <button
                        onClick={() => handleAccept(s.id)}
                        disabled={processingId === s.id}
                        className="px-2.5 py-1 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded disabled:opacity-50 transition"
                      >
                        Acceptă
                      </button>
                      <button
                        onClick={() => handleReject(s.id)}
                        disabled={processingId === s.id}
                        className="px-2.5 py-1 text-xs font-medium text-red-700 bg-red-100 hover:bg-red-200 rounded disabled:opacity-50 dark:text-red-300 dark:bg-red-900/30 dark:hover:bg-red-900/50 transition"
                      >
                        Respinge
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export default function StoryClustersPage() {
  const [items, setItems] = useState<StoryClusterItem[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [expandedDetail, setExpandedDetail] = useState<StoryClusterDetail | null>(null);
  const [processingId, setProcessingId] = useState<number | null>(null);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);
  const [threshold, setThreshold] = useState<number>(0.7);
  const [thresholdInput, setThresholdInput] = useState<string>('0.70');
  const [showThresholdSettings, setShowThresholdSettings] = useState(false);
  const [savingThreshold, setSavingThreshold] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchActive, setSearchActive] = useState(false);
  const [searchLoading, setSearchLoading] = useState(false);

  useEffect(() => {
    getAutoPromoteThreshold().then((t) => {
      setThreshold(t);
      setThresholdInput(t.toFixed(2));
    });
  }, []);

  const handleSaveThreshold = async () => {
    const value = parseFloat(thresholdInput);
    if (isNaN(value) || value < 0 || value > 1) {
      setToast({ message: 'Pragul trebuie să fie între 0.00 și 1.00', type: 'error' });
      return;
    }
    setSavingThreshold(true);
    const result = await setAutoPromoteThreshold(value);
    if (result.success) {
      setThreshold(result.threshold ?? value);
      setToast({ message: `Prag auto-promote salvat: ${(result.threshold ?? value).toFixed(2)}`, type: 'success' });
      setShowThresholdSettings(false);
    } else {
      setToast({ message: result.error || 'Eroare', type: 'error' });
    }
    setSavingThreshold(false);
  };

  // Search handler with debounce
  useEffect(() => {
    if (searchQuery.trim() === '') {
      if (searchActive) {
        setSearchActive(false);
      }
      return;
    }

    const timer = setTimeout(async () => {
      setSearchLoading(true);
      setSearchActive(true);
      const result = await searchStoryClusters(searchQuery.trim());
      if (result.error) {
        setError(result.error);
      } else {
        setItems(result.items);
        setTotalItems(result.totalItems);
      }
      setSearchLoading(false);
    }, 300);

    return () => clearTimeout(timer);
  }, [searchQuery]); // eslint-disable-line react-hooks/exhaustive-deps

  const clearSearch = () => {
    setSearchQuery('');
    setSearchActive(false);
    setLoading(true);
  };

  const loadData = useCallback(async () => {
    setLoading(true);
    setError(null);
    const result = await fetchStoryClusters(
      statusFilter === 'all' ? undefined : statusFilter,
      page,
    );
    if (result.error) {
      setError(result.error);
    } else {
      setItems(result.items);
      setTotalItems(result.totalItems);
    }
    setLoading(false);
  }, [statusFilter, page]);

  useEffect(() => {
    // Skip normal loading when search is active
    if (searchActive) return;

    // Inline fetch avoids synchronous setState from loadData callback
    let active = true;
    fetchStoryClusters(
      statusFilter === 'all' ? undefined : statusFilter,
      page,
    ).then(result => {
      if (!active) return;
      if (result.error) {
        setError(result.error);
      } else {
        setItems(result.items);
        setTotalItems(result.totalItems);
      }
      setLoading(false);
    });
    return () => { active = false; };
  }, [statusFilter, page, searchActive]);

  useEffect(() => {
    if (toast) {
      const timer = setTimeout(() => setToast(null), 3000);
      return () => clearTimeout(timer);
    }
  }, [toast]);

  const handleExpand = async (id: number) => {
    if (expandedId === id) {
      setExpandedId(null);
      setExpandedDetail(null);
      return;
    }
    setExpandedId(id);
    const detail = await fetchStoryClusterDetail(id);
    setExpandedDetail(detail);
  };

  const handleStatusChange = async (id: number, status: string) => {
    setProcessingId(id);
    const result = await updateClusterStatus(id, status);
    if (result.success) {
      setToast({ message: `Status schimbat: ${STATUS_LABELS[status]}`, type: 'success' });
      loadData();
    } else {
      setToast({ message: result.error || 'Eroare', type: 'error' });
    }
    setProcessingId(null);
  };

  const handlePromote = async (id: number) => {
    setProcessingId(id);
    const result = await promoteCluster(id);
    if (result.success) {
      setToast({ message: 'Cluster promovat cu succes', type: 'success' });
      loadData();
    } else {
      setToast({ message: result.error || 'Eroare la promovare', type: 'error' });
    }
    setProcessingId(null);
  };

  const handleRegenerateSummary = async (id: number) => {
    setProcessingId(id);
    const result = await regenerateSummary(id);
    if (result.success) {
      setToast({ message: 'Rezumat AI solicitat — se generează...', type: 'success' });
    } else {
      setToast({ message: result.error || 'Eroare la regenerare', type: 'error' });
    }
    setProcessingId(null);
  };

  const handleRemovePR = async (clusterId: number, prId: number) => {
    if (!confirm('Elimini acest articol din cluster?')) return;

    const result = await removePressReleaseFromCluster(clusterId, prId);
    if (result.success) {
      setToast({ message: `Articol eliminat din cluster (${result.articleCount} rămase)`, type: 'success' });
      // Refresh expanded detail
      const detail = await fetchStoryClusterDetail(clusterId);
      setExpandedDetail(detail);
      loadData();
    } else {
      setToast({ message: result.error || 'Eroare la eliminare', type: 'error' });
    }
  };

  const handleBoostChange = async (id: number, boost: number) => {
    const result = await updateClusterBoost(id, boost);
    if (result.success) {
      setToast({ message: `Boost setat: ${boost}x`, type: 'success' });
      loadData();
    } else {
      setToast({ message: result.error || 'Eroare', type: 'error' });
    }
  };

  const totalPages = Math.ceil(totalItems / 20);

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Story Clusters</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {totalItems} clustere detectate &middot; Prag auto-promote: {threshold.toFixed(2)}
          </p>
        </div>
        <button
          onClick={() => setShowThresholdSettings(!showThresholdSettings)}
          className="px-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
        >
          Setări Threshold
        </button>
      </div>

      {/* Threshold Settings Panel */}
      {showThresholdSettings && (
        <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
          <h3 className="text-sm font-semibold text-gray-900 dark:text-white mb-2">Auto-Promote Threshold</h3>
          <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">
            Clusterele cu scor &ge; {threshold.toFixed(2)} vor fi promovate automat la PressRelease (pending review).
          </p>
          <div className="flex items-center gap-3">
            <input
              type="number"
              min="0"
              max="1"
              step="0.05"
              value={thresholdInput}
              onChange={(e) => setThresholdInput(e.target.value)}
              className="w-24 px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
            />
            <button
              onClick={handleSaveThreshold}
              disabled={savingThreshold}
              className="px-4 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
              {savingThreshold ? 'Se salvează...' : 'Salvează'}
            </button>
            <button
              onClick={() => setShowThresholdSettings(false)}
              className="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
              Anulează
            </button>
          </div>
        </div>
      )}

      {/* Toast */}
      {toast && (
        <div
          className={`fixed top-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-sm font-medium ${
            toast.type === 'success'
              ? 'bg-green-500 text-white'
              : 'bg-red-500 text-white'
          }`}
        >
          {toast.message}
        </div>
      )}

      {/* Curation Suggestions */}
      <CurationPanel
        onToast={(msg, type) => setToast({ message: msg, type })}
      />

      {/* Search */}
      <div className="relative">
        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg className="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <input
          type="text"
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          placeholder="Cauta clustere..."
          className="w-full pl-10 pr-10 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
        {searchQuery && (
          <button
            onClick={clearSearch}
            className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        )}
        {searchLoading && (
          <div className="absolute inset-y-0 right-8 flex items-center">
            <div className="animate-spin h-4 w-4 border-2 border-blue-500 border-t-transparent rounded-full" />
          </div>
        )}
      </div>

      {searchActive && (
        <div className="text-sm text-gray-500 dark:text-gray-400">
          {totalItems} rezultate pentru &laquo;{searchQuery}&raquo;
          <button onClick={clearSearch} className="ml-2 text-blue-600 dark:text-blue-400 hover:underline">
            Sterge cautarea
          </button>
        </div>
      )}

      {/* Filters */}
      <div className="flex gap-2">
        {['all', 'auto', 'reviewed', 'approved', 'rejected', 'promoted'].map((s) => (
          <button
            key={s}
            onClick={() => { setStatusFilter(s); setPage(1); }}
            className={`px-3 py-1.5 text-sm rounded-lg border transition-colors ${
              statusFilter === s
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
            }`}
          >
            {s === 'all' ? 'Toate' : STATUS_LABELS[s] ?? s}
          </button>
        ))}
      </div>

      {/* Error */}
      {error && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg text-sm">
          {error}
        </div>
      )}

      {/* Table */}
      <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
              <th className="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Headline</th>
              <th className="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-400 w-36">Score</th>
              <th className="text-center px-4 py-3 font-medium text-gray-600 dark:text-gray-400 w-24">Sources</th>
              <th className="text-center px-4 py-3 font-medium text-gray-600 dark:text-gray-400 w-24">Articles</th>
              <th className="text-center px-4 py-3 font-medium text-gray-600 dark:text-gray-400 w-24">Status</th>
              <th className="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-400 w-28">First Seen</th>
              <th className="text-center px-4 py-3 font-medium text-gray-600 dark:text-gray-400 w-20"></th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
            {loading ? (
              <tr>
                <td colSpan={7} className="px-4 py-12 text-center text-gray-500">
                  <div className="animate-pulse">Se încarcă...</div>
                </td>
              </tr>
            ) : items.length === 0 ? (
              <tr>
                <td colSpan={7} className="px-4 py-12 text-center text-gray-500">
                  Niciun cluster găsit
                </td>
              </tr>
            ) : (
              items.map((item) => (
                <Fragment key={item.id}>
                  <tr
                    className="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors"
                    onClick={() => handleExpand(item.id)}
                  >
                    <td className="px-4 py-3">
                      <div className="font-medium text-gray-900 dark:text-white line-clamp-2">
                        {item.primaryHeadline}
                      </div>
                      {item.topics && item.topics.length > 0 && (
                        <div className="flex gap-1 mt-1">
                          {item.topics.map((t) => (
                            <span
                              key={t.id}
                              className="text-xs px-1.5 py-0.5 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-300 rounded"
                            >
                              {t.title}
                            </span>
                          ))}
                        </div>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <ScoreBar score={item.importanceScore} />
                    </td>
                    <td className="px-4 py-3 text-center">
                      <SourceBadge count={item.sourceCount} />
                    </td>
                    <td className="px-4 py-3 text-center text-gray-600 dark:text-gray-400">
                      {item.articleCount}
                    </td>
                    <td className="px-4 py-3 text-center">
                      <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[item.status] ?? ''}`}>
                        {STATUS_LABELS[item.status] ?? item.status}
                      </span>
                      {item.status === 'promoted' && item.importanceScore >= threshold && (
                        <span className="ml-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                          auto
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                      {formatDate(item.firstSeenAt)}
                    </td>
                    <td className="px-4 py-3 text-center">
                      <span className="text-gray-400">{expandedId === item.id ? '▲' : '▼'}</span>
                    </td>
                  </tr>

                  {/* Expanded row */}
                  {expandedId === item.id && (
                    <tr key={`${item.id}-detail`}>
                      <td colSpan={7} className="px-4 py-4 bg-gray-50 dark:bg-gray-900/30">
                        {expandedDetail ? (
                          <div className="space-y-4">
                            {/* AI Summary Badge */}
                            <div className="flex items-center gap-2">
                              {expandedDetail.summaryShort ? (
                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                  AI Generated
                                </span>
                              ) : (
                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-700/30 dark:text-gray-400">
                                  No Summary
                                </span>
                              )}
                              <button
                                onClick={(e) => { e.stopPropagation(); handleRegenerateSummary(item.id); }}
                                disabled={processingId === item.id}
                                className="text-xs text-blue-600 dark:text-blue-400 hover:underline disabled:opacity-50"
                              >
                                {processingId === item.id ? 'Se generează...' : 'Regenerează rezumat AI'}
                              </button>
                            </div>

                            {/* Summary Short */}
                            {expandedDetail.summaryShort && (
                              <div>
                                <h4 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Rezumat</h4>
                                <p className="text-sm font-medium text-gray-900 dark:text-white">{expandedDetail.summaryShort}</p>
                              </div>
                            )}

                            {/* Summary Medium */}
                            {expandedDetail.summaryMedium && (
                              <div>
                                <h4 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Context</h4>
                                <p className="text-sm text-gray-700 dark:text-gray-300">{expandedDetail.summaryMedium}</p>
                              </div>
                            )}

                            {/* Why It Matters */}
                            {expandedDetail.whyItMatters && (
                              <div className="border-l-4 border-blue-400 dark:border-blue-500 pl-3 bg-blue-50/50 dark:bg-blue-900/10 py-2 rounded-r">
                                <h4 className="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase mb-1">De ce contează</h4>
                                <p className="text-sm text-gray-700 dark:text-gray-300">{expandedDetail.whyItMatters}</p>
                              </div>
                            )}

                            {/* Key Facts */}
                            {expandedDetail.keyFacts && expandedDetail.keyFacts.length > 0 && (
                              <div>
                                <h4 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Fapte cheie</h4>
                                <ul className="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 space-y-1">
                                  {expandedDetail.keyFacts.map((f, i) => (
                                    <li key={i}>{f}</li>
                                  ))}
                                </ul>
                              </div>
                            )}

                            {/* PressReleases */}
                            <div>
                              <h4 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">
                                Press Releases ({expandedDetail.pressReleases?.length ?? 0})
                              </h4>
                              <div className="space-y-2">
                                {(expandedDetail.pressReleases ?? []).map((pr) => (
                                  <div
                                    key={pr.id}
                                    className="flex items-center justify-between bg-white dark:bg-gray-800 px-3 py-2 rounded border border-gray-200 dark:border-gray-700"
                                  >
                                    <div className="flex-1 min-w-0">
                                      <div className="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {pr.title}
                                      </div>
                                      <div className="text-xs text-gray-500 dark:text-gray-400">
                                        {pr.sourceName ?? pr.sourceHostname ?? 'Unknown'} &middot; {pr.receivedAt ? formatDate(pr.receivedAt) : '—'}
                                      </div>
                                    </div>
                                    <div className="flex items-center gap-2 ml-2 shrink-0">
                                      {pr.sourceUrl && (
                                        <a
                                          href={pr.sourceUrl}
                                          target="_blank"
                                          rel="noopener noreferrer"
                                          className="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                                          onClick={(e) => e.stopPropagation()}
                                        >
                                          Source
                                        </a>
                                      )}
                                      <button
                                        onClick={(e) => {
                                          e.stopPropagation();
                                          if (expandedId) handleRemovePR(expandedId, pr.id);
                                        }}
                                        className="text-red-400 hover:text-red-600 dark:text-red-500 dark:hover:text-red-400 text-sm"
                                        title="Elimină din cluster"
                                      >
                                        &times;
                                      </button>
                                    </div>
                                  </div>
                                ))}
                              </div>
                            </div>

                            {/* Actions */}
                            <div className="flex items-center gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                              <button
                                onClick={() => handleStatusChange(item.id, 'approved')}
                                disabled={processingId === item.id}
                                className="px-3 py-1.5 text-xs bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
                              >
                                Approve
                              </button>
                              <button
                                onClick={() => handleStatusChange(item.id, 'rejected')}
                                disabled={processingId === item.id}
                                className="px-3 py-1.5 text-xs bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50"
                              >
                                Reject
                              </button>
                              {!item.promotedToPressRelease && (
                                <button
                                  onClick={() => handlePromote(item.id)}
                                  disabled={processingId === item.id}
                                  className="px-3 py-1.5 text-xs bg-purple-600 text-white rounded hover:bg-purple-700 disabled:opacity-50"
                                >
                                  Promote
                                </button>
                              )}
                              <div className="ml-auto flex items-center gap-2">
                                <label className="text-xs text-gray-500 dark:text-gray-400">Boost:</label>
                                <select
                                  value={item.editorialBoost}
                                  onChange={(e) => handleBoostChange(item.id, parseFloat(e.target.value))}
                                  className="text-xs border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300"
                                  onClick={(e) => e.stopPropagation()}
                                >
                                  <option value={0.5}>0.5x</option>
                                  <option value={1.0}>1.0x</option>
                                  <option value={1.5}>1.5x</option>
                                  <option value={2.0}>2.0x</option>
                                  <option value={3.0}>3.0x</option>
                                </select>
                              </div>
                            </div>
                          </div>
                        ) : (
                          <div className="text-sm text-gray-500 animate-pulse">Se încarcă detaliile...</div>
                        )}
                      </td>
                    </tr>
                  )}
                </Fragment>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination (hidden during search) */}
      {totalPages > 1 && !searchActive && (
        <div className="flex items-center justify-between">
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Pagina {page} din {totalPages} ({totalItems} total)
          </p>
          <div className="flex gap-1">
            <button
              onClick={() => setPage(Math.max(1, page - 1))}
              disabled={page <= 1}
              className="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded disabled:opacity-50 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300"
            >
              ← Prev
            </button>
            <button
              onClick={() => setPage(Math.min(totalPages, page + 1))}
              disabled={page >= totalPages}
              className="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded disabled:opacity-50 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300"
            >
              Next →
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
