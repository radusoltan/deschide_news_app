export enum NotificationType {
  ARTICLE_PUBLISHED = 'article_published',
  ARTICLE_UPDATED = 'article_updated',
  ARTICLE_AUTO_CREATED = 'article_auto_created',
  ARTICLE_TRANSLATED = 'article_translated',
  USER_LOGIN = 'user_login',
  USER_ACTION = 'user_action',
  SYSTEM_ERROR = 'system_error',
  JOB_FAILED = 'job_failed',
}

export enum NotificationImportance {
  LOW = 'low',
  MEDIUM = 'medium',
  HIGH = 'high',
  URGENT = 'urgent',
}

export interface AdminNotification {
  id: string;
  type: NotificationType;
  importance: NotificationImportance;
  title: string;
  message: string | null;
  relatedEntityType: string | null;
  relatedEntityId: number | null;
  actionUrl: string | null;
  isRead: boolean;
  createdAt: string;
  readAt: string | null;
}

export interface UnreadCountResponse {
  count: number;
}

export interface PaginatedNotifications {
  items: AdminNotification[];
  totalItems: number;
  page: number;
  limit: number;
}
