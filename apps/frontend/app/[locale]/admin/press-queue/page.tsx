'use client';

import { useEffect, useState, useCallback } from 'react';
import { useParams } from 'next/navigation';
import {
  getPressReleases,
  approvePressRelease,
  rejectPressRelease,
  type PressRelease,
} from '@/lib/api/press-releases';

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

function getToken(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
}

export default function PressQueuePage() {
  const params = useParams();
  const locale = params.locale as string;

  const [items, setItems] = useState<PressRelease[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [filter, setFilter] = useState<'pending' | 'approved' | 'rejected' | ''>('pending');
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [processing, setProcessing] = useState<number | null>(null);

  const fetchData = useCallback(async () => {
    const token = getToken();
    if (!token) {
      setError('Nu ești autentificat');
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const data = await getPressReleases(token, {
        status: filter || undefined,
        page,
        itemsPerPage: 20,
      });
      setItems(data.member);
      setTotalItems(data.totalItems);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Eroare la încărcarea datelor');
    } finally {
      setLoading(false);
    }
  }, [filter, page]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleApprove = async (id: number) => {
    const token = getToken();
    if (!token) return;

    setProcessing(id);
    try {
      await approvePressRelease(id, token);
      await fetchData();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Eroare la aprobare');
    } finally {
      setProcessing(null);
    }
  };

  const handleReject = async (id: number) => {
    const token = getToken();
    if (!token) return;

    setProcessing(id);
    try {
      await rejectPressRelease(id, token);
      await fetchData();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Eroare la respingere');
    } finally {
      setProcessing(null);
    }
  };

  const formatDate = (dateStr: string) => {
    const d = new Date(dateStr);
    return d.toLocaleDateString('ro-RO', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const totalPages = Math.ceil(totalItems / 20);

  return (
    <div className="p-4 lg:ml-64">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
          Coadă Comunicate de Presă
        </h1>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Revizuiește și aprobă comunicatele de presă primite automat din Zoho Mail
        </p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <button
          onClick={() => { setFilter('pending'); setPage(1); }}
          className={`p-4 rounded-lg border text-left transition ${
            filter === 'pending'
              ? 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-600'
              : 'border-gray-200 bg-white dark:bg-gray-800 dark:border-gray-700 hover:border-yellow-300'
          }`}
        >
          <div className="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
            {filter === 'pending' ? totalItems : '...'}
          </div>
          <div className="text-sm text-gray-600 dark:text-gray-400">In așteptare</div>
        </button>
        <button
          onClick={() => { setFilter('approved'); setPage(1); }}
          className={`p-4 rounded-lg border text-left transition ${
            filter === 'approved'
              ? 'border-green-500 bg-green-50 dark:bg-green-900/20 dark:border-green-600'
              : 'border-gray-200 bg-white dark:bg-gray-800 dark:border-gray-700 hover:border-green-300'
          }`}
        >
          <div className="text-2xl font-bold text-green-600 dark:text-green-400">
            {filter === 'approved' ? totalItems : '...'}
          </div>
          <div className="text-sm text-gray-600 dark:text-gray-400">Aprobate</div>
        </button>
        <button
          onClick={() => { setFilter('rejected'); setPage(1); }}
          className={`p-4 rounded-lg border text-left transition ${
            filter === 'rejected'
              ? 'border-red-500 bg-red-50 dark:bg-red-900/20 dark:border-red-600'
              : 'border-gray-200 bg-white dark:bg-gray-800 dark:border-gray-700 hover:border-red-300'
          }`}
        >
          <div className="text-2xl font-bold text-red-600 dark:text-red-400">
            {filter === 'rejected' ? totalItems : '...'}
          </div>
          <div className="text-sm text-gray-600 dark:text-gray-400">Respinse</div>
        </button>
      </div>

      {/* Error */}
      {error && (
        <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
          {error}
          <button onClick={() => setError(null)} className="ml-2 font-bold">×</button>
        </div>
      )}

      {/* Loading */}
      {loading && (
        <div className="text-center py-12 text-gray-500 dark:text-gray-400">
          Se încarcă...
        </div>
      )}

      {/* Empty State */}
      {!loading && items.length === 0 && (
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
                className="p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-750"
                onClick={() => setExpandedId(expandedId === pr.id ? null : pr.id)}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1 flex-wrap">
                      <span className={`inline-flex px-2 py-0.5 text-xs font-medium rounded ${STATUS_COLORS[pr.status]}`}>
                        {pr.status}
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
                        {pr.contentLength} chars
                      </span>
                    </div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-white truncate">
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
                        disabled={processing === pr.id}
                        className="px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg disabled:opacity-50 transition"
                      >
                        {processing === pr.id ? '...' : 'Aprobă'}
                      </button>
                      <button
                        onClick={(e) => { e.stopPropagation(); handleReject(pr.id); }}
                        disabled={processing === pr.id}
                        className="px-3 py-1.5 text-xs font-medium text-red-700 bg-red-100 hover:bg-red-200 rounded-lg disabled:opacity-50 dark:text-red-300 dark:bg-red-900/30 dark:hover:bg-red-900/50 transition"
                      >
                        Respinge
                      </button>
                    </div>
                  )}

                  {pr.status === 'approved' && pr.article && (
                    <a
                      href={`/${locale}/admin/articles/${pr.article.id}/edit`}
                      onClick={(e) => e.stopPropagation()}
                      className="px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100 hover:bg-blue-200 rounded-lg dark:text-blue-300 dark:bg-blue-900/30 dark:hover:bg-blue-900/50 transition"
                    >
                      Editează Articol #{pr.article.id}
                    </a>
                  )}
                </div>
              </div>

              {/* Expanded Content */}
              {expandedId === pr.id && (
                <div className="border-t border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-900/50">
                  {pr.sourceUrl && (
                    <div className="mb-3">
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
            className="px-3 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 disabled:opacity-50 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-300"
          >
            Prev
          </button>
          <span className="px-3 py-1 text-sm text-gray-600 dark:text-gray-400">
            {page} / {totalPages}
          </span>
          <button
            onClick={() => setPage(Math.min(totalPages, page + 1))}
            disabled={page >= totalPages}
            className="px-3 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 disabled:opacity-50 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-300"
          >
            Next
          </button>
        </div>
      )}
    </div>
  );
}
