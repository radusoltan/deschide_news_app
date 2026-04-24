'use client';

import { useState, useTransition } from 'react';
import {
  approveEscalation,
  extendSla,
  rejectEscalation,
} from '@/app/actions/escalations';
import type { Escalation } from '@/lib/api/escalations';
import { ESCALATION_CATEGORY_LABEL } from '@/lib/api/escalations';
import { OriginGraphViz } from './OriginGraphViz';

interface EscalationDetailPanelProps {
  escalation: Escalation;
  onActionDone: () => void;
}

const EXTEND_PRESETS: Array<{ label: string; seconds: number }> = [
  { label: '+10 min', seconds: 600 },
  { label: '+30 min', seconds: 1800 },
  { label: '+1 oră', seconds: 3600 },
];

type FormError = {
  message: string;
  violations?: Array<{ field: string; message: string }>;
};

/**
 * Expanded row renderer for a single escalation. Shows the article snapshot +
 * verdict + origin graph + action form. Wires the three admin actions
 * (approve / reject / extend-sla) through the existing server-action layer.
 *
 * On any successful action the panel calls `onActionDone()` so the parent
 * queue re-fetches. Mercure will also fire a matching event (`decided` or
 * `extended`) — the refetch is still useful because Mercure publish is fail-
 * open and the action result is authoritative.
 */
export function EscalationDetailPanel({ escalation, onActionDone }: EscalationDetailPanelProps) {
  const [mode, setMode] = useState<'idle' | 'approve' | 'reject' | 'extend'>('idle');
  const [isPending, startTransition] = useTransition();
  const [error, setError] = useState<FormError | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  const [editorialNotes, setEditorialNotes] = useState('');
  const [publishAsArticle, setPublishAsArticle] = useState(false);
  const [rejectReason, setRejectReason] = useState('');

  const snapshot = escalation.article_snapshot;
  const verdictType = snapshot.verdict_type ?? 'n/a';
  const categoryLabel = ESCALATION_CATEGORY_LABEL[escalation.category_name] ?? escalation.category_name;

  const handleApprove = () => {
    setError(null);
    setSuccessMsg(null);
    startTransition(async () => {
      const result = await approveEscalation(escalation.id, publishAsArticle, editorialNotes.trim() || undefined);
      if (result.error) {
        setError({ message: result.error, violations: result.violations });
        return;
      }
      const publishStatus = result.data?.publish_dispatch?.status;
      setSuccessMsg(
        publishAsArticle && publishStatus !== 'dispatched'
          ? `Aprobat. Publicarea automată: ${publishStatus ?? 'neexecutată'}.`
          : 'Aprobat.',
      );
      setMode('idle');
      onActionDone();
    });
  };

  const handleReject = () => {
    setError(null);
    setSuccessMsg(null);
    startTransition(async () => {
      const result = await rejectEscalation(escalation.id, rejectReason.trim());
      if (result.error) {
        setError({ message: result.error, violations: result.violations });
        return;
      }
      setSuccessMsg('Respins.');
      setMode('idle');
      onActionDone();
    });
  };

  const handleExtend = (seconds: number) => {
    setError(null);
    setSuccessMsg(null);
    startTransition(async () => {
      const result = await extendSla(escalation.id, seconds);
      if (result.error) {
        setError({ message: result.error, violations: result.violations });
        return;
      }
      setSuccessMsg(`SLA extins cu ${Math.round(seconds / 60)} minute.`);
      setMode('idle');
      onActionDone();
    });
  };

  const violationFor = (field: string): string | null => {
    if (!error?.violations) return null;
    const hit = error.violations.find((v) => v.field === field);
    return hit?.message ?? null;
  };

  const rejectReasonError = mode === 'reject' ? violationFor('reason') : null;

  return (
    <div className="border-t border-gray-200 dark:border-gray-700 p-4 bg-surface-sunken dark:bg-gray-800/50">
      {/* Article snapshot */}
      <div className="mb-4 space-y-2">
        <div className="flex items-center gap-2 flex-wrap">
          <span className="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">
            {categoryLabel}
          </span>
          <span className="inline-flex px-2 py-0.5 text-xs font-medium rounded border border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-800">
            Verdict: {verdictType}
          </span>
        </div>

        {snapshot.title && (
          <h4 className="text-sm font-semibold text-primary dark:text-primary-dark">
            {snapshot.title}
          </h4>
        )}
        {snapshot.lead && (
          <p className="text-sm text-secondary dark:text-gray-400">{snapshot.lead}</p>
        )}
        {snapshot.summary && !snapshot.lead && (
          <p className="text-sm text-secondary dark:text-gray-400">{snapshot.summary}</p>
        )}
        {snapshot.verdict_rationale && (
          <p className="text-xs italic text-secondary dark:text-gray-400">
            <span className="font-semibold not-italic">Raționament verificare: </span>
            {snapshot.verdict_rationale}
          </p>
        )}
      </div>

      {/* Origin graph */}
      <div className="mb-4">
        <div className="text-xs font-semibold uppercase text-secondary dark:text-gray-400 mb-2">
          Graf de origine
        </div>
        <OriginGraphViz snapshot={escalation.origin_graph_snapshot} />
      </div>

      {/* Feedback banner */}
      {error && (
        <div className="mb-3 p-2.5 rounded bg-red-50 border border-red-200 text-red-700 text-xs dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
          {error.message}
          {error.violations && error.violations.length > 0 && (
            <ul className="mt-1 ml-4 list-disc">
              {error.violations.map((v, idx) => (
                <li key={`${v.field}-${idx}`}>
                  <span className="font-semibold">{v.field}:</span> {v.message}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
      {successMsg && (
        <div className="mb-3 p-2.5 rounded bg-green-50 border border-green-200 text-green-700 text-xs dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
          {successMsg}
        </div>
      )}

      {/* Action buttons / forms */}
      <div className="flex gap-2 flex-wrap">
        {mode === 'idle' && (
          <>
            <button
              onClick={() => { setMode('approve'); setError(null); setSuccessMsg(null); }}
              disabled={isPending}
              className="px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded disabled:opacity-50 transition"
            >
              Aprobă
            </button>
            <button
              onClick={() => { setMode('reject'); setError(null); setSuccessMsg(null); }}
              disabled={isPending}
              className="px-3 py-1.5 text-xs font-medium text-red-700 bg-red-100 hover:bg-red-200 rounded disabled:opacity-50 dark:text-red-300 dark:bg-red-900/30 dark:hover:bg-red-900/50 transition"
            >
              Respinge
            </button>
            <button
              onClick={() => { setMode('extend'); setError(null); setSuccessMsg(null); }}
              disabled={isPending}
              className="px-3 py-1.5 text-xs font-medium text-primary bg-surface border border-gray-300 hover:bg-surface-sunken rounded disabled:opacity-50 dark:text-primary-dark dark:bg-surface-dark dark:border-gray-600 dark:hover:bg-gray-700 transition"
            >
              Extinde SLA
            </button>
          </>
        )}

        {mode === 'approve' && (
          <div className="w-full space-y-2">
            <label className="flex items-center gap-2 text-xs">
              <input
                type="checkbox"
                checked={publishAsArticle}
                onChange={(e) => setPublishAsArticle(e.target.checked)}
                className="rounded"
              />
              <span className="text-primary dark:text-primary-dark">Aprobă și publică articol</span>
              {publishAsArticle && !snapshot.primary_signal_id && (
                <span className="text-amber-600 dark:text-amber-400 italic">
                  (snapshot-ul nu are primary_signal_id — publicarea automată va fi marcată ca warning)
                </span>
              )}
            </label>
            <textarea
              value={editorialNotes}
              onChange={(e) => setEditorialNotes(e.target.value)}
              placeholder="Note editoriale (opțional)"
              rows={2}
              className="w-full text-xs p-2 rounded border border-gray-300 bg-surface text-primary dark:bg-surface-dark dark:text-primary-dark dark:border-gray-600"
            />
            <div className="flex gap-2">
              <button
                onClick={handleApprove}
                disabled={isPending}
                className="px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded disabled:opacity-50 transition"
              >
                {isPending ? 'Se procesează…' : publishAsArticle ? 'Aprobă și publică' : 'Confirmă aprobarea'}
              </button>
              <button
                onClick={() => setMode('idle')}
                disabled={isPending}
                className="px-3 py-1.5 text-xs text-secondary hover:text-primary dark:text-gray-400 dark:hover:text-primary-dark"
              >
                Anulează
              </button>
            </div>
          </div>
        )}

        {mode === 'reject' && (
          <div className="w-full space-y-2">
            <textarea
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
              placeholder="Motivul respingerii (minim 5, maxim 500 caractere)"
              rows={3}
              className={`w-full text-xs p-2 rounded border bg-surface text-primary dark:bg-surface-dark dark:text-primary-dark ${
                rejectReasonError
                  ? 'border-red-400 dark:border-red-600'
                  : 'border-gray-300 dark:border-gray-600'
              }`}
            />
            {rejectReasonError && (
              <p className="text-xs text-red-600 dark:text-red-400">{rejectReasonError}</p>
            )}
            <div className="flex gap-2">
              <button
                onClick={handleReject}
                disabled={isPending}
                className="px-3 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded disabled:opacity-50 transition"
              >
                {isPending ? 'Se procesează…' : 'Confirmă respingerea'}
              </button>
              <button
                onClick={() => setMode('idle')}
                disabled={isPending}
                className="px-3 py-1.5 text-xs text-secondary hover:text-primary dark:text-gray-400 dark:hover:text-primary-dark"
              >
                Anulează
              </button>
            </div>
          </div>
        )}

        {mode === 'extend' && (
          <div className="w-full space-y-2">
            <p className="text-xs text-secondary dark:text-gray-400">
              Extinde termenul SLA (maxim 3600s per extindere, 5 extinderi/oră per editor).
            </p>
            <div className="flex gap-2 flex-wrap">
              {EXTEND_PRESETS.map((preset) => (
                <button
                  key={preset.seconds}
                  onClick={() => handleExtend(preset.seconds)}
                  disabled={isPending}
                  className="px-3 py-1.5 text-xs font-medium text-primary bg-surface border border-gray-300 hover:bg-surface-sunken rounded disabled:opacity-50 dark:text-primary-dark dark:bg-surface-dark dark:border-gray-600 dark:hover:bg-gray-700"
                >
                  {preset.label}
                </button>
              ))}
              <button
                onClick={() => setMode('idle')}
                disabled={isPending}
                className="ml-auto px-3 py-1.5 text-xs text-secondary hover:text-primary dark:text-gray-400 dark:hover:text-primary-dark"
              >
                Anulează
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
