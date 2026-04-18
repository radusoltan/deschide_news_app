<?php

declare(strict_types=1);

namespace App\Service\Editorial\Monitor;

use App\Enum\EditorialAlignment;

/**
 * Editorial-pipeline monitor for global-wire sources (Sprint 53 T53.6,
 * narrowed in Sprint 54 T54.11 per ADR-020 D1).
 *
 * Services the two alignments that genuinely belong to global-agency wire
 * coverage:
 *
 * - WIRE_NEUTRAL    — Reuters / AP / AFP / Agerpres
 * - UKRAINIAN_STATE — Ukrinform
 *
 * Russian-language coverage (INDEPENDENT_RU + KREMLIN_ALIGNED) moved to
 * {@see MediaRuSourceMonitor} in Sprint 54 T54.5 — originally both monitors
 * overlapped on those alignments, which doubled HTTP fetches without
 * semantic benefit. Single-owner alignment mapping keeps per-monitor
 * observability clean and avoids rate-limit pressure on the shared upstream
 * (TASS in particular).
 *
 * All fetch + parse + dedup + dispatch logic is inherited verbatim from
 * {@see AbstractRssMonitor}; this class is deliberately thin so alignment
 * routing lives in exactly one declaration.
 */
final class WireSourceMonitor extends AbstractRssMonitor
{
    /**
     * @return list<EditorialAlignment>
     */
    protected function getAlignmentFilters(): array
    {
        return [
            EditorialAlignment::WIRE_NEUTRAL,
            EditorialAlignment::UKRAINIAN_STATE,
        ];
    }
}
