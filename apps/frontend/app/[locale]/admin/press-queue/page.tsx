'use client';

import { useEffect, useState, useCallback, useTransition } from 'react';
import { useParams, useRouter } from 'next/navigation';
import {
  fetchPressReleases,
  fetchPressEmails,
  fetchPressReleaseCounts,
  approvePressRelease,
  rejectPressRelease,
  type PressReleaseItem,
  type SourceTypeCounts,
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

const SOURCE_TYPE_BADGE: Record<string, { label: string; className: string }> = {
  email: { label: 'Email', className: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-300 dark:border-blue-800' },
  scrape: { label: 'Scrape', className: 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' },
  manual: { label: 'Manual', className: 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-700/30 dark:text-gray-300 dark:border-gray-600' },
  aggregator: { label: 'Agregator', className: 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-900/20 dark:text-violet-300 dark:border-violet-800' },
};

const LANGUAGE_BADGE: Record<string, { label: string; className: string }> = {
  en: { label: 'EN', className: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-900/20 dark:text-sky-300 dark:border-sky-800' },
  ru: { label: 'RU', className: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-900/20 dark:text-rose-300 dark:border-rose-800' },
  it: { label: 'IT', className: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:border-emerald-800' },
  de: { label: 'DE', className: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-800' },
  fr: { label: 'FR', className: 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-900/20 dark:text-indigo-300 dark:border-indigo-800' },
};

const STATUS_LABELS: Record<string, string> = {
  pending: 'In asteptare',
  approved: 'Aprobate',
  rejected: 'Respinse',
};

export default function PressQueuePage() {
  const params = useParams();
  const locale = (params.locale as string) || 'ro';
  const router = useRouter();

  const [items, setItems] = useState<PressReleaseItem[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [filter, setFilter] = useState<'pending' | 'approved' | 'rejected'>('pending');
  const [sourceFilter, setSourceFilter] = useState<string>('all');
  const [languageFilter, setLanguageFilter] = useState<string>('all');
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [isPending, startTransition] = useTransition();
  const [processingId, setProcessingId] = useState<number | null>(null);
  const [fetchingEmails, setFetchingEmails] = useState(false);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);
  const [counts, setCounts] = useState<SourceTypeCounts | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    setError(null);
    const sourceParam = sourceFilter === 'all' ? undefined : sourceFilter;
    const langParam = languageFilter === 'all' ? undefined : languageFilter;
    const result = await fetchPressReleases(filter, page, sourceParam, langParam);
    if (result.error) {
      setError(result.error);
    }
    setItems(result.items);
    setTotalItems(result.totalItems);
    setLoading(false);
  }, [filter, page, sourceFilter, languageFilter]);

  const loadCounts = useCallback(async () => {
    const c = await fetchPressReleaseCounts();
    setCounts(c);
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  useEffect(() => {
    loadCounts();
  }, [loadCounts]);

  const handleApprove = (id: number) => {
    setProcessingId(id);
    startTransition(async () => {
      const result = await approvePressRelease(id);
      if (result.error) {
        setError(result.error);
      }
      setProcessingId(null);
      await loadData();
      await loadCounts();
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
      await loadCounts();
    });
  };

  const handleFetchEmails = async () => {
    setFetchingEmails(true);
    setToast(null);
    try {
      const result = await fetchPressEmails();
      if (result.success) {
        if (result.queued > 0) {
          setToast({ message: `${result.queued} comunicate noi importate`, type: 'success' });
          await loadData();
          await loadCounts();
        } else {
          setToast({ message: 'Niciun email nou', type: 'success' });
        }
      } else {
        setToast({ message: result.error || 'Eroare la preluare', type: 'error' });
      }
    } catch {
      setToast({ message: 'Eroare de retea', type: 'error' });
    } finally {
      setFetchingEmails(false);
    }
  };

  // Auto-dismiss toast after 5 seconds
  useEffect(() => {
    if (!toast) return;
    const timer = setTimeout(() => setToast(null), 5000);
    return () => clearTimeout(timer);
  }, [toast]);

  const changeFilter = (f: 'pending' | 'approved' | 'rejected') => {
    setFilter(f);
    setPage(1);
    setExpandedId(null);
  };

  const changeSourceFilter = (s: string) => {
    setSourceFilter(s);
    setPage(1);
    setExpandedId(null);
  };

  const changeLanguageFilter = (l: string) => {
    setLanguageFilter(l);
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

  const getSourceLabel = (pr: PressReleaseItem): string => {
    if (pr.sourceType === 'email') return pr.senderName || pr.senderAddress || 'Email';
    if (pr.sourceType === 'scrape') return pr.sourceName?.replace('scrape:', '') || 'Scrape';
    if (pr.sourceType === 'aggregator') return pr.sourceName?.replace('aggregator:', '') || 'Agregator';
    return pr.sourceName || 'Manual';
  };

  const totalPages = Math.ceil(totalItems / 20);

  return (
    <div>
      {/* Header */}
      <div className="mb-6 flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-primary dark:text-primary-dark">
            Coada Editoriala Unificata
          </h1>
          <p className="mt-1 text-sm text-secondary dark:text-gray-400">
            Revizuieste si aproba comunicatele din toate sursele (email, scraping, manual)
          </p>
          {counts && counts.total > 0 && (
            <p className="mt-1 text-sm font-medium text-primary dark:text-primary-dark">
              {counts.total} in asteptare
              {counts.email > 0 && <span className="ml-2 text-blue-600 dark:text-blue-400">{counts.email} email</span>}
              {counts.scrape > 0 && <span className="ml-2 text-green-600 dark:text-green-400">{counts.scrape} scrape</span>}
              {counts.manual > 0 && <span className="ml-2 text-gray-600 dark:text-gray-400">{counts.manual} manual</span>}
              {counts.aggregator > 0 && <span className="ml-2 text-violet-600 dark:text-violet-400">{counts.aggregator} agregator</span>}
            </p>
          )}
        </div>
        <button
          onClick={handleFetchEmails}
          disabled={fetchingEmails}
          className="flex items-center gap-2 px-4 py-2 text-sm font-medium border border-gray-300 rounded-lg bg-surface text-primary hover:bg-surface-sunken disabled:opacity-50 disabled:cursor-not-allowed dark:bg-surface-dark dark:text-primary-dark dark:border-gray-600 dark:hover:bg-gray-700 transition flex-shrink-0"
        >
          {fetchingEmails ? (
            <svg className="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
          ) : (
            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
          )}
          {fetchingEmails ? 'Se preia...' : 'Preia Emailuri'}
        </button>
      </div>

      {/* Toast Notification */}
      {toast && (
        <div className={`mb-4 p-3 rounded-lg flex items-center justify-between text-sm ${
          toast.type === 'success'
            ? 'bg-green-50 border border-green-200 text-green-700 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300'
            : 'bg-red-50 border border-red-200 text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300'
        }`}>
          <span>{toast.message}</span>
          <button onClick={() => setToast(null)} className="ml-2 font-bold hover:opacity-70">x</button>
        </div>
      )}

      {/* Filter Tabs + Source Filter */}
      <div className="flex items-center gap-4 mb-6 flex-wrap">
        <div className="flex gap-2">
          {(['pending', 'approved', 'rejected'] as const).map((s) => (
            <button
              key={s}
              onClick={() => changeFilter(s)}
              className={`px-4 py-2 text-sm font-medium rounded-lg transition ${
                filter === s
                  ? 'bg-blue-600 text-white'
                  : 'bg-surface text-primary border border-gray-300 hover:bg-surface-sunken dark:bg-surface-dark dark:text-primary-dark dark:border-gray-600 dark:hover:bg-gray-700'
              }`}
            >
              {STATUS_LABELS[s]}
              {filter === s && !loading && (
                <span className="ml-2 px-1.5 py-0.5 text-xs bg-surface/20 rounded">
                  {totalItems}
                </span>
              )}
            </button>
          ))}
        </div>

        {/* Source Type Filter */}
        <div className="flex gap-1 ml-auto">
          {[
            { key: 'all', label: 'Toate' },
            { key: 'email', label: 'Email' },
            { key: 'scrape', label: 'Scrape' },
            { key: 'manual', label: 'Manual' },
            { key: 'aggregator', label: 'Agregator' },
          ].map((s) => (
            <button
              key={s.key}
              onClick={() => changeSourceFilter(s.key)}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition ${
                sourceFilter === s.key
                  ? 'bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-900'
                  : 'bg-surface text-secondary border border-gray-200 hover:bg-surface-sunken dark:bg-surface-dark dark:text-gray-400 dark:border-gray-600 dark:hover:bg-gray-700'
              }`}
            >
              {s.label}
            </button>
          ))}
        </div>

        {/* Language Filter */}
        <select
          value={languageFilter}
          onChange={(e) => changeLanguageFilter(e.target.value)}
          className="px-3 py-1.5 text-xs font-medium rounded-md border border-gray-200 bg-surface text-secondary dark:bg-surface-dark dark:text-gray-400 dark:border-gray-600"
        >
          <option value="all">Toate limbile</option>
          <option value="ro">RO</option>
          <option value="en">EN</option>
          <option value="ru">RU</option>
          <option value="it">IT</option>
          <option value="de">DE</option>
          <option value="fr">FR</option>
        </select>
      </div>

      {/* Error */}
      {error && (
        <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
          {error}
          <button onClick={() => setError(null)} className="ml-2 font-bold hover:text-red-900">&times;</button>
        </div>
      )}

      {/* Loading */}
      {loading && (
        <div className="text-center py-12 text-secondary dark:text-gray-400">
          Se incarca...
        </div>
      )}

      {/* Empty State */}
      {!loading && items.length === 0 && !error && (
        <div className="text-center py-12 bg-surface dark:bg-surface-dark rounded-lg border border-gray-200 dark:border-gray-700">
          <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <p className="mt-2 text-secondary dark:text-gray-400">
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
              className="bg-surface dark:bg-surface-dark border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden"
            >
              {/* Row Header */}
              <div
                className="p-4 cursor-pointer hover:bg-surface-sunken dark:hover:bg-gray-700/50 transition"
                onClick={() => setExpandedId(expandedId === pr.id ? null : pr.id)}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1.5 flex-wrap">
                      <span className={`inline-flex px-2 py-0.5 text-xs font-medium rounded ${STATUS_COLORS[pr.status]}`}>
                        {STATUS_LABELS[pr.status] || pr.status}
                      </span>
                      {/* Source Type Badge */}
                      {SOURCE_TYPE_BADGE[pr.sourceType] && (
                        <span className={`inline-flex px-2 py-0.5 text-xs font-medium rounded border ${SOURCE_TYPE_BADGE[pr.sourceType].className}`}>
                          {SOURCE_TYPE_BADGE[pr.sourceType].label}
                        </span>
                      )}
                      {/* Language Badge (show only for non-Romanian) */}
                      {pr.originalLanguage && pr.originalLanguage !== 'ro' && LANGUAGE_BADGE[pr.originalLanguage] && (
                        <span className={`inline-flex px-2 py-0.5 text-xs font-medium rounded border ${LANGUAGE_BADGE[pr.originalLanguage].className}`}>
                          {LANGUAGE_BADGE[pr.originalLanguage].label}
                        </span>
                      )}
                      <span className={`inline-flex px-2 py-0.5 text-xs font-medium rounded ${CATEGORY_COLORS[pr.categorySlug] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-primary-dark'}`}>
                        {pr.categorySlug}
                      </span>
                      <span className="text-xs text-secondary dark:text-gray-400">
                        {getSourceLabel(pr)}
                      </span>
                      <span className="text-xs text-gray-400 dark:text-secondary">
                        {formatDate(pr.receivedAt)}
                      </span>
                      <span className="text-xs text-gray-400 dark:text-secondary">
                        {pr.contentLength} caractere
                      </span>
                    </div>
                    <h3 className="text-sm font-semibold text-primary dark:text-primary-dark">
                      {pr.title}
                    </h3>
                    {pr.lead && (
                      <p className="mt-1 text-xs text-secondary dark:text-gray-400 line-clamp-2">
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
                        {processingId === pr.id ? '...' : 'Aproba'}
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
                      Editeaza Articol #{pr.articleId}
                    </a>
                  )}
                </div>
              </div>

              {/* Expanded Content */}
              {expandedId === pr.id && (
                <div className="border-t border-gray-200 dark:border-gray-700 p-4 bg-surface-sunken dark:bg-surface-dark/50">
                  {/* Source metadata */}
                  <div className="mb-3 pb-3 border-b border-gray-200 dark:border-gray-700 space-y-1">
                    {pr.sourceType === 'email' && pr.emailSubject && (
                      <div>
                        <span className="text-xs font-medium text-secondary dark:text-gray-400">Subiect: </span>
                        <span className="text-xs text-primary dark:text-primary-dark">{pr.emailSubject}</span>
                      </div>
                    )}
                    {pr.sourceType === 'email' && pr.senderAddress && (
                      <div>
                        <span className="text-xs font-medium text-secondary dark:text-gray-400">De la: </span>
                        <span className="text-xs text-primary dark:text-primary-dark">{pr.senderName || pr.senderAddress}</span>
                        {pr.senderName && (
                          <span className="text-xs text-gray-400 dark:text-secondary ml-1">({pr.senderAddress})</span>
                        )}
                      </div>
                    )}
                    {pr.sourceUrl && (
                      <div>
                        <span className="text-xs font-medium text-secondary dark:text-gray-400">Sursa originala: </span>
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
                    {pr.sourceType === 'scrape' && pr.sourceName && (
                      <div>
                        <span className="text-xs font-medium text-secondary dark:text-gray-400">Sursa scraping: </span>
                        <span className="text-xs text-primary dark:text-primary-dark">{pr.sourceName}</span>
                      </div>
                    )}
                    {pr.sourceType === 'aggregator' && pr.sourceName && (
                      <div>
                        <span className="text-xs font-medium text-secondary dark:text-gray-400">Sursa agregator: </span>
                        <span className="text-xs text-primary dark:text-primary-dark">{pr.sourceName.replace('aggregator:', '')}</span>
                      </div>
                    )}
                    {pr.originalLanguage && pr.originalLanguage !== 'ro' && (
                      <div>
                        <span className="text-xs font-medium text-secondary dark:text-gray-400">Limba originala: </span>
                        <span className="text-xs text-primary dark:text-primary-dark">{pr.originalLanguage.toUpperCase()}</span>
                        <span className="text-xs text-amber-600 dark:text-amber-400 ml-1">(tradus automat in RO)</span>
                      </div>
                    )}
                    {pr.rejectionReason && (
                      <div className="mt-2 p-2 bg-red-50 border border-red-200 rounded dark:bg-red-900/10 dark:border-red-800">
                        <span className="text-xs font-medium text-red-700 dark:text-red-300">Motiv respingere: </span>
                        <span className="text-xs text-red-600 dark:text-red-400">{pr.rejectionReason}</span>
                      </div>
                    )}
                  </div>
                  <div
                    className="text-sm leading-relaxed text-primary dark:text-gray-200 [&_p]:mb-3 [&_a]:text-blue-600 dark:[&_a]:text-blue-400 [&_a]:underline [&_strong]:font-semibold [&_h2]:text-lg [&_h2]:font-bold [&_h2]:mt-4 [&_h2]:mb-2 [&_h3]:text-base [&_h3]:font-semibold [&_h3]:mt-3 [&_h3]:mb-1 [&_blockquote]:border-l-4 [&_blockquote]:border-gray-300 [&_blockquote]:pl-4 [&_blockquote]:italic dark:[&_blockquote]:border-gray-600"
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
            className="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 disabled:opacity-50 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-primary-dark"
          >
            Anterior
          </button>
          <span className="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400">
            Pagina {page} din {totalPages}
          </span>
          <button
            onClick={() => setPage(Math.min(totalPages, page + 1))}
            disabled={page >= totalPages}
            className="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 disabled:opacity-50 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-primary-dark"
          >
            Urmator
          </button>
        </div>
      )}
    </div>
  );
}
