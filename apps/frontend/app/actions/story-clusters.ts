'use server';

import { getAccessToken } from '@/lib/dal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

export interface StoryClusterItem {
  id: number;
  primaryHeadline: string;
  summaryShort: string | null;
  importanceScore: number;
  sourceCount: number;
  articleCount: number;
  regionTags: string[] | null;
  status: 'auto' | 'reviewed' | 'approved' | 'rejected' | 'promoted';
  firstSeenAt: string;
  lastUpdatedAt: string;
  promotedToPressRelease: boolean;
  editorialBoost: number;
  topics: { id: number; title: string }[];
  distinctCountries: string[];
}

export interface StoryClusterDetail extends StoryClusterItem {
  summaryMedium: string | null;
  whyItMatters: string | null;
  keyFacts: string[] | null;
  pressReleases: {
    id: number;
    title: string;
    sourceUrl: string | null;
    sourceHostname: string | null;
    sourceName: string | null;
    receivedAt: string;
    status: string;
  }[];
}

export interface StoryClusterListResult {
  items: StoryClusterItem[];
  totalItems: number;
  error?: string;
}

export async function fetchStoryClusters(
  status?: string,
  page: number = 1,
  orderBy: string = 'importanceScore',
  orderDir: string = 'desc',
): Promise<StoryClusterListResult> {
  const token = await getAccessToken();
  if (!token) {
    return { items: [], totalItems: 0, error: 'Nu ești autentificat' };
  }

  const params = new URLSearchParams({
    itemsPerPage: '20',
    page: String(page),
    [`order[${orderBy}]`]: orderDir,
  });
  if (status && status !== 'all') {
    params.set('status', status);
  }

  const res = await fetch(`${API_BASE_URL}/api/story_clusters?${params}`, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/ld+json',
    },
    cache: 'no-store',
  });

  if (!res.ok) {
    return { items: [], totalItems: 0, error: `API error: ${res.status}` };
  }

  const data = await res.json();

  return {
    items: data['member'] ?? data['hydra:member'] ?? [],
    totalItems: data['totalItems'] ?? data['hydra:totalItems'] ?? 0,
  };
}

export async function fetchStoryClusterDetail(
  id: number,
): Promise<StoryClusterDetail | null> {
  const token = await getAccessToken();
  if (!token) return null;

  const res = await fetch(`${API_BASE_URL}/api/story_clusters/${id}`, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/ld+json',
    },
    cache: 'no-store',
  });

  if (!res.ok) return null;

  return res.json();
}

export async function fetchTopClusters(
  limit: number = 10,
  sinceHours: number = 24,
): Promise<StoryClusterItem[]> {
  const token = await getAccessToken();
  if (!token) return [];

  const res = await fetch(
    `${API_BASE_URL}/api/story-clusters/top?limit=${limit}&since_hours=${sinceHours}`,
    {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
      cache: 'no-store',
    },
  );

  if (!res.ok) return [];

  return res.json();
}

export async function updateClusterStatus(
  id: number,
  status: string,
): Promise<{ success: boolean; error?: string }> {
  const token = await getAccessToken();
  if (!token) return { success: false, error: 'Nu ești autentificat' };

  const res = await fetch(`${API_BASE_URL}/api/story_clusters/${id}`, {
    method: 'PATCH',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/merge-patch+json',
    },
    body: JSON.stringify({ status }),
  });

  if (!res.ok) {
    return { success: false, error: `API error: ${res.status}` };
  }

  return { success: true };
}

export async function updateClusterBoost(
  id: number,
  editorialBoost: number,
): Promise<{ success: boolean; error?: string }> {
  const token = await getAccessToken();
  if (!token) return { success: false, error: 'Nu ești autentificat' };

  const res = await fetch(`${API_BASE_URL}/api/story_clusters/${id}`, {
    method: 'PATCH',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/merge-patch+json',
    },
    body: JSON.stringify({ editorialBoost }),
  });

  if (!res.ok) {
    return { success: false, error: `API error: ${res.status}` };
  }

  return { success: true };
}

export async function promoteCluster(
  id: number,
): Promise<{ success: boolean; error?: string }> {
  const token = await getAccessToken();
  if (!token) return { success: false, error: 'Nu ești autentificat' };

  const res = await fetch(`${API_BASE_URL}/api/story-clusters/${id}/promote`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
    },
  });

  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    return { success: false, error: data.error ?? `API error: ${res.status}` };
  }

  return { success: true };
}

export async function regenerateSummary(
  id: number,
): Promise<{ success: boolean; error?: string }> {
  const token = await getAccessToken();
  if (!token) return { success: false, error: 'Nu ești autentificat' };

  const res = await fetch(`${API_BASE_URL}/api/story-clusters/${id}/regenerate-summary`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
    },
  });

  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    return { success: false, error: data.error ?? `API error: ${res.status}` };
  }

  return { success: true };
}

export async function getAutoPromoteThreshold(): Promise<number> {
  const token = await getAccessToken();
  if (!token) return 0.7;

  const res = await fetch(`${API_BASE_URL}/api/settings/auto-promote-threshold`, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
    },
    cache: 'no-store',
  });

  if (!res.ok) return 0.7;

  const data = await res.json();
  return data.threshold ?? 0.7;
}

export async function setAutoPromoteThreshold(
  threshold: number,
): Promise<{ success: boolean; threshold?: number; error?: string }> {
  const token = await getAccessToken();
  if (!token) return { success: false, error: 'Nu ești autentificat' };

  const res = await fetch(`${API_BASE_URL}/api/settings/auto-promote-threshold`, {
    method: 'PUT',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ threshold }),
  });

  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    return { success: false, error: data.error ?? `API error: ${res.status}` };
  }

  const data = await res.json();
  return { success: true, threshold: data.threshold };
}
