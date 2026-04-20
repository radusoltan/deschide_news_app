'use client';

import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * One event emitted by the backend on `deschide_news/admin_escalations`.
 * Event `type` matches the `event` field in the Mercure payload.
 *
 * T56.07 (ADR-022 D3): the `decided` variant previously carried
 * `decided_by` (editor user id) and `comment` (editorial reasoning excerpt
 * up to 256 chars). Those fields are stripped at emit because the topic
 * is unauthenticated. Consumers that need editor identity or the
 * editorial note must fetch via the authenticated REST endpoint
 * `GET /admin/escalations/{id}` — the hook's own consumers today
 * (EscalationQueue.tsx) use events solely as refetch triggers.
 */
export type EscalationMercureEvent =
  | { event: 'new'; id: number; category: string; category_name: string; created_at: string; expires_at: string | null }
  | { event: 'expired'; id: number; category: string; category_name: string; expires_at: string | null; decided_at: string | null }
  | { event: 'decided'; decision: 'approved' | 'rejected'; id: number; category: string; category_name: string; decided_at: string | null }
  | { event: 'extended'; id: number; category: string; category_name: string; expires_at: string | null; added_seconds: number };

export type MercureStatus = 'connecting' | 'connected' | 'disconnected' | 'error';

export interface UseMercureEscalationsOptions {
  /**
   * Handler fired exactly once per incoming event. Callers typically
   * refetch the list + stats inside this callback.
   */
  onEvent: (event: EscalationMercureEvent) => void;

  /**
   * Enable auto-reconnect with exponential-backoff-ish capped delay.
   * @default true
   */
  autoReconnect?: boolean;

  /** @default 8 */
  maxReconnectAttempts?: number;

  /**
   * Base delay between reconnection attempts in ms. Real delay scales
   * mildly with attempt count (capped at 15s) to avoid hammering the hub
   * during sustained outages.
   * @default 3000
   */
  reconnectDelay?: number;

  /** @default false */
  debug?: boolean;
}

export interface UseMercureEscalationsResult {
  status: MercureStatus;
  error: string | null;
  reconnectAttempts: number;
  reconnect: () => void;
}

const ESCALATIONS_TOPIC = 'deschide_news/admin_escalations';

/**
 * Subscribes to the admin escalations Mercure topic and invokes `onEvent` for
 * each new payload. Handles connect / disconnect / error lifecycle + bounded
 * auto-reconnect.
 *
 * Per T55.13 contract: this hook is the ONLY place the frontend touches
 * EventSource — components MUST NOT create their own connections. Callers
 * combine the hook with their existing fetchEscalations() calls so the
 * queue refreshes event-driven instead of polling.
 *
 * Backend fails Mercure publishes open (warn + continue on hub unreachable),
 * and this hook mirrors that contract: connection failures surface via
 * `status='error'` + `error` message but never throw upstream. Callers are
 * expected to render a "reconectare..." banner and fall back to manual
 * refresh buttons.
 */
export function useMercureEscalations(
  options: UseMercureEscalationsOptions,
): UseMercureEscalationsResult {
  const {
    onEvent,
    autoReconnect = true,
    maxReconnectAttempts = 8,
    reconnectDelay = 3000,
    debug = false,
  } = options;

  const [status, setStatus] = useState<MercureStatus>('disconnected');
  const [error, setError] = useState<string | null>(null);
  const [reconnectAttempts, setReconnectAttempts] = useState(0);

  const eventSourceRef = useRef<EventSource | null>(null);
  const reconnectTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const isMountedRef = useRef(true);
  const connectRef = useRef<() => void>(() => {});

  // Keep a stable reference to the consumer callback so changing it does not
  // force a reconnect. Callers typically pass fresh closures on every render
  // (they capture latest filters/state); without the ref, connect() would
  // restart on every render.
  const onEventRef = useRef(onEvent);
  useEffect(() => {
    onEventRef.current = onEvent;
  }, [onEvent]);

  const log = useCallback(
    (...args: unknown[]) => {
      if (debug) {
        console.log('[useMercureEscalations]', ...args);
      }
    },
    [debug],
  );

  const cleanup = useCallback(() => {
    if (eventSourceRef.current) {
      eventSourceRef.current.close();
      eventSourceRef.current = null;
    }
    if (reconnectTimeoutRef.current) {
      clearTimeout(reconnectTimeoutRef.current);
      reconnectTimeoutRef.current = null;
    }
  }, []);

  const connect = useCallback(() => {
    if (!isMountedRef.current) return;

    cleanup();

    const mercureUrl = process.env.NEXT_PUBLIC_MERCURE_URL;
    if (!mercureUrl) {
      setStatus('error');
      setError('NEXT_PUBLIC_MERCURE_URL nu este configurat.');
      log('Mercure URL missing from env');
      return;
    }

    const url = `${mercureUrl}?topic=${encodeURIComponent(ESCALATIONS_TOPIC)}`;
    log('connect', url);
    setStatus('connecting');
    setError(null);

    try {
      const es = new EventSource(url);
      eventSourceRef.current = es;

      es.onopen = () => {
        if (!isMountedRef.current) return;
        setStatus('connected');
        setError(null);
        setReconnectAttempts(0);
        log('connected');
      };

      es.onmessage = (raw) => {
        if (!isMountedRef.current) return;
        try {
          const parsed = JSON.parse(raw.data) as EscalationMercureEvent;
          log('event', parsed.event, parsed);
          onEventRef.current(parsed);
        } catch (err) {
          log('parse error', err, raw.data);
        }
      };

      es.onerror = () => {
        if (!isMountedRef.current) return;

        log('connection dropped');
        setStatus('error');
        es.close();
        eventSourceRef.current = null;

        if (!autoReconnect) {
          setError('Conexiunea Mercure a fost întreruptă.');
          return;
        }

        setReconnectAttempts((prev) => {
          const next = prev + 1;

          if (next > maxReconnectAttempts) {
            setError(`Reconectare eșuată după ${maxReconnectAttempts} încercări. Folosește butonul „Reîncarcă".`);
            log('max reconnects reached');
            return next;
          }

          // Mild scaling so the hub isn't hammered under sustained outages.
          const delay = Math.min(reconnectDelay * Math.ceil(next / 2), 15_000);
          setError(`Reconectare (${next}/${maxReconnectAttempts}) în ${Math.round(delay / 1000)}s…`);
          log(`scheduling reconnect in ${delay}ms (attempt ${next})`);

          reconnectTimeoutRef.current = setTimeout(() => {
            if (isMountedRef.current) {
              connectRef.current();
            }
          }, delay);

          return next;
        });
      };
    } catch (err) {
      log('EventSource ctor error', err);
      setStatus('error');
      setError(err instanceof Error ? err.message : 'Eroare necunoscută Mercure');
    }
  }, [autoReconnect, maxReconnectAttempts, reconnectDelay, cleanup, log]);

  useEffect(() => {
    connectRef.current = connect;
  }, [connect]);

  const reconnect = useCallback(() => {
    log('manual reconnect');
    setReconnectAttempts(0);
    setError(null);
    connect();
  }, [connect, log]);

  useEffect(() => {
    isMountedRef.current = true;

    // Defer the initial connect by a microtask so the setState calls inside
    // `connect()` run after the effect body returns — avoids the
    // `react-hooks/set-state-in-effect` cascade-render warning while
    // keeping the connection tied to the component lifecycle.
    const timeoutId = setTimeout(() => {
      if (isMountedRef.current) {
        connect();
      }
    }, 0);

    return () => {
      isMountedRef.current = false;
      clearTimeout(timeoutId);
      cleanup();
    };
  }, [connect, cleanup]);

  return { status, error, reconnectAttempts, reconnect };
}
