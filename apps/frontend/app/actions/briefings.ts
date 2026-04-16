'use server';

import { getAccessToken } from '@/lib/dal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

export interface TopicBriefingItem {
  id: number;
  topic: {
    id: number;
    title: string;
  };
  cadence: 'hourly' | 'daily' | 'weekly';
  status: 'pending' | 'generating' | 'draft' | 'polished' | 'published' | 'failed';
  title: string | null;
  summaryShort: string | null;
  summaryLong: string | null;
  keyFacts: string[] | null;
  whyItMatters: string | null;
  claudePolished: boolean;
  prCount: number;
  periodFrom: string;
  periodTo: string;
  generatedAt: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface BriefingListResult {
  items: TopicBriefingItem[];
  totalItems: number;
  error?: string;
}

export async function fetchBriefings(
  cadence?: string,
  topicId?: number,
  page: number = 1,
): Promise<BriefingListResult> {
  const token = await getAccessToken();
  if (!token) {
    return { items: [], totalItems: 0, error: 'Nu ești autentificat' };
  }

  const params = new URLSearchParams({
    itemsPerPage: '20',
    page: String(page),
    'order[createdAt]': 'desc',
  });

  if (cadence && cadence !== 'all') {
    params.set('cadence', cadence);
  }
  if (topicId) {
    params.set('topic.id', String(topicId));
  }

  const res = await fetch(`${API_BASE_URL}/api/admin/briefings?${params}`, {
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
