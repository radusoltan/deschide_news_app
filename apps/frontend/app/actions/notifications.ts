'use server';

import { getAccessToken } from '@/lib/dal';
import type { PaginatedNotifications, UnreadCountResponse } from '@/lib/types/notification';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

export async function fetchUnreadCount(): Promise<UnreadCountResponse> {
  try {
    const token = await getAccessToken();
    if (!token) return { count: 0 };

    const res = await fetch(`${API_BASE_URL}/api/admin/notifications/unread-count`, {
      headers: { Authorization: `Bearer ${token}` },
      cache: 'no-store',
    });

    if (!res.ok) return { count: 0 };
    return res.json();
  } catch {
    return { count: 0 };
  }
}

export async function fetchNotifications(
  page = 1,
  limit = 20,
  isRead?: boolean,
  type?: string,
): Promise<PaginatedNotifications> {
  try {
    const token = await getAccessToken();
    if (!token) return { items: [], totalItems: 0, page, limit };

    const params = new URLSearchParams({ page: String(page), limit: String(limit) });
    if (isRead !== undefined) params.set('isRead', String(isRead));
    if (type) params.set('type', type);

    const res = await fetch(`${API_BASE_URL}/api/admin/notifications?${params}`, {
      headers: { Authorization: `Bearer ${token}` },
      cache: 'no-store',
    });

    if (!res.ok) return { items: [], totalItems: 0, page, limit };
    return res.json();
  } catch {
    return { items: [], totalItems: 0, page, limit };
  }
}

export async function markNotificationAsRead(id: string): Promise<void> {
  const token = await getAccessToken();
  if (!token) return;

  await fetch(`${API_BASE_URL}/api/admin/notifications/${id}/read`, {
    method: 'PATCH',
    headers: { Authorization: `Bearer ${token}` },
  });
}

export async function markAllNotificationsAsRead(): Promise<{ updated: number }> {
  const token = await getAccessToken();
  if (!token) return { updated: 0 };

  const res = await fetch(`${API_BASE_URL}/api/admin/notifications/mark-all-read`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}` },
  });

  if (!res.ok) return { updated: 0 };
  return res.json();
}

export async function deleteNotification(id: string): Promise<void> {
  const token = await getAccessToken();
  if (!token) return;

  await fetch(`${API_BASE_URL}/api/admin/notifications/${id}`, {
    method: 'DELETE',
    headers: { Authorization: `Bearer ${token}` },
  });
}
