'use server';

import { getAccessToken } from '@/lib/dal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

export interface FactCheckResult {
  answer: string;
  question: string;
  topicId: number;
  notebookId: string | null;
  cached: boolean;
  checkedAt: string;
}

export interface FactCheckResponse {
  data?: FactCheckResult;
  error?: string;
}

export async function runFactCheck(
  articleId: number,
  question?: string,
): Promise<FactCheckResponse> {
  const token = await getAccessToken();
  if (!token) {
    return { error: 'Nu ești autentificat' };
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

    if (!res.ok) {
      return { error: json.error ?? `API error: ${res.status}` };
    }

    return { data: json.data };
  } catch (err: unknown) {
    if (err instanceof DOMException && err.name === 'AbortError') {
      return { error: 'Timeout: NotebookLM nu a răspuns în 60 de secunde.' };
    }
    return { error: 'Eroare de rețea. Încearcă din nou.' };
  } finally {
    clearTimeout(timeout);
  }
}
