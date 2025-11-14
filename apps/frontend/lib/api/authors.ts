/**
 * Server-side API client functions for Author operations
 */

import 'server-only';
import { getAccessToken } from '@/lib/dal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

export interface Author {
  id: number;
  firstName: string;
  lastName: string;
  email: string;
  slug: string;
  bio?: string;
  status: string;
  fullName: string;
  initials: string;
}

export interface AuthorListResponse {
  '@context': string;
  '@id': string;
  '@type': string;
  totalItems: number;
  member: Author[];
}

/**
 * Fetch all authors
 */
export async function getAuthors(): Promise<Author[]> {
  const token = await getAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  const response = await fetch(`${API_BASE_URL}/api/authors`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/ld+json',
    },
    cache: 'no-store',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch authors: ${response.statusText}`);
  }

  const data: AuthorListResponse = await response.json();
  return data.member || [];
}
