'use server';

import { getAccessToken } from '@/lib/dal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

export interface PressReleaseItem {
  id: number;
  title: string;
  lead: string | null;
  content: string;
  sourceEmailId: string | null;
  senderAddress: string | null;
  senderName: string | null;
  sourceUrl: string | null;
  categorySlug: string;
  emailSubject: string | null;
  status: 'pending' | 'approved' | 'rejected';
  receivedAt: string;
  createdAt: string;
  processedAt: string | null;
  article: { id: number; title: string } | null;
  articleId: number | null;
  contentLength: number;
  sourceType: 'email' | 'scrape' | 'manual' | 'aggregator';
  sourceName: string | null;
  originalLanguage: string | null;
  rejectionReason: string | null;
}

export interface PressReleaseListResult {
  items: PressReleaseItem[];
  totalItems: number;
  error?: string;
}

export async function fetchPressReleases(
  status?: string,
  page: number = 1,
  sourceType?: string,
  originalLanguage?: string,
): Promise<PressReleaseListResult> {
  const token = await getAccessToken();
  if (!token) {
    return { items: [], totalItems: 0, error: 'Nu ești autentificat' };
  }

  const params = new URLSearchParams({ itemsPerPage: '20' });
  if (status) params.set('status', status);
  if (page > 1) params.set('page', String(page));
  if (sourceType) params.set('sourceType', sourceType);
  if (originalLanguage) params.set('originalLanguage', originalLanguage);

  try {
    const res = await fetch(`${API_BASE_URL}/api/press_releases?${params}`, {
      headers: {
        Accept: 'application/ld+json',
        Authorization: `Bearer ${token}`,
      },
      cache: 'no-store',
    });

    if (!res.ok) {
      return { items: [], totalItems: 0, error: `Eroare API: ${res.status}` };
    }

    const data = await res.json();
    return {
      items: data.member || [],
      totalItems: data.totalItems || 0,
    };
  } catch (err) {
    return { items: [], totalItems: 0, error: String(err) };
  }
}

export async function approvePressRelease(id: number): Promise<{ success: boolean; articleId?: number; error?: string }> {
  const token = await getAccessToken();
  if (!token) return { success: false, error: 'Nu ești autentificat' };

  try {
    const res = await fetch(`${API_BASE_URL}/api/press_releases/${id}/approve`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/ld+json',
        Accept: 'application/ld+json',
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({}),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      return { success: false, error: err['hydra:description'] || err.message || `HTTP ${res.status}` };
    }

    const data = await res.json();
    return { success: true, articleId: data.article?.id };
  } catch (err) {
    return { success: false, error: String(err) };
  }
}

export interface FetchEmailsResult {
  success: boolean;
  queued: number;
  skipped: number;
  errors: number;
  error?: string;
}

export async function fetchPressEmails(): Promise<FetchEmailsResult> {
  const token = await getAccessToken();
  if (!token) {
    return { success: false, queued: 0, skipped: 0, errors: 0, error: 'Nu ești autentificat' };
  }

  try {
    const res = await fetch(`${API_BASE_URL}/api/press-emails/fetch`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      cache: 'no-store',
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      return {
        success: false,
        queued: 0,
        skipped: 0,
        errors: 1,
        error: err.error || `Eroare HTTP ${res.status}`,
      };
    }

    return await res.json();
  } catch (err) {
    return { success: false, queued: 0, skipped: 0, errors: 1, error: String(err) };
  }
}

export interface SourceTypeCounts {
  email: number;
  scrape: number;
  manual: number;
  aggregator: number;
  total: number;
}

export async function fetchPressReleaseCounts(): Promise<SourceTypeCounts> {
  const token = await getAccessToken();
  if (!token) return { email: 0, scrape: 0, manual: 0, aggregator: 0, total: 0 };

  try {
    const res = await fetch(`${API_BASE_URL}/api/press_releases/counts`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      cache: 'no-store',
    });

    if (!res.ok) return { email: 0, scrape: 0, manual: 0, aggregator: 0, total: 0 };
    return await res.json();
  } catch {
    return { email: 0, scrape: 0, manual: 0, aggregator: 0, total: 0 };
  }
}

export async function rejectPressRelease(id: number, reason?: string): Promise<{ success: boolean; error?: string }> {
  const token = await getAccessToken();
  if (!token) return { success: false, error: 'Nu ești autentificat' };

  try {
    const res = await fetch(`${API_BASE_URL}/api/press_releases/${id}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/merge-patch+json',
        Accept: 'application/ld+json',
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({
        status: 'rejected',
        ...(reason ? { rejectionReason: reason } : {}),
      }),
    });

    if (!res.ok) {
      return { success: false, error: `HTTP ${res.status}` };
    }

    return { success: true };
  } catch (err) {
    return { success: false, error: String(err) };
  }
}
