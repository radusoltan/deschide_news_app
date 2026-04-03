'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import type { Tag } from '@/lib/types/tag';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

interface TagsPageClientProps {
  locale: string;
  initialPage: number;
  initialSearch: string;
}

export default function TagsPageClient({
  locale,
  initialPage,
  initialSearch,
}: TagsPageClientProps) {
  const router = useRouter();
  const [tags, setTags] = useState<Tag[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [page, setPage] = useState(initialPage);
  const [search, setSearch] = useState(initialSearch);
  const [isLoading, setIsLoading] = useState(true);
  const [sortField, setSortField] = useState<string>('name');
  const [sortDir, setSortDir] = useState<'ASC' | 'DESC'>('ASC');
  const itemsPerPage = 30;

  // Modal state
  const [editingTag, setEditingTag] = useState<Tag | null>(null);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [deleteTag, setDeleteTag] = useState<Tag | null>(null);
  const [formName, setFormName] = useState('');
  const [formDescription, setFormDescription] = useState('');
  const [formError, setFormError] = useState('');
  const [isSaving, setIsSaving] = useState(false);

  // Merge state
  const [mergeSource, setMergeSource] = useState<Tag | null>(null);
  const [mergeTarget, setMergeTarget] = useState<Tag | null>(null);
  const [mergeSearch, setMergeSearch] = useState('');
  const [mergeResults, setMergeResults] = useState<Tag[]>([]);
  const [mergeError, setMergeError] = useState('');

  const fetchTags = useCallback(async () => {
    setIsLoading(true);
    try {
      const headers: HeadersInit = {
        'Content-Type': 'application/ld+json',
        'Accept-Language': locale,
      };

      const params = new URLSearchParams();
      params.set('page', page.toString());
      params.set('itemsPerPage', itemsPerPage.toString());
      params.set(`order[${sortField}]`, sortDir);

      if (search.trim()) {
        params.set('name', search.trim());
      }

      const res = await fetch(`${API_BASE_URL}/api/tags?${params.toString()}`, {
        headers,
        cache: 'no-store',
      });

      if (res.ok) {
        const data = await res.json();
        setTags(data['hydra:member'] || data['member'] || []);
        setTotalItems(data['hydra:totalItems'] || data['totalItems'] || 0);
      }
    } catch (err) {
      console.error('Failed to fetch tags:', err);
    } finally {
      setIsLoading(false);
    }
  }, [locale, page, search, sortField, sortDir]);

  useEffect(() => {
    fetchTags();
  }, [fetchTags]);

  const totalPages = Math.ceil(totalItems / itemsPerPage);

  const handleSort = (field: string) => {
    if (sortField === field) {
      setSortDir((d) => (d === 'ASC' ? 'DESC' : 'ASC'));
    } else {
      setSortField(field);
      setSortDir(field === 'usageCount' ? 'DESC' : 'ASC');
    }
    setPage(1);
  };

  const openCreate = () => {
    setFormName('');
    setFormDescription('');
    setFormError('');
    setEditingTag(null);
    setIsCreateOpen(true);
  };

  const openEdit = (tag: Tag) => {
    setFormName(tag.name);
    setFormDescription(tag.description || '');
    setFormError('');
    setEditingTag(tag);
    setIsCreateOpen(true);
  };

  const handleSaveTag = async () => {
    if (!formName.trim()) {
      setFormError('Numele tag-ului este obligatoriu');
      return;
    }

    setIsSaving(true);
    setFormError('');

    try {
      const headers: HeadersInit = {
        'Content-Type': 'application/ld+json',
        'Accept-Language': locale,
      };

      const body = JSON.stringify({
        name: formName.trim(),
        description: formDescription.trim() || null,
      });

      let res: Response;
      if (editingTag) {
        res = await fetch(`${API_BASE_URL}/api/tags/${editingTag.id}`, {
          method: 'PUT',
          headers,
          body,
        });
      } else {
        res = await fetch(`${API_BASE_URL}/api/tags`, {
          method: 'POST',
          headers,
          body,
        });
      }

      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err['hydra:description'] || err.detail || 'Failed to save tag');
      }

      setIsCreateOpen(false);
      setEditingTag(null);
      fetchTags();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'Failed to save tag');
    } finally {
      setIsSaving(false);
    }
  };

  const handleDeleteTag = async () => {
    if (!deleteTag) return;

    setIsSaving(true);
    try {
      const res = await fetch(`${API_BASE_URL}/api/tags/${deleteTag.id}`, {
        method: 'DELETE',
        headers: { 'Accept-Language': locale },
      });

      if (!res.ok && res.status !== 204) {
        throw new Error('Failed to delete tag');
      }

      setDeleteTag(null);
      fetchTags();
    } catch (err) {
      console.error('Delete failed:', err);
    } finally {
      setIsSaving(false);
    }
  };

  const searchMergeTargets = useCallback(
    async (q: string) => {
      if (q.trim().length < 2) {
        setMergeResults([]);
        return;
      }
      try {
        const res = await fetch(
          `${API_BASE_URL}/api/tags/search?q=${encodeURIComponent(q.trim())}&limit=10`,
          { headers: { 'Accept-Language': locale } }
        );
        if (res.ok) {
          const data = await res.json();
          setMergeResults(
            (data['hydra:member'] || data['member'] || data || []).filter(
              (t: Tag) => t.id !== mergeSource?.id
            )
          );
        }
      } catch {
        setMergeResults([]);
      }
    },
    [locale, mergeSource]
  );

  const handleMergeTag = async () => {
    if (!mergeSource || !mergeTarget) return;

    setIsSaving(true);
    setMergeError('');
    try {
      const res = await fetch(`${API_BASE_URL}/api/tags/${mergeSource.id}/merge`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept-Language': locale,
        },
        body: JSON.stringify({ targetTagId: mergeTarget.id }),
      });

      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err['hydra:description'] || 'Failed to merge tags');
      }

      setMergeSource(null);
      setMergeTarget(null);
      setMergeSearch('');
      setMergeResults([]);
      fetchTags();
    } catch (err) {
      setMergeError(err instanceof Error ? err.message : 'Failed to merge tags');
    } finally {
      setIsSaving(false);
    }
  };

  const SortIcon = ({ field }: { field: string }) => (
    <svg
      className={`w-3 h-3 ml-1 inline-block transition-transform ${
        sortField === field ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400'
      } ${sortField === field && sortDir === 'DESC' ? 'rotate-180' : ''}`}
      fill="currentColor"
      viewBox="0 0 20 20"
    >
      <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
    </svg>
  );

  return (
    <>
      {/* Page Header */}
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-primary dark:text-primary-dark">Tag-uri</h1>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {totalItems} tag-uri in total
          </p>
        </div>
        <button
          onClick={openCreate}
          className="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800"
        >
          + Tag nou
        </button>
      </div>

      {/* Search */}
      <div className="mb-4">
        <div className="relative max-w-md">
          <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input
            type="text"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            placeholder="Cauta tag-uri..."
            className="block w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-sm text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
      </div>

      {/* Table */}
      <div className="bg-surface dark:bg-surface-dark relative shadow-md sm:rounded-lg overflow-hidden">
        <table className="w-full text-sm text-left text-primary dark:text-primary-dark">
          <thead className="text-xs uppercase bg-gray-50 dark:bg-gray-700 text-secondary dark:text-gray-400">
            <tr>
              <th className="px-6 py-3 cursor-pointer select-none" onClick={() => handleSort('name')}>
                Nume <SortIcon field="name" />
              </th>
              <th className="px-6 py-3">Slug</th>
              <th className="px-6 py-3 cursor-pointer select-none" onClick={() => handleSort('usageCount')}>
                Utilizari <SortIcon field="usageCount" />
              </th>
              <th className="px-6 py-3 cursor-pointer select-none" onClick={() => handleSort('createdAt')}>
                Creat <SortIcon field="createdAt" />
              </th>
              <th className="px-6 py-3 text-right">Actiuni</th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              <tr>
                <td colSpan={5} className="px-6 py-12 text-center text-secondary dark:text-gray-400">
                  <div className="flex items-center justify-center gap-2">
                    <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                    Se incarca...
                  </div>
                </td>
              </tr>
            ) : tags.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-6 py-12 text-center text-secondary dark:text-gray-400">
                  {search ? `Niciun tag gasit pentru "${search}"` : 'Niciun tag disponibil'}
                </td>
              </tr>
            ) : (
              tags.map((tag) => (
                <tr key={tag.id} className="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                  <td className="px-6 py-4 font-medium">
                    #{tag.name}
                  </td>
                  <td className="px-6 py-4 text-secondary dark:text-gray-400 font-mono text-xs">
                    {tag.slug}
                  </td>
                  <td className="px-6 py-4">
                    <span
                      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                        tag.usageCount > 0
                          ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                          : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
                      }`}
                    >
                      {tag.usageCount}
                    </span>
                  </td>
                  <td className="px-6 py-4 text-secondary dark:text-gray-400 text-xs">
                    {tag.createdAt
                      ? new Date(tag.createdAt).toLocaleDateString(locale, {
                          day: '2-digit',
                          month: 'short',
                          year: 'numeric',
                        })
                      : '-'}
                  </td>
                  <td className="px-6 py-4 text-right">
                    <button
                      onClick={() => openEdit(tag)}
                      className="text-blue-600 dark:text-blue-400 hover:underline text-sm mr-3"
                    >
                      Edit
                    </button>
                    <button
                      onClick={() => {
                        setMergeSource(tag);
                        setMergeTarget(null);
                        setMergeSearch('');
                        setMergeResults([]);
                        setMergeError('');
                      }}
                      className="text-amber-600 dark:text-amber-400 hover:underline text-sm mr-3"
                    >
                      Merge
                    </button>
                    <button
                      onClick={() => setDeleteTag(tag)}
                      className="text-red-600 dark:text-red-400 hover:underline text-sm"
                    >
                      Delete
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="mt-4 flex items-center justify-between">
          <p className="text-sm text-secondary dark:text-gray-400">
            Pagina {page} din {totalPages} ({totalItems} tag-uri)
          </p>
          <div className="flex gap-2">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page <= 1}
              className="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg disabled:opacity-50 hover:bg-gray-50 dark:hover:bg-gray-700 text-primary dark:text-primary-dark"
            >
              Anterior
            </button>
            <button
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              disabled={page >= totalPages}
              className="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg disabled:opacity-50 hover:bg-gray-50 dark:hover:bg-gray-700 text-primary dark:text-primary-dark"
            >
              Urmator
            </button>
          </div>
        </div>
      )}

      {/* Create/Edit Modal */}
      {isCreateOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-gray-900/80">
          <div className="bg-surface dark:bg-surface-dark rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
            <h3 className="text-lg font-semibold text-primary dark:text-primary-dark mb-4">
              {editingTag ? 'Editeaza tag' : 'Tag nou'}
            </h3>

            {formError && (
              <div className="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-600 dark:text-red-400">
                {formError}
              </div>
            )}

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-primary dark:text-primary-dark mb-1">
                  Nume <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={formName}
                  onChange={(e) => setFormName(e.target.value)}
                  placeholder="ex: alegeri, economie, sport"
                  maxLength={100}
                  className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  autoFocus
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-primary dark:text-primary-dark mb-1">
                  Descriere
                </label>
                <textarea
                  value={formDescription}
                  onChange={(e) => setFormDescription(e.target.value)}
                  placeholder="Descriere optionala"
                  rows={3}
                  className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                />
              </div>
            </div>

            <div className="mt-6 flex justify-end gap-3">
              <button
                onClick={() => {
                  setIsCreateOpen(false);
                  setEditingTag(null);
                }}
                disabled={isSaving}
                className="px-4 py-2 text-sm font-medium text-primary dark:text-primary-dark bg-surface dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
              >
                Anuleaza
              </button>
              <button
                onClick={handleSaveTag}
                disabled={isSaving}
                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                {isSaving ? 'Se salveaza...' : editingTag ? 'Salveaza' : 'Creeaza'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Merge Modal */}
      {mergeSource && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-gray-900/80">
          <div className="bg-surface dark:bg-surface-dark rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
            <h3 className="text-lg font-semibold text-primary dark:text-primary-dark mb-4">
              Merge &laquo;#{mergeSource.name}&raquo; in alt tag
            </h3>

            {mergeError && (
              <div className="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-600 dark:text-red-400">
                {mergeError}
              </div>
            )}

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-primary dark:text-primary-dark mb-1">
                  Tag destinatie
                </label>
                <input
                  type="text"
                  value={mergeSearch}
                  onChange={(e) => {
                    setMergeSearch(e.target.value);
                    setMergeTarget(null);
                    searchMergeTargets(e.target.value);
                  }}
                  placeholder="Cauta tag-ul destinatie..."
                  className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  autoFocus
                />
                {mergeResults.length > 0 && !mergeTarget && (
                  <div className="mt-1 border border-gray-200 dark:border-gray-600 rounded-lg max-h-40 overflow-y-auto bg-surface dark:bg-gray-700">
                    {mergeResults.map((t) => (
                      <button
                        key={t.id}
                        type="button"
                        onClick={() => {
                          setMergeTarget(t);
                          setMergeSearch(t.name);
                          setMergeResults([]);
                        }}
                        className="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-600 text-primary dark:text-primary-dark"
                      >
                        #{t.name}
                        <span className="ml-2 text-xs text-secondary dark:text-gray-400">
                          ({t.usageCount} articole)
                        </span>
                      </button>
                    ))}
                  </div>
                )}
              </div>

              {mergeTarget && (
                <div className="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg text-sm">
                  <p className="text-amber-800 dark:text-amber-300">
                    Cele <strong>{mergeSource.usageCount}</strong> articole de la <strong>#{mergeSource.name}</strong> vor fi mutate la <strong>#{mergeTarget.name}</strong>.
                  </p>
                  <p className="mt-1 text-amber-700 dark:text-amber-400 text-xs">
                    Tag-ul &laquo;#{mergeSource.name}&raquo; va fi sters permanent.
                  </p>
                </div>
              )}
            </div>

            <div className="mt-6 flex justify-end gap-3">
              <button
                onClick={() => {
                  setMergeSource(null);
                  setMergeTarget(null);
                  setMergeSearch('');
                  setMergeResults([]);
                }}
                disabled={isSaving}
                className="px-4 py-2 text-sm font-medium text-primary dark:text-primary-dark bg-surface dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
              >
                Anuleaza
              </button>
              <button
                onClick={handleMergeTag}
                disabled={isSaving || !mergeTarget}
                className="px-4 py-2 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700 disabled:opacity-50"
              >
                {isSaving ? 'Se proceseaza...' : 'Merge & Sterge'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {deleteTag && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-gray-900/80">
          <div className="bg-surface dark:bg-surface-dark rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
            <div className="flex items-center gap-3 mb-4">
              <div className="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
                <svg className="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-primary dark:text-primary-dark">
                Sterge tag
              </h3>
            </div>

            <p className="text-sm text-secondary dark:text-gray-400 mb-2">
              Esti sigur ca vrei sa stergi tag-ul <strong>#{deleteTag.name}</strong>?
            </p>

            {deleteTag.usageCount > 0 && (
              <div className="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg text-sm text-yellow-700 dark:text-yellow-300">
                Acest tag este asociat cu {deleteTag.usageCount} articole. Tag-ul va fi disociat de articole, dar articolele nu vor fi sterse.
              </div>
            )}

            <div className="mt-6 flex justify-end gap-3">
              <button
                onClick={() => setDeleteTag(null)}
                disabled={isSaving}
                className="px-4 py-2 text-sm font-medium text-primary dark:text-primary-dark bg-surface dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
              >
                Anuleaza
              </button>
              <button
                onClick={handleDeleteTag}
                disabled={isSaving}
                className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
              >
                {isSaving ? 'Se sterge...' : 'Sterge'}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
