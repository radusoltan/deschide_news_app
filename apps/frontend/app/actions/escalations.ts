'use server';

import { getAccessToken } from '@/lib/dal';
import type {
  EscalationApprovePayload,
  EscalationCategoryValue,
  EscalationEnvelope,
  EscalationExtendPayload,
  EscalationListPayload,
  EscalationRejectPayload,
  EscalationStatsPayload,
  EscalationStatus,
} from '@/lib/api/escalations';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

/**
 * Result shape shared across server actions. Intentionally flat so callers can
 * destructure `{ data, error, violations }` directly.
 */
export interface ActionResult<T> {
  data: T | null;
  error: string | null;
  violations?: Array<{ field: string; message: string }>;
  status?: string;
}

/**
 * Low-level helper that calls the admin REST endpoint and unwraps the standard
 * envelope. Any non-2xx response is surfaced as `error`; validation responses
 * additionally carry `violations` for inline field-level rendering.
 */
async function callApi<T>(
  path: string,
  init: RequestInit = {},
): Promise<ActionResult<T>> {
  const token = await getAccessToken();
  if (!token) {
    return { data: null, error: 'Nu ești autentificat.', status: 'unauthenticated' };
  }

  try {
    const res = await fetch(`${API_BASE_URL}${path}`, {
      ...init,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
        ...(init.headers ?? {}),
      },
      cache: 'no-store',
    });

    let body: EscalationEnvelope<T> | null = null;
    try {
      body = (await res.json()) as EscalationEnvelope<T>;
    } catch {
      // Non-JSON response (HTML error page, gateway timeout, etc.)
      return { data: null, error: `Eroare API: ${res.status}`, status: 'bad_response' };
    }

    if (!res.ok || !body.success) {
      return {
        data: null,
        error: body.error ?? `Eroare API: ${res.status}`,
        violations: body.violations,
        status: body.status,
      };
    }

    return { data: (body.data ?? null) as T | null, error: null, status: body.status };
  } catch (err) {
    return { data: null, error: err instanceof Error ? err.message : String(err), status: 'network_error' };
  }
}

// ========== READS ==========

export async function fetchEscalations(params: {
  status?: EscalationStatus;
  category?: EscalationCategoryValue;
  page?: number;
  limit?: number;
} = {}): Promise<ActionResult<EscalationListPayload>> {
  const query = new URLSearchParams();
  if (params.status) query.set('status', params.status);
  if (params.category) query.set('category', params.category);
  if (params.page && params.page > 1) query.set('page', String(params.page));
  if (params.limit) query.set('limit', String(params.limit));

  const qs = query.toString();

  return callApi<EscalationListPayload>(`/api/admin/escalations${qs ? `?${qs}` : ''}`);
}

export async function fetchStats(): Promise<ActionResult<EscalationStatsPayload>> {
  return callApi<EscalationStatsPayload>('/api/admin/escalations/stats');
}

// ========== WRITES ==========

export async function approveEscalation(
  id: number,
  publishAsArticle: boolean = false,
  editorialNotes?: string,
): Promise<ActionResult<EscalationApprovePayload>> {
  return callApi<EscalationApprovePayload>(
    `/api/admin/escalations/${id}/approve`,
    {
      method: 'POST',
      body: JSON.stringify({
        publishAsArticle,
        ...(editorialNotes ? { editorialNotes } : {}),
      }),
    },
  );
}

export async function rejectEscalation(
  id: number,
  reason: string,
): Promise<ActionResult<EscalationRejectPayload>> {
  return callApi<EscalationRejectPayload>(
    `/api/admin/escalations/${id}/reject`,
    {
      method: 'POST',
      body: JSON.stringify({ reason }),
    },
  );
}

export async function extendSla(
  id: number,
  additionalSeconds: number,
): Promise<ActionResult<EscalationExtendPayload>> {
  return callApi<EscalationExtendPayload>(
    `/api/admin/escalations/${id}/extend-sla`,
    {
      method: 'POST',
      body: JSON.stringify({ additionalSeconds }),
    },
  );
}
