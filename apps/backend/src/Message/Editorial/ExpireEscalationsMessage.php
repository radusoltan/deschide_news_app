<?php

declare(strict_types=1);

namespace App\Message\Editorial;

/**
 * Scheduler-emitted tick (Sprint 55 T55.11, ADR-020 D9) that asks
 * {@see \App\MessageHandler\Editorial\ExpireEscalationsMessageHandler}
 * to mark {@see \App\Entity\Editorial\EditorialEscalationLog} rows whose
 * `expires_at` has passed as `decision=EXPIRED`.
 *
 * EXPIRED is a distinct terminal state from APPROVED / REJECTED — the row
 * is no longer pending (so the SLA queue drops it) but the editorial
 * outcome remains unresolved. Admin UI (T55.13) surfaces expired rows so a
 * senior editor can re-open them.
 *
 * No payload — the handler uses `now = new DateTimeImmutable()` directly.
 */
final readonly class ExpireEscalationsMessage
{
}
