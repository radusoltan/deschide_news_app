'use client';

import { useState, useEffect, useCallback } from 'react';
import { Bell, ChevronLeft, ChevronRight } from 'lucide-react';
import { fetchNotifications } from '@/app/actions/notifications';
import { markNotificationAsRead } from '@/app/actions/notifications';
import type { AdminNotification } from '@/lib/types/notification';
import { NotificationType } from '@/lib/types/notification';
import NotificationItem from '../components/NotificationItem';

const TYPE_LABELS: Record<string, string> = {
  '': 'Toate tipurile',
  [NotificationType.ARTICLE_PUBLISHED]: 'Articol publicat',
  [NotificationType.ARTICLE_UPDATED]: 'Articol actualizat',
  [NotificationType.USER_LOGIN]: 'Autentificare',
  [NotificationType.USER_ACTION]: 'Actiune utilizator',
  [NotificationType.SYSTEM_ERROR]: 'Eroare sistem',
  [NotificationType.JOB_FAILED]: 'Job esuat',
};

const READ_FILTER_OPTIONS = [
  { value: '', label: 'Toate' },
  { value: 'false', label: 'Necitite' },
  { value: 'true', label: 'Citite' },
];

export default function NotificationsPage() {
  const [notifications, setNotifications] = useState<AdminNotification[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [page, setPage] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [typeFilter, setTypeFilter] = useState('');
  const [readFilter, setReadFilter] = useState('');
  const limit = 20;

  const loadNotifications = useCallback(async () => {
    setIsLoading(true);
    try {
      const isRead =
        readFilter === '' ? undefined : readFilter === 'true';
      const type = typeFilter || undefined;
      const data = await fetchNotifications(page, limit, isRead, type);
      setNotifications(data.items);
      setTotalItems(data.totalItems);
    } catch (err) {
      console.error('Failed to fetch notifications:', err);
    } finally {
      setIsLoading(false);
    }
  }, [page, typeFilter, readFilter]);

  useEffect(() => {
    loadNotifications();
  }, [loadNotifications]);

  const handleMarkAsRead = async (id: string) => {
    try {
      await markNotificationAsRead(id);
      setNotifications((prev) =>
        prev.map((n) =>
          n.id === id
            ? { ...n, isRead: true, readAt: new Date().toISOString() }
            : n
        )
      );
    } catch (err) {
      console.error('Failed to mark as read:', err);
    }
  };

  const totalPages = Math.ceil(totalItems / limit);

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">
          Notificari
        </h1>
        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
          {totalItems} notificari in total
        </p>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 mb-4">
        <select
          value={typeFilter}
          onChange={(e) => {
            setTypeFilter(e.target.value);
            setPage(1);
          }}
          className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        >
          {Object.entries(TYPE_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>

        <select
          value={readFilter}
          onChange={(e) => {
            setReadFilter(e.target.value);
            setPage(1);
          }}
          className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        >
          {READ_FILTER_OPTIONS.map(({ value, label }) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </div>

      {/* List */}
      <div className="bg-white rounded-lg shadow dark:bg-gray-800 overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500" />
          </div>
        ) : notifications.length > 0 ? (
          <div className="divide-y divide-gray-200 dark:divide-gray-700">
            {notifications.map((notification) => (
              <NotificationItem
                key={notification.id}
                notification={notification}
                onMarkAsRead={handleMarkAsRead}
              />
            ))}
          </div>
        ) : (
          <div className="flex flex-col items-center justify-center py-12">
            <Bell className="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
            <p className="text-gray-500 dark:text-gray-400">
              Nu exista notificari
            </p>
          </div>
        )}
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between mt-4">
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Pagina {page} din {totalPages}
          </p>
          <div className="flex gap-2">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page === 1}
              className="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
            >
              <ChevronLeft className="w-4 h-4" />
              Inapoi
            </button>
            <button
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              disabled={page === totalPages}
              className="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
            >
              Inainte
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
