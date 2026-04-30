/**
 * Topics API Service
 *
 * Promoted from `app/[locale]/(public)/topics/[slug]/page.tsx` so the
 * server-side locale resolver and the topic page can share a single
 * fetch call (React 19 fetch memoization dedupes identical URL+options
 * within one request).
 */

import type { Locale } from '../types';
import { CACHE_TAGS } from '../data/cache-config';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

export interface TopicData {
  id: number;
  title: string;
  slug: string;
  description?: string | null;
  lvl: number;
  isActive: boolean;
  translatedSlugs?: { ro?: string; en?: string; ru?: string };
}

/**
 * Fetch a single topic by slug for a given locale.
 * Returns null on miss or any fetch error (fail-soft).
 */
export async function fetchTopicBySlug(
  slug: string,
  locale: Locale | string
): Promise<TopicData | null> {
  try {
    const url = new URL(`${API_BASE_URL}/api/topics`);
    url.searchParams.set('slug', slug);
    url.searchParams.set('itemsPerPage', '1');

    const response = await fetch(url.toString(), {
      headers: { 'Accept-Language': locale },
      next: {
        revalidate: 600,
        tags: [CACHE_TAGS.locale(locale)],
      },
    });

    if (!response.ok) {
      return null;
    }

    const data = await response.json();
    const members = data['hydra:member'] || data.member || [];
    return (members[0] as TopicData) ?? null;
  } catch {
    return null;
  }
}
