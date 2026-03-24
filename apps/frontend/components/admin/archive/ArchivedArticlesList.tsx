'use client';

import { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { buildLocalizedUrl, buildArticleUrl } from '@/lib/utils/url-builder';
import type { Locale } from '@/lib/types';

interface Category {
  id: number;
  title: string;
  slug: string;
}

interface Article {
  '@id': string;
  id: number;
  title: string;
  slug: string;
  category: Category;
  publishedAt: string;
  archivedAt: string;
  archiveReason: string;
  status: string;
}

interface HydraCollection {
  '@context': string;
  '@id': string;
  '@type': string;
  totalItems: number;
  member: Article[];
}

interface ArchivedArticlesListProps {
  token: string;
  onArticleUnarchived?: () => void;
  locale?: string;
}

const reasonLabels = {
  ro: {
    old_content: 'Conținut vechi',
    legal_request: 'Cerere legală',
    duplicate: 'Duplicat',
    quality: 'Calitate slabă',
    outdated: 'Depășit',
    manual: 'Manual',
    migration: 'Migrare'
  },
  en: {
    old_content: 'Old Content',
    legal_request: 'Legal Request',
    duplicate: 'Duplicate',
    quality: 'Poor Quality',
    outdated: 'Outdated',
    manual: 'Manual',
    migration: 'Migration'
  },
  ru: {
    old_content: 'Старый контент',
    legal_request: 'Правовой запрос',
    duplicate: 'Дубликат',
    quality: 'Низкое качество',
    outdated: 'Устаревший',
    manual: 'Вручную',
    migration: 'Миграция'
  }
};

const translations = {
  ro: {
    title: 'Articole Arhivate',
    itemsInArchive: 'articole în arhivă',
    id: 'ID',
    titleCol: 'Titlu',
    category: 'Categorie',
    published: 'Publicat',
    archived: 'Arhivat',
    reason: 'Motiv',
    actions: 'Acțiuni',
    unarchive: 'Restabilește',
    view: 'Vezi',
    previous: 'Anterior',
    next: 'Următor',
    page: 'Pagina',
    of: 'din',
    confirmUnarchive: 'Restabilești acest articol?',
    confirmMessage: 'Articolul va fi readus în lista activă.',
    cancel: 'Anulează',
    confirm: 'Confirmă',
    successUnarchive: 'Articolul a fost restabilit cu succes',
    errorUnarchive: 'Eroare la restabilirea articolului',
    loading: 'Se încarcă...',
    noArticles: 'Nu există articole arhivate'
  },
  en: {
    title: 'Archived Articles',
    itemsInArchive: 'articles in archive',
    id: 'ID',
    titleCol: 'Title',
    category: 'Category',
    published: 'Published',
    archived: 'Archived',
    reason: 'Reason',
    actions: 'Actions',
    unarchive: 'Restore',
    view: 'View',
    previous: 'Previous',
    next: 'Next',
    page: 'Page',
    of: 'of',
    confirmUnarchive: 'Restore this article?',
    confirmMessage: 'The article will be returned to the active list.',
    cancel: 'Cancel',
    confirm: 'Confirm',
    successUnarchive: 'Article restored successfully',
    errorUnarchive: 'Error restoring article',
    loading: 'Loading...',
    noArticles: 'No archived articles'
  },
  ru: {
    title: 'Архивные статьи',
    itemsInArchive: 'статей в архиве',
    id: 'ID',
    titleCol: 'Заголовок',
    category: 'Категория',
    published: 'Опубликовано',
    archived: 'Архивировано',
    reason: 'Причина',
    actions: 'Действия',
    unarchive: 'Восстановить',
    view: 'Смотреть',
    previous: 'Предыдущая',
    next: 'Следующая',
    page: 'Страница',
    of: 'из',
    confirmUnarchive: 'Восстановить эту статью?',
    confirmMessage: 'Статья будет возвращена в активный список.',
    cancel: 'Отмена',
    confirm: 'Подтвердить',
    successUnarchive: 'Статья успешно восстановлена',
    errorUnarchive: 'Ошибка при восстановлении статьи',
    loading: 'Загрузка...',
    noArticles: 'Нет архивных статей'
  }
};

const reasonBadgeColors: Record<string, string> = {
  old_content: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
  legal_request: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
  duplicate: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
  quality: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
  outdated: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
  manual: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
  migration: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300'
};

export default function ArchivedArticlesList({
  token,
  onArticleUnarchived,
  locale = 'ro'
}: ArchivedArticlesListProps) {
  const [articles, setArticles] = useState<Article[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [unarchivingId, setUnarchivingId] = useState<number | null>(null);
  const [confirmModal, setConfirmModal] = useState<{ show: boolean; article: Article | null }>({
    show: false,
    article: null
  });
  const [notification, setNotification] = useState<{ show: boolean; message: string; type: 'success' | 'error' }>({
    show: false,
    message: '',
    type: 'success'
  });

  const itemsPerPage = 20;
  const totalPages = Math.ceil(totalItems / itemsPerPage);
  const t = translations[locale as keyof typeof translations] || translations.ro;
  const reasons = reasonLabels[locale as keyof typeof reasonLabels] || reasonLabels.ro;

  const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

  const showNotification = useCallback((message: string, type: 'success' | 'error') => {
    setNotification({ show: true, message, type });
    setTimeout(() => {
      setNotification({ show: false, message: '', type: 'success' });
    }, 3000);
  }, []);

  const fetchArticles = useCallback(async () => {
    setLoading(true);
    try {
      const response = await fetch(
        `${apiUrl}/api/archived_articles?page=${page}&itemsPerPage=${itemsPerPage}`,
        {
          headers: {
            'Accept': 'application/ld+json',
            'Accept-Language': locale
          }
        }
      );

      if (!response.ok) {
        throw new Error('Failed to fetch archived articles');
      }

      const data: HydraCollection = await response.json();
      setArticles(data.member || []);
      setTotalItems(data.totalItems || 0);
    } catch (error) {
      console.error('Error fetching archived articles:', error);
      showNotification(t.errorUnarchive, 'error');
    } finally {
      setLoading(false);
    }
  }, [apiUrl, page, itemsPerPage, locale, t.errorUnarchive, showNotification]);

  useEffect(() => {
    fetchArticles();
  }, [fetchArticles]);

  const formatDate = (dateString: string) => {
    const localeMap: Record<string, string> = {
      ro: 'ro-RO',
      en: 'en-US',
      ru: 'ru-RU'
    };

    return new Date(dateString).toLocaleDateString(localeMap[locale] || 'ro-RO', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const truncateTitle = (title: string, maxLength: number = 50) => {
    if (title.length <= maxLength) return title;
    return title.substring(0, maxLength) + '...';
  };

  const handleUnarchiveClick = (article: Article) => {
    setConfirmModal({ show: true, article });
  };

  const handleUnarchiveConfirm = async () => {
    if (!confirmModal.article) return;

    const articleId = confirmModal.article.id;
    setUnarchivingId(articleId);
    setConfirmModal({ show: false, article: null });

    try {
      const response = await fetch(
        `${apiUrl}/api/admin/articles/${articleId}/unarchive`,
        {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'Accept': 'application/ld+json'
          }
        }
      );

      if (!response.ok) {
        throw new Error('Failed to unarchive article');
      }

      // Optimistic update - remove from list
      setArticles(prev => prev.filter(a => a.id !== articleId));
      setTotalItems(prev => prev - 1);

      showNotification(t.successUnarchive, 'success');

      // Call callback to refresh stats
      if (onArticleUnarchived) {
        onArticleUnarchived();
      }

      // If current page is empty after removal and not first page, go to previous page
      if (articles.length === 1 && page > 1) {
        setPage(prev => prev - 1);
      }
    } catch (error) {
      console.error('Error unarchiving article:', error);
      showNotification(t.errorUnarchive, 'error');
      // Refresh list on error to ensure consistency
      fetchArticles();
    } finally {
      setUnarchivingId(null);
    }
  };

  const LoadingSkeleton = () => (
    <>
      {[...Array(5)].map((_, i) => (
        <tr key={i} className="animate-pulse">
          <td className="px-4 py-3">
            <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-12"></div>
          </td>
          <td className="px-4 py-3">
            <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-64"></div>
          </td>
          <td className="px-4 py-3">
            <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded w-20"></div>
          </td>
          <td className="px-4 py-3">
            <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-24"></div>
          </td>
          <td className="px-4 py-3">
            <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-24"></div>
          </td>
          <td className="px-4 py-3">
            <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded w-28"></div>
          </td>
          <td className="px-4 py-3">
            <div className="flex gap-2">
              <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-20"></div>
              <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-16"></div>
            </div>
          </td>
        </tr>
      ))}
    </>
  );

  return (
    <>
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white">{t.title}</h3>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            {totalItems} {t.itemsInArchive}
          </p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  {t.id}
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  {t.titleCol}
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  {t.category}
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  {t.published}
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  {t.archived}
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  {t.reason}
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  {t.actions}
                </th>
              </tr>
            </thead>
            <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              {loading ? (
                <LoadingSkeleton />
              ) : articles.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                    {t.noArticles}
                  </td>
                </tr>
              ) : (
                articles.map(article => (
                  <tr
                    key={article.id}
                    className="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                  >
                    <td className="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 font-medium">
                      {article.id}
                    </td>
                    <td className="px-4 py-3 text-sm">
                      <Link
                        href={`/${locale}/admin/articles/${article.id}/edit`}
                        className="text-blue-600 dark:text-blue-400 hover:underline"
                        title={article.title}
                      >
                        {truncateTitle(article.title)}
                      </Link>
                    </td>
                    <td className="px-4 py-3">
                      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                        {article.category?.title || '-'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                      {formatDate(article.publishedAt)}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                      {formatDate(article.archivedAt)}
                    </td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                        reasonBadgeColors[article.archiveReason] || reasonBadgeColors.manual
                      }`}>
                        {reasons[article.archiveReason as keyof typeof reasons] || article.archiveReason}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => handleUnarchiveClick(article)}
                          disabled={unarchivingId === article.id}
                          className="px-3 py-1 text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed rounded transition-colors"
                        >
                          {unarchivingId === article.id ? t.loading : t.unarchive}
                        </button>
                        <Link
                          href={buildArticleUrl(article as any, locale as Locale)}
                          target="_blank"
                          className="px-3 py-1 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 border border-blue-600 dark:border-blue-400 rounded transition-colors"
                        >
                          {t.view}
                        </Link>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {totalPages > 1 && (
          <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <p className="text-sm text-gray-500 dark:text-gray-400">
              {t.page} {page} {t.of} {totalPages}
            </p>
            <div className="flex gap-2">
              <button
                onClick={() => setPage(p => Math.max(1, p - 1))}
                disabled={page === 1 || loading}
                className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {t.previous}
              </button>
              <button
                onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                disabled={page === totalPages || loading}
                className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {t.next}
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Confirmation Modal */}
      {confirmModal.show && confirmModal.article && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
              {t.confirmUnarchive}
            </h3>
            <p className="text-gray-600 dark:text-gray-400 mb-1">
              {confirmModal.article.title}
            </p>
            <p className="text-sm text-gray-500 dark:text-gray-500 mb-6">
              {t.confirmMessage}
            </p>
            <div className="flex justify-end gap-3">
              <button
                onClick={() => setConfirmModal({ show: false, article: null })}
                className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
              >
                {t.cancel}
              </button>
              <button
                onClick={handleUnarchiveConfirm}
                className="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded transition-colors"
              >
                {t.confirm}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Notification Toast */}
      {notification.show && (
        <div className="fixed bottom-4 right-4 z-50 animate-fade-in">
          <div className={`rounded-lg shadow-lg p-4 ${
            notification.type === 'success'
              ? 'bg-green-500 text-white'
              : 'bg-red-500 text-white'
          }`}>
            {notification.message}
          </div>
        </div>
      )}
    </>
  );
}
