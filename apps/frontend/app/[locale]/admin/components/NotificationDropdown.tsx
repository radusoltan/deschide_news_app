'use client';

import { useEffect, useRef } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { Bell, CheckCheck } from 'lucide-react';
import type { AdminNotification } from '@/lib/types/notification';
import NotificationItem from './NotificationItem';

interface NotificationDropdownProps {
  notifications: AdminNotification[];
  onMarkAsRead: (id: string) => void;
  onMarkAllRead: () => void;
  onClose: () => void;
}

export default function NotificationDropdown({
  notifications,
  onMarkAsRead,
  onMarkAllRead,
  onClose,
}: NotificationDropdownProps) {
  const params = useParams();
  const locale = params.locale as string;
  const ref = useRef<HTMLDivElement>(null);

  // Close on click outside
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        onClose();
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [onClose]);

  const visibleNotifications = notifications.slice(0, 10);
  const hasUnread = notifications.some((n) => !n.isRead);

  return (
    <div
      ref={ref}
      className="absolute right-0 top-full mt-2 w-80 sm:w-96 bg-white rounded-lg shadow-lg border border-gray-200 dark:bg-gray-700 dark:border-gray-600 z-50 overflow-hidden"
    >
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-600">
        <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
          Notificari
        </h3>
        {hasUnread && (
          <button
            onClick={onMarkAllRead}
            className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
          >
            <CheckCheck className="w-3.5 h-3.5" />
            Marcheaza toate ca citite
          </button>
        )}
      </div>

      {/* List */}
      <div className="max-h-96 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-600">
        {visibleNotifications.length > 0 ? (
          visibleNotifications.map((notification) => (
            <NotificationItem
              key={notification.id}
              notification={notification}
              onMarkAsRead={onMarkAsRead}
              compact
            />
          ))
        ) : (
          <div className="flex flex-col items-center justify-center py-8 px-4">
            <Bell className="w-8 h-8 text-gray-300 dark:text-gray-500 mb-2" />
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Nicio notificare noua
            </p>
          </div>
        )}
      </div>

      {/* Footer */}
      <div className="border-t border-gray-200 dark:border-gray-600">
        <Link
          href={`/${locale}/admin/notifications`}
          onClick={onClose}
          className="block text-center py-2.5 text-sm text-blue-600 hover:bg-gray-50 dark:text-blue-400 dark:hover:bg-gray-600"
        >
          Vezi toate notificarile
        </Link>
      </div>
    </div>
  );
}
