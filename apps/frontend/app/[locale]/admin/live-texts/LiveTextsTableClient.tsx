'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import type { LiveTextListItem } from '@/lib/types/livetext';

interface LiveTextsTableClientProps {
  liveTexts: LiveTextListItem[];
  locale: string;
}

export function LiveTextsTableClient({ liveTexts, locale }: LiveTextsTableClientProps) {
  const router = useRouter();
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const texts = {
    ro: {
      title: 'Titlu',
      status: 'Status',
      category: 'Categorie',
      posts: 'Postări',
      startTime: 'Început',
      endTime: 'Sfârșit',
      author: 'Autor',
      actions: 'Acțiuni',
      view: 'Vezi',
      edit: 'Editează',
      managePosts: 'Gestionare Postări',
      delete: 'Șterge',
      confirmDelete: 'Sigur doriți să ștergeți acest Live Text?',
      noLiveTexts: 'Nu există Live Texts',
      statusLive: 'LIVE',
      statusPaused: 'PAUZAT',
      statusEnded: 'ÎNCHEIAT',
      statusDraft: 'DRAFT',
    },
    en: {
      title: 'Title',
      status: 'Status',
      category: 'Category',
      posts: 'Posts',
      startTime: 'Start',
      endTime: 'End',
      author: 'Author',
      actions: 'Actions',
      view: 'View',
      edit: 'Edit',
      managePosts: 'Manage Posts',
      delete: 'Delete',
      confirmDelete: 'Are you sure you want to delete this Live Text?',
      noLiveTexts: 'No Live Texts',
      statusLive: 'LIVE',
      statusPaused: 'PAUSED',
      statusEnded: 'ENDED',
      statusDraft: 'DRAFT',
    },
    ru: {
      title: 'Название',
      status: 'Статус',
      category: 'Категория',
      posts: 'Посты',
      startTime: 'Начало',
      endTime: 'Конец',
      author: 'Автор',
      actions: 'Действия',
      view: 'Просмотр',
      edit: 'Редактировать',
      managePosts: 'Управление постами',
      delete: 'Удалить',
      confirmDelete: 'Вы уверены, что хотите удалить этот Live Текст?',
      noLiveTexts: 'Нет Live Текстов',
      statusLive: 'В ЭФИРЕ',
      statusPaused: 'ПАУЗА',
      statusEnded: 'ЗАВЕРШЕНО',
      statusDraft: 'ЧЕРНОВИК',
    },
  };

  const t = texts[locale as keyof typeof texts] || texts.ro;

  const handleDelete = async (id: number) => {
    if (!confirm(t.confirmDelete)) {
      return;
    }

    setDeletingId(id);

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? '';
      const response = await fetch(`${apiUrl}/api/live_texts/${id}`, {
        method: 'DELETE',
        credentials: 'include',
      });

      if (!response.ok) {
        throw new Error('Failed to delete');
      }

      // Refresh the page
      router.refresh();
    } catch (error) {
      console.error('Error deleting live text:', error);
      alert('Failed to delete live text');
    } finally {
      setDeletingId(null);
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'live':
        return (
          <span className="px-2 py-1 text-xs font-bold rounded-full bg-red-600 text-white animate-pulse">
            {t.statusLive}
          </span>
        );
      case 'paused':
        return (
          <span className="px-2 py-1 text-xs font-bold rounded-full bg-yellow-500 text-white">
            {t.statusPaused}
          </span>
        );
      case 'ended':
        return (
          <span className="px-2 py-1 text-xs font-bold rounded-full bg-surface-sunken0 text-white">
            {t.statusEnded}
          </span>
        );
      case 'draft':
        return (
          <span className="px-2 py-1 text-xs font-bold rounded-full bg-gray-400 text-white">
            {t.statusDraft}
          </span>
        );
      default:
        return <span className="px-2 py-1 text-xs font-bold rounded-full bg-gray-300 text-gray-800">{status}</span>;
    }
  };

  const formatDate = (dateString: string | null) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat(locale, {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date);
  };

  if (liveTexts.length === 0) {
    return (
      <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-12 text-center">
        <svg
          className="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
          />
        </svg>
        <p className="text-gray-600 dark:text-gray-400 text-lg">{t.noLiveTexts}</p>
      </div>
    );
  }

  return (
    <div className="bg-surface dark:bg-surface-dark rounded-lg shadow overflow-hidden">
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead className="bg-surface-sunken dark:bg-surface-dark">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-secondary dark:text-gray-400 uppercase tracking-wider">
                {t.title}
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-secondary dark:text-gray-400 uppercase tracking-wider">
                {t.status}
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-secondary dark:text-gray-400 uppercase tracking-wider">
                {t.category}
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-secondary dark:text-gray-400 uppercase tracking-wider">
                {t.startTime}
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-secondary dark:text-gray-400 uppercase tracking-wider">
                {t.author}
              </th>
              <th className="px-6 py-3 text-right text-xs font-medium text-secondary dark:text-gray-400 uppercase tracking-wider">
                {t.actions}
              </th>
            </tr>
          </thead>
          <tbody className="bg-surface dark:bg-surface-dark divide-y divide-gray-200 dark:divide-gray-700">
            {liveTexts.map((liveText) => (
              <tr key={liveText.id} className="hover:bg-surface-sunken dark:hover:bg-gray-700 transition-colors">
                <td className="px-6 py-4">
                  <div className="text-sm font-medium text-primary dark:text-primary-dark">
                    {liveText.title}
                  </div>
                  {liveText.description && (
                    <div className="text-sm text-secondary dark:text-gray-400 line-clamp-1">
                      {liveText.description}
                    </div>
                  )}
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  {getStatusBadge(liveText.status)}
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="text-sm text-primary dark:text-primary-dark">
                    {liveText.category?.title || '-'}
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="text-sm text-secondary dark:text-gray-400">
                    {formatDate(liveText.startTime)}
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="text-sm text-primary dark:text-primary-dark">
                    {liveText.author?.username || '-'}
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div className="flex items-center justify-end gap-2">
                    {/* View */}
                    <Link
                      href={`/${locale}/live/${liveText.slug}`}
                      className="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                      title={t.view}
                    >
                      <svg
                        className="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                        />
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                        />
                      </svg>
                    </Link>

                    {/* Edit */}
                    <Link
                      href={`/${locale}/admin/live-texts/${liveText.id}/edit`}
                      className="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                      title={t.edit}
                    >
                      <svg
                        className="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                        />
                      </svg>
                    </Link>

                    {/* Manage Posts */}
                    <Link
                      href={`/${locale}/admin/live-texts/${liveText.id}/posts`}
                      className="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300"
                      title={t.managePosts}
                    >
                      <svg
                        className="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M4 6h16M4 10h16M4 14h16M4 18h16"
                        />
                      </svg>
                    </Link>

                    {/* Delete */}
                    <button
                      onClick={() => handleDelete(liveText.id)}
                      disabled={deletingId === liveText.id}
                      className="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
                      title={t.delete}
                    >
                      {deletingId === liveText.id ? (
                        <svg
                          className="w-5 h-5 animate-spin"
                          fill="none"
                          viewBox="0 0 24 24"
                        >
                          <circle
                            className="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            strokeWidth="4"
                          />
                          <path
                            className="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                          />
                        </svg>
                      ) : (
                        <svg
                          className="w-5 h-5"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                          />
                        </svg>
                      )}
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
