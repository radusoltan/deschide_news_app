<?php

declare(strict_types=1);

namespace App\Service\Editorial;

/**
 * Transient carrier for audit metadata passed from the CLI entry point to
 * {@see \App\EventListener\AppSettingAuditListener} (T57.P3, ADR-024 D5).
 *
 * Symfony has no true request-scoped DI in CLI context, so this is a regular
 * (shared) service with explicit set/get/clear. Two liveness rules the
 * contract depends on:
 *
 *   1. Every caller that invokes {@see \App\Repository\AppSettingRepository::set()}
 *      on a critical key MUST populate `setReason()` beforehand and wrap the
 *      call in a `try { … } finally { $context->clear(); }`. Leaking a reason
 *      into an unrelated subsequent flush would attribute the wrong metadata
 *      to the wrong audit row.
 *
 *   2. The listener treats a missing `reason` on a critical update as a
 *      CONTRACT VIOLATION BY THE CALLER rather than as a reason to reject the
 *      flush (ADR-024 D5 refinement c). It logs a warning and still persists
 *      the audit row with `reason = null` — the forensic trail stays intact
 *      while the non-CLI caller gets visibility into its bug.
 *
 * Future callers adding new write paths to AppSetting must read this contract
 * before wiring. A lint-level reminder lives in the `AppSettingAuditListener`
 * docblock as well.
 */
class AppSettingChangeAuditContext
{
    private ?string $reason = null;

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function clear(): void
    {
        $this->reason = null;
    }
}
