/**
 * Press Releases API - Editorial queue management
 */

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

export interface PressRelease {
  '@id': string;
  '@type': string;
  id: number;
  title: string;
  lead: string | null;
  content: string;
  sourceEmailId: string;
  senderAddress: string;
  senderName: string;
  sourceUrl: string | null;
  categorySlug: string;
  emailSubject: string;
  status: 'pending' | 'approved' | 'rejected';
  receivedAt: string;
  createdAt: string;
  processedAt: string | null;
  processedBy: { id: number; username: string } | null;
  article: { '@id': string; id: number; title: string } | null;
  contentLength: number;
}

export interface PressReleaseCollection {
  '@context': string;
  '@id': string;
  '@type': string;
  totalItems: number;
  member: PressRelease[];
}

export async function getPressReleases(
  token: string,
  params?: { status?: string; page?: number; itemsPerPage?: number }
): Promise<PressReleaseCollection> {
  const searchParams = new URLSearchParams();
  if (params?.status) searchParams.set('status', params.status);
  if (params?.page) searchParams.set('page', String(params.page));
  if (params?.itemsPerPage) searchParams.set('itemsPerPage', String(params.itemsPerPage));

  const query = searchParams.toString();
  const url = `${API_BASE_URL}/api/press_releases${query ? `?${query}` : ''}`;

  const res = await fetch(url, {
    headers: {
      Accept: 'application/ld+json',
      Authorization: `Bearer ${token}`,
    },
    cache: 'no-store',
  });

  if (!res.ok) {
    throw new Error(`Failed to fetch press releases: ${res.status}`);
  }

  return res.json();
}

export async function approvePressRelease(
  id: number,
  token: string
): Promise<PressRelease> {
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
    const error = await res.json().catch(() => ({ message: `HTTP ${res.status}` }));
    throw new Error(error.message || error['hydra:description'] || `Failed to approve: ${res.status}`);
  }

  return res.json();
}

export async function rejectPressRelease(
  id: number,
  token: string
): Promise<PressRelease> {
  const res = await fetch(`${API_BASE_URL}/api/press_releases/${id}`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/merge-patch+json',
      Accept: 'application/ld+json',
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify({ status: 'rejected' }),
  });

  if (!res.ok) {
    throw new Error(`Failed to reject: ${res.status}`);
  }

  return res.json();
}

export async function getPendingCount(token: string): Promise<number> {
  const data = await getPressReleases(token, { status: 'pending', itemsPerPage: 1 });
  return data.totalItems;
}
