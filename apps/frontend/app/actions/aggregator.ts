'use server';

import { getAccessToken } from '@/lib/dal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

// Types
export interface TrendingTopic {
  topicId: number;
  topicName: string;
  score: number;
  articleCount: number;
  velocity: number;
}

export interface AggregatorSourceStat {
  source: string;
  lastRun: string;
  articlesFound: number;
  duplicatesSkipped: number;
  pendingReview: number;
  status: string;
}

export interface TopicProposal {
  id: number;
  title: string;
  reviewStatus: string;
  isActive: boolean;
  createdAt: string;
}

// Server actions
export async function fetchTrendingTopics(days: number = 7, limit: number = 20): Promise<TrendingTopic[]> {
  const token = await getAccessToken();
  if (!token) return [];

  try {
    const res = await fetch(
      `${API_BASE_URL}/api/topics/trending?days=${days}&limit=${limit}`,
      {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
        cache: 'no-store',
      }
    );
    if (!res.ok) return [];
    return await res.json();
  } catch {
    return [];
  }
}

export async function fetchAggregatorStats(): Promise<AggregatorSourceStat[]> {
  const token = await getAccessToken();
  if (!token) return [];

  try {
    const res = await fetch(`${API_BASE_URL}/api/aggregator/stats`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      cache: 'no-store',
    });
    if (!res.ok) return [];
    return await res.json();
  } catch {
    return [];
  }
}

export async function fetchTopicProposals(): Promise<TopicProposal[]> {
  const token = await getAccessToken();
  if (!token) return [];

  try {
    const res = await fetch(`${API_BASE_URL}/api/topics/proposals`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      cache: 'no-store',
    });
    if (!res.ok) return [];
    return await res.json();
  } catch {
    return [];
  }
}

export async function approveTopicProposal(topicId: number): Promise<{ success: boolean; error?: string }> {
  const token = await getAccessToken();
  if (!token) return { success: false, error: 'Nu ești autentificat' };

  try {
    const res = await fetch(`${API_BASE_URL}/api/topics/${topicId}/approve`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
      },
    });
    if (!res.ok) return { success: false, error: `HTTP ${res.status}` };
    return { success: true };
  } catch (err) {
    return { success: false, error: String(err) };
  }
}

export async function rejectTopicProposal(topicId: number): Promise<{ success: boolean; error?: string }> {
  const token = await getAccessToken();
  if (!token) return { success: false, error: 'Nu ești autentificat' };

  try {
    const res = await fetch(`${API_BASE_URL}/api/topics/${topicId}/reject`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
      },
    });
    if (!res.ok) return { success: false, error: `HTTP ${res.status}` };
    return { success: true };
  } catch (err) {
    return { success: false, error: String(err) };
  }
}

export async function triggerAggregatorRun(source?: string): Promise<{ success: boolean; error?: string }> {
  const token = await getAccessToken();
  if (!token) return { success: false, error: 'Nu ești autentificat' };

  try {
    const res = await fetch(`${API_BASE_URL}/api/aggregator/run`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify(source ? { source } : {}),
    });
    if (!res.ok) {
      if (res.status === 403) return { success: false, error: 'Acces interzis — necesită rol Admin' };
      return { success: false, error: `Eroare HTTP ${res.status}` };
    }
    return { success: true };
  } catch (err) {
    return { success: false, error: String(err) };
  }
}

// Dedup Stats types and action
export interface DedupTotals {
  total: number;
  unique: number;
  duplicate: number;
  pendingReview: number;
}

export interface DedupDaily {
  date: string;
  unique: number;
  duplicate: number;
  review: number;
}

export interface DedupStats {
  totals: DedupTotals;
  daily: DedupDaily[];
}

export async function fetchDedupStats(): Promise<DedupStats | null> {
  const token = await getAccessToken();
  if (!token) return null;

  try {
    const res = await fetch(`${API_BASE_URL}/api/aggregator/dedup-stats`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      cache: 'no-store',
    });
    if (!res.ok) return null;
    return await res.json();
  } catch {
    return null;
  }
}
