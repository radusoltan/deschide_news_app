'use server';

import { getAccessToken } from '@/lib/dal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

export type FactCheckStatus =
  | 'fresh'
  | 'cached'
  | 'no_topics'
  | 'no_notebook'
  | 'disabled'
  | 'unavailable'
  | 'failed'
  | 'validation'
  | 'rate_limited'
  | 'not_found'
  | 'network'
  | 'timeout';

export interface FactCheckResult {
  answer: string;
  question: string;
  topicId: number;
  notebookId: string | null;
  cached: boolean;
  checkedAt: string;
}

export interface FactCheckViolation {
  field: string;
  message: string;
}

export interface FactCheckResponse {
  status: FactCheckStatus;
  data?: FactCheckResult;
  error?: string;
  violations?: FactCheckViolation[];
}

export async function runFactCheck(
  articleId: number,
  question?: string,
): Promise<FactCheckResponse> {
  const token = await getAccessToken();
  if (!token) {
    return { status: 'network', error: 'Nu ești autentificat' };
  }

  const body: Record<string, string> = {};
  if (question) {
    body.question = question;
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 60000);

  try {
    const res = await fetch(
      `${API_BASE_URL}/api/admin/articles/${articleId}/factcheck`,
      {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(body),
        signal: controller.signal,
        cache: 'no-store',
      },
    );

    const json = await res.json();
    const status = (json.status as FactCheckStatus) ?? (res.ok ? 'fresh' : 'failed');

    if (!res.ok) {
      return {
        status,
        error: json.error ?? `API error: ${res.status}`,
        violations: json.violations,
      };
    }

    return { status, data: json.data };
  } catch (err: unknown) {
    if (err instanceof DOMException && err.name === 'AbortError') {
      return {
        status: 'timeout',
        error: 'Timeout: NotebookLM nu a răspuns în 60 de secunde.',
      };
    }
    return { status: 'network', error: 'Eroare de rețea. Încearcă din nou.' };
  } finally {
    clearTimeout(timeout);
  }
}
