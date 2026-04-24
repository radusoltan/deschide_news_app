'use client';

import { useCallback, useEffect, useState } from 'react';
import { fetchEscalations, fetchStats } from '@/app/actions/escalations';
import type {
  Escalation,
  EscalationCategoryName,
  EscalationCategoryValue,
  EscalationStatsPayload,
  EscalationStatus,
} from '@/lib/api/escalations';
import { ESCALATION_CATEGORY_LABEL } from '@/lib/api/escalations';
import { useMercureEscalations } from '@/lib/hooks/useMercureEscalations';
import { EscalationDetailPanel } from './EscalationDetailPanel';

const STATUS_OPTIONS: Array<{ value: EscalationStatus; label: string }> = [
  { value: 'pending', label: 'În așteptare' },
  { value: 'approved', label: 'Aprobate' },
  { value: 'rejected', label: 'Respinse' },
  { value: 'expired', label: 'Expirate' },
];

const CATEGORY_TO_VALUE: Record<EscalationCategoryName, EscalationCategoryValue> = {
  CATEGORY_1_NUCLEAR_WAR: 'categ_1',
  CATEGORY_2_HEAD_OF_STATE_DEATH: 'categ_2',
  CATEGORY_3_NBC_ATTACK: 'categ_3',
  CATEGORY_4_COUP: 'categ_4',
  CATEGORY_5_MASS_CASUALTIES: 'categ_5',
  CATEGORY_6_CRIMINAL_ACCUSATION: 'categ_6',
  CATEGORY_7_PRE_CEC_ELECTORAL: 'categ_7',
  FAMILY_A_CHURCH: 'family_a',
  FAMILY_B_EU_NATO_RUSSIA: 'family_b',
  FAMILY_C_TRANSNISTRIA_GAGAUZIA: 'family_c',
  FAMILY_D_CEC_PARTY_LEADERS: 'family_d',
};

const PAGE_SIZE = 20;

type FilterState = {
  status: EscalationStatus;
  category: EscalationCategoryValue | 'all';
  page: number;
};

/**
 * Escalation queue root component (Sprint 55 T55.13).
 *
 * Loads the pending list + stats on mount, refreshes both via Mercure events
 * (never by polling — see audit D12 + T55.12 rate-limit cost). On any
 * Mercure event we simply re-fetch the list + stats using the currently
 * selected filter; this keeps the state single-source-of-truth (backend) and
 * avoids optimistic UI drift against the authoritative envelope responses.
 */
export function EscalationQueue() {
  const [filter, setFilter] = useState<FilterState>({ status: 'pending', category: 'all', page: 1 });
  const [items, setItems] = useState<Escalation[]>([]);
  const [stats, setStats] = useState<EscalationStatsPayload | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadList = useCallback(async () => {
    setLoading(true);
    const categoryParam = filter.category === 'all' ? undefined : filter.category;
    const result = await fetchEscalations({
      status: filter.status,
      category: categoryParam,
      page: filter.page,
      limit: PAGE_SIZE,
    });
    if (result.error) {
      setError(result.error);
      setItems([]);
    } else {
      setError(null);
      setItems(result.data?.items ?? []);
    }
    setLoading(false);
  }, [filter.status, filter.category, filter.page]);

  const loadStats = useCallback(async () => {
    const result = await fetchStats();
    if (!result.error && result.data) {
      setStats(result.data);
    }
  }, []);

  const refreshAll = useCallback(() => {
    loadList();
    loadStats();
  }, [loadList, loadStats]);

  // Both initial loaders set `loading=true` synchronously, which trips the
  // `react-hooks/set-state-in-effect` rule. Defer via setTimeout 0 so the
  // setState call runs after the effect body returns. Matches the pattern
  // used in useMercureEscalations for the same reason.
  useEffect(() => {
    const id = setTimeout(() => { loadList(); }, 0);
    return () => clearTimeout(id);
  }, [loadList]);

  useEffect(() => {
    const id = setTimeout(() => { loadStats(); }, 0);
    return () => clearTimeout(id);
  }, [loadStats]);

  // Event-driven refresh — zero polling against the rate-limited list endpoint.
  const mercure = useMercureEscalations({
    onEvent: () => {
      refreshAll();
    },
  });

  const changeStatus = (status: EscalationStatus) => {
    setFilter((prev) => ({ ...prev, status, page: 1 }));
    setExpandedId(null);
  };

  const changeCategory = (categoryValue: EscalationCategoryValue | 'all') => {
    setFilter((prev) => ({ ...prev, category: categoryValue, page: 1 }));
    setExpandedId(null);
  };

  const formatDate = (iso: string | null): string => {
    if (!iso) return '—';
    try {
      return new Date(iso).toLocaleString('ro-RO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch {
      return iso;
    }
  };

  // Ticker state incremented every 30s so all countdown strings re-render
  // on a regular cadence. The timestamp is captured synchronously on each
  // render and then used as a pure reference for relative formatting —
  // keeps react-hooks/purity happy (no Date.now() call during render).
  const [nowMs, setNowMs] = useState(() => Date.now());
  useEffect(() => {
    const tick = setInterval(() => setNowMs(Date.now()), 30_000);
    return () => clearInterval(tick);
  }, []);

  const countdown = (iso: string | null): string => {
    if (!iso) return '—';
    const target = new Date(iso).getTime();
    if (Number.isNaN(target)) return '—';
    const ms = target - nowMs;
    if (ms <= 0) return 'expirat';
    const mins = Math.floor(ms / 60000);
    if (mins >= 60) {
      return `${Math.floor(mins / 60)}h ${mins % 60}m`;
    }
    return `${mins}m`;
  };

  const statusLabel = (status: FilterState['status']): string =>
    STATUS_OPTIONS.find((o) => o.value === status)?.label ?? status;

  return (
    <div>
      {/* Header */}
      <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-primary dark:text-primary-dark">
            Coada de escaladare editorială
          </h1>
          <p className="mt-1 text-sm text-secondary dark:text-gray-400">
            Revizuiește afirmațiile escaladate către decizie umană (ADR-020 D7).
          </p>
          {stats && (
            <p className="mt-1 text-sm font-medium text-primary dark:text-primary-dark">
              {stats.pending_total} în așteptare
              {Object.entries(stats.pending_by_category).slice(0, 4).map(([name, count]) => (
                <span key={name} className="ml-3 text-xs text-secondary dark:text-gray-400">
                  {ESCALATION_CATEGORY_LABEL[name as EscalationCategoryName] ?? name}:{' '}
                  <span className="font-semibold">{count}</span>
                </span>
              ))}
            </p>
          )}
        </div>

        <div className="flex items-center gap-2">
          <MercureStatusIndicator
            status={mercure.status}
            error={mercure.error}
            onReconnect={mercure.reconnect}
          />
          <button
            onClick={refreshAll}
            disabled={loading}
            className="px-3 py-1.5 text-xs font-medium text-primary bg-surface border border-gray-300 hover:bg-surface-sunken rounded disabled:opacity-50 dark:text-primary-dark dark:bg-surface-dark dark:border-gray-600 dark:hover:bg-gray-700"
          >
            Reîncarcă
          </button>
        </div>
      </div>

      {/* Filters */}
      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div className="flex gap-1">
          {STATUS_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              onClick={() => changeStatus(opt.value)}
              className={`px-3 py-1.5 text-xs font-medium rounded transition ${
                filter.status === opt.value
                  ? 'bg-blue-600 text-white'
                  : 'bg-surface text-primary border border-gray-300 hover:bg-surface-sunken dark:bg-surface-dark dark:text-primary-dark dark:border-gray-600 dark:hover:bg-gray-700'
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>

        <select
          value={filter.category}
          onChange={(e) => changeCategory(e.target.value as EscalationCategoryValue | 'all')}
          className="ml-auto px-3 py-1.5 text-xs font-medium rounded border border-gray-300 bg-surface text-primary dark:bg-surface-dark dark:text-primary-dark dark:border-gray-600"
        >
          <option value="all">Toate categoriile</option>
          {(Object.keys(ESCALATION_CATEGORY_LABEL) as EscalationCategoryName[]).map((name) => (
            <option key={name} value={CATEGORY_TO_VALUE[name]}>
              {ESCALATION_CATEGORY_LABEL[name]}
            </option>
          ))}
        </select>
      </div>

      {/* List error */}
      {error && (
        <div className="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
          {error}
        </div>
      )}

      {/* Loading / empty / list */}
      {loading && (
        <div className="text-center py-10 text-secondary dark:text-gray-400">
          Se încarcă coada…
        </div>
      )}

      {!loading && items.length === 0 && !error && (
        <div className="text-center py-10 border border-dashed border-gray-300 dark:border-gray-700 rounded text-secondary dark:text-gray-400">
          Nicio escaladare cu statusul „{statusLabel(filter.status)}&rdquo;.
        </div>
      )}

      {!loading && items.length > 0 && (
        <div className="space-y-2">
          {items.map((escalation) => {
            const isExpanded = expandedId === escalation.id;
            const categoryLabel = ESCALATION_CATEGORY_LABEL[escalation.category_name] ?? escalation.category_name;

            return (
              <div
                key={escalation.id}
                className="bg-surface dark:bg-surface-dark border border-gray-200 dark:border-gray-700 rounded overflow-hidden"
              >
                <button
                  type="button"
                  onClick={() => setExpandedId(isExpanded ? null : escalation.id)}
                  className="w-full p-3 text-left hover:bg-surface-sunken dark:hover:bg-gray-700/50 transition"
                >
                  <div className="flex items-center gap-3 flex-wrap">
                    <span className="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">
                      {categoryLabel}
                    </span>
                    {escalation.decision && (
                      <span className="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                        {escalation.decision}
                      </span>
                    )}
                    <span className="flex-1 text-sm font-medium text-primary dark:text-primary-dark truncate">
                      {escalation.article_snapshot.title ?? `Escaladare #${escalation.id}`}
                    </span>
                    <span className="text-xs text-secondary dark:text-gray-400">
                      {filter.status === 'pending' ? `Expiră în ${countdown(escalation.expires_at)}` : formatDate(escalation.decided_at ?? escalation.created_at)}
                    </span>
                    <span className="text-xs text-gray-400 dark:text-gray-500">
                      {isExpanded ? '▲' : '▼'}
                    </span>
                  </div>
                </button>

                {isExpanded && (
                  <EscalationDetailPanel
                    escalation={escalation}
                    onActionDone={refreshAll}
                  />
                )}
              </div>
            );
          })}
        </div>
      )}

      {/* Pagination */}
      {items.length >= PAGE_SIZE && (
        <div className="mt-4 flex justify-center gap-2">
          <button
            onClick={() => setFilter((prev) => ({ ...prev, page: Math.max(1, prev.page - 1) }))}
            disabled={filter.page <= 1}
            className="px-3 py-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40 hover:bg-surface-sunken dark:hover:bg-gray-700 text-primary dark:text-primary-dark"
          >
            Anterior
          </button>
          <span className="px-3 py-1.5 text-xs text-secondary dark:text-gray-400">
            Pagina {filter.page}
          </span>
          <button
            onClick={() => setFilter((prev) => ({ ...prev, page: prev.page + 1 }))}
            disabled={items.length < PAGE_SIZE}
            className="px-3 py-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40 hover:bg-surface-sunken dark:hover:bg-gray-700 text-primary dark:text-primary-dark"
          >
            Următor
          </button>
        </div>
      )}
    </div>
  );
}

interface MercureStatusIndicatorProps {
  status: 'connecting' | 'connected' | 'disconnected' | 'error';
  error: string | null;
  onReconnect: () => void;
}

function MercureStatusIndicator({ status, error, onReconnect }: MercureStatusIndicatorProps) {
  if (status === 'connected') {
    return (
      <span
        className="inline-flex items-center gap-1.5 text-xs text-green-700 dark:text-green-400"
        title="Evenimente live prin Mercure"
      >
        <span className="inline-block w-2 h-2 rounded-full bg-green-500 animate-pulse" />
        Live
      </span>
    );
  }
  if (status === 'connecting') {
    return (
      <span className="inline-flex items-center gap-1.5 text-xs text-secondary dark:text-gray-400">
        <span className="inline-block w-2 h-2 rounded-full bg-yellow-500" />
        Se conectează…
      </span>
    );
  }
  return (
    <button
      type="button"
      onClick={onReconnect}
      className="inline-flex items-center gap-1.5 text-xs text-amber-700 hover:text-amber-900 dark:text-amber-400 dark:hover:text-amber-300"
      title={error ?? 'Click pentru reconectare manuală'}
    >
      <span className="inline-block w-2 h-2 rounded-full bg-amber-500" />
      Mercure: {error ?? 'deconectat'} · reconectează
    </button>
  );
}
