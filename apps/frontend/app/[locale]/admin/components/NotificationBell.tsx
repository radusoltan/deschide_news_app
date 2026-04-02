'use client';

import { useState, useEffect } from 'react';
import { Bell } from 'lucide-react';
import { useNotifications } from '@/lib/hooks/useNotifications';
import NotificationDropdown from './NotificationDropdown';

interface NotificationBellProps {
  username: string;
}

export default function NotificationBell({ username }: NotificationBellProps) {
  const { notifications, unreadCount, markAsRead, markAllRead } =
    useNotifications(username);
  const [isOpen, setIsOpen] = useState(false);

  // Request browser notification permission on mount
  useEffect(() => {
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission();
    }
  }, []);

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setIsOpen((prev) => !prev)}
        className="p-2 text-secondary rounded-lg hover:text-primary hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700"
        aria-label={`Notificari${unreadCount > 0 ? ` (${unreadCount} necitite)` : ''}`}
      >
        <Bell className="w-6 h-6" />
        {unreadCount > 0 && (
          <span className="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white bg-red-500 rounded-full">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {isOpen && (
        <NotificationDropdown
          notifications={notifications}
          onMarkAsRead={(id) => markAsRead(id)}
          onMarkAllRead={() => markAllRead()}
          onClose={() => setIsOpen(false)}
        />
      )}
    </div>
  );
}
