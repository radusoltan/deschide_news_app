'use client';

import { useEffect, useState, useCallback, useTransition } from 'react';
import { useParams, useRouter } from 'next/navigation';
import {
  fetchPressReleases,
  approvePressRelease,
  rejectPressRelease,
  type PressReleaseItem,
} from '@/app/actions/press-releases';

const STATUS_COLORS: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
  approved: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
  rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
};

const CATEGORY_COLORS: Record<string, string> = {
  politica: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
  economie: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
  societate: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
  sport: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
  cultura: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
  externe: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300',
  mediu: 'bg-lime-100 text-lime-800 dark:bg-lime-900/30 dark:text-lime-300',
  sanatate: 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300',
  educatie: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
  justitie: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
};

const STATUS_LABELS: Record<string, string> = {
  pending: 'In așteptare',
  approved: 'Aprobate',
  rejected: 'Respinse',
};

export default function PressQueuePage() {
  const params = useParams();
  const locale = params.locale as string;
  const router = useRouter();

  const [items, setItems] = useState<PressReleaseItem[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [filter, setFilter] = useState<'pending' | 'approved' | 'rejected'>('pending');
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [isPending, startTransition] = useTransition();
  const [processingId, setProcessingId] = useState<number | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    setError(null);
    const result = await fetchPressReleases(filter, page);
    if (result.error) {
      setError(result.error);
    }
    setItems(result.items);
    setTotalItems(result.totalItems);
    setLoading(false);
  }, [filter, page]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleApprove = (id: number) => {
    setProcessingId(id);
    startTransition(async () => {
      const result = await approvePressRelease(id);
      if (result.error) {
        setError(result.error);
      }
      setProcessingId(null);
      await loadData();
    });
  };

  const handleReject = (id: number) => {
    setProcessingId(id);
    startTransition(async () => {
      const result = await rejectPressRelease(id);
      if (result.error) {
        setError(result.error);
      }
      setProcessingId(null);
      await loadData();
    });
  };

  const changeFilter = (f: 'pending' | 'approved' | 'rejected') => {
    setFilter(f);
    setPage(1);
    setExpandedId(null);
  };

  const formatDate = (dateStr: string) => {
    try {
      return new Date(dateStr).toLocaleDateString('ro-RO', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
      });
    } catch {
      return dateStr;
    }
  };

  const totalPages = Math.ceil(totalItems / 20);

  return (
    <div>
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
          Coadă Comunicate de Presă
        </h1>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Revizuiește și aprobă comunicatele de presă primite automat din Zoho Mail
        </p>
      </div>

      {/* Filter Tabs */}
      <div className="flex gap-2 mb-6">
        {(['pending', 'approved', 'rejected'] as const).map((s) => (
          <button
            key={s}
            onClick={() => changeFilter(s)}
            className={`px-4 py-2 text-sm font-medium rounded-lg transition ${
              filter === s
                ? 'bg-blue-600 text-white'
                : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700'
            }`}
          >
            {STATUS_LABELS[s]}
            {filter === s && !loading && (
              <span className="ml-2 px-1.5 py-0.5 text-xs bg-white/20 rounded">
                {totalItems}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* Error */}
      {error && (
        <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
          {error}
          <button onClick={() => setError(null)} className="ml-2 font-bold hover:text-red-900">×</button>
        </div>
      )}

      {/* Loading */}
      {loading && (
        <div className="text-center py-12 text-gray-500 dark:text-gray-400">
          Se încarcă...
        </div>
      )}

      {/* Empty State */}
      {!loading && items.length === 0 && !error && (
        <div className="text-center py-12 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
          <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <p className="mt-2 text-gray-500 dark:text-gray-400">
            {filter === 'pending' ? 'Nu sunt comunicate noi de revizuit' : 'Niciun rezultat'}
          </p>
        </div>
      )}

      {/* List */}
      {!loading && items.length > 0 && (
        <div className="space-y-3">
          {items.map((pr) => (
            <div
              key={pr.id}
              className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden"
            >
              {/* Row Header */}
              <div
                className="p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                onClick={() => setExpandedId(expandedId === pr.id ? null : pr.id)}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1.5 flex-wrap">
                      <span className={`inline-flex px-2 py-0.5 text-xs font-medium rounded ${STATUS_COLORS[pr.status]}`}>
                        {STATUS_LABELS[pr.status] || pr.status}
                      </span>
                      <span className={`inline-flex px-2 py-0.5 text-xs font-medium rounded ${CATEGORY_COLORS[pr.categorySlug] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'}`}>
                        {pr.categorySlug}
                      </span>
                      <span className="text-xs text-gray-500 dark:text-gray-400">
                        {pr.senderName}
                      </span>
                      <span className="text-xs text-gray-400 dark:text-gray-500">
                        {formatDate(pr.receivedAt)}
                      </span>
                      <span className="text-xs text-gray-400 dark:text-gray-500">
                        {pr.contentLength} caractere
                      </span>
                    </div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                      {pr.title}
                    </h3>
                    {pr.lead && (
                      <p className="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                        {pr.lead}
                      </p>
                    )}
                  </div>

                  {/* Actions */}
                  {pr.status === 'pending' && (
                    <div className="flex gap-2 flex-shrink-0">
                      <button
                        onClick={(e) => { e.stopPropagation(); handleApprove(pr.id); }}
                        disabled={processingId === pr.id || isPending}
                        className="px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg disabled:opacity-50 transition"
                      >
                        {processingId === pr.id ? '...' : 'Aprobă'}
                      </button>
                      <button
                        onClick={(e) => { e.stopPropagation(); handleReject(pr.id); }}
                        disabled={processingId === pr.id || isPending}
                        className="px-3 py-1.5 text-xs font-medium text-red-700 bg-red-100 hover:bg-red-200 rounded-lg disabled:opacity-50 dark:text-red-300 dark:bg-red-900/30 dark:hover:bg-red-900/50 transition"
                      >
                        Respinge
                      </button>
                    </div>
                  )}

                  {pr.status === 'approved' && pr.articleId && (
                    <a
                      href={`/${locale}/admin/articles/${pr.articleId}/edit`}
                      onClick={(e) => e.stopPropagation()}
                      className="px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100 hover:bg-blue-200 rounded-lg dark:text-blue-300 dark:bg-blue-900/30 dark:hover:bg-blue-900/50 transition flex-shrink-0"
                    >
                      Editează Articol #{pr.articleId}
                    </a>
                  )}
                </div>
              </div>

              {/* Expanded Content */}
              {expandedId === pr.id && (
                <div className="border-t border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-900/50">
                  {pr.sourceUrl && (
                    <div className="mb-3 pb-3 border-b border-gray-200 dark:border-gray-700">
                      <span className="text-xs font-medium text-gray-500 dark:text-gray-400">Sursă originală: </span>
                      <a
                        href={pr.sourceUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-xs text-blue-600 hover:underline dark:text-blue-400"
                      >
                        {pr.sourceUrl}
                      </a>
                    </div>
                  )}
                  <div
                    className="prose prose-sm max-w-none dark:prose-invert"
                    dangerouslySetInnerHTML={{ __html: pr.content }}
                  />
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex justify-center gap-2 mt-6">
          <button
            onClick={() => setPage(Math.max(1, page - 1))}
            disabled={page <= 1}
            className="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 disabled:opacity-50 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-300"
          >
            Anterior
          </button>
          <span className="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400">
            Pagina {page} din {totalPages}
          </span>
          <button
            onClick={() => setPage(Math.min(totalPages, page + 1))}
            disabled={page >= totalPages}
            className="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 disabled:opacity-50 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-300"
          >
            Următor
          </button>
        </div>
      )}
    </div>
  );
}
