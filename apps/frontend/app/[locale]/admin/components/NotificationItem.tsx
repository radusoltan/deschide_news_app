'use client';

import {
  Newspaper,
  UserCircle,
  AlertTriangle,
  Cog,
  type LucideIcon,
} from 'lucide-react';
import moment from 'moment';
import { useParams, useRouter } from 'next/navigation';
import type { AdminNotification } from '@/lib/types/notification';
import { NotificationType, NotificationImportance } from '@/lib/types/notification';

const TYPE_ICON_MAP: Record<string, LucideIcon> = {
  [NotificationType.ARTICLE_PUBLISHED]: Newspaper,
  [NotificationType.ARTICLE_UPDATED]: Newspaper,
  [NotificationType.USER_LOGIN]: UserCircle,
  [NotificationType.USER_ACTION]: UserCircle,
  [NotificationType.SYSTEM_ERROR]: AlertTriangle,
  [NotificationType.JOB_FAILED]: Cog,
};

const IMPORTANCE_COLOR_MAP: Record<string, string> = {
  [NotificationImportance.URGENT]: 'text-red-500',
  [NotificationImportance.HIGH]: 'text-orange-500',
  [NotificationImportance.MEDIUM]: 'text-blue-500',
  [NotificationImportance.LOW]: 'text-gray-400',
};

interface NotificationItemProps {
  notification: AdminNotification;
  onMarkAsRead: (id: string) => void;
  compact?: boolean;
}

export default function NotificationItem({
  notification,
  onMarkAsRead,
  compact = false,
}: NotificationItemProps) {
  const params = useParams();
  const router = useRouter();
  const locale = params.locale as string;

  const Icon = TYPE_ICON_MAP[notification.type] ?? AlertTriangle;
  const iconColor = IMPORTANCE_COLOR_MAP[notification.importance] ?? 'text-gray-400';
  const timeAgo = moment(notification.createdAt).fromNow();

  const handleClick = () => {
    if (!notification.isRead) {
      onMarkAsRead(notification.id);
    }
    if (notification.actionUrl) {
      router.push(`/${locale}${notification.actionUrl}`);
    }
  };

  return (
    <button
      onClick={handleClick}
      className={`w-full text-left flex items-start gap-3 px-4 py-3 transition-colors hover:bg-gray-100 dark:hover:bg-gray-600 ${
        notification.isRead
          ? 'bg-transparent'
          : 'bg-blue-50 dark:bg-blue-900/20'
      } ${compact ? 'px-3 py-2' : ''}`}
    >
      <div className={`mt-0.5 flex-shrink-0 ${iconColor}`}>
        <Icon className={compact ? 'w-4 h-4' : 'w-5 h-5'} />
      </div>
      <div className="flex-1 min-w-0">
        <p
          className={`text-sm ${
            notification.isRead
              ? 'text-gray-600 dark:text-gray-400'
              : 'text-gray-900 dark:text-white font-medium'
          } ${compact ? 'text-xs' : ''} truncate`}
        >
          {notification.title}
        </p>
        {notification.message && !compact && (
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
            {notification.message}
          </p>
        )}
        <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">{timeAgo}</p>
      </div>
      {!notification.isRead && (
        <span className="w-2 h-2 mt-2 bg-blue-500 rounded-full flex-shrink-0" />
      )}
    </button>
  );
}
