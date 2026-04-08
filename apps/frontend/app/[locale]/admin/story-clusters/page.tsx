'use client';

import { useEffect, useState, useCallback } from 'react';
import {
  fetchStoryClusters,
  fetchStoryClusterDetail,
  updateClusterStatus,
  updateClusterBoost,
  promoteCluster,
  regenerateSummary,
  type StoryClusterItem,
  type StoryClusterDetail,
} from '@/app/actions/story-clusters';

const STATUS_COLORS: Record<string, string> = {
  auto: 'bg-gray-100 text-gray-800 dark:bg-gray-700/30 dark:text-gray-300',
  reviewed: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
  approved: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
  rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
  promoted: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
};

const STATUS_LABELS: Record<string, string> = {
  auto: 'Auto',
  reviewed: 'Reviewed',
  approved: 'Approved',
  rejected: 'Rejected',
  promoted: 'Promoted',
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
    loadData();
  }, [loadData]);

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
            {totalItems} clustere detectate
          </p>
        </div>
      </div>

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
                <>
                  <tr
                    key={item.id}
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
                                        {pr.sourceHostname ?? 'Unknown'} &middot; {formatDate(pr.receivedAt)}
                                      </div>
                                    </div>
                                    {pr.sourceUrl && (
                                      <a
                                        href={pr.sourceUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-xs text-blue-600 dark:text-blue-400 ml-2 hover:underline"
                                        onClick={(e) => e.stopPropagation()}
                                      >
                                        Source
                                      </a>
                                    )}
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
                </>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
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
