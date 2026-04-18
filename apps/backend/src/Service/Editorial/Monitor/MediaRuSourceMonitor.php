<?php

declare(strict_types=1);

namespace App\Service\Editorial\Monitor;

use App\Enum\EditorialAlignment;

/**
 * Editorial-pipeline monitor for Russian-language media (Sprint 54 T54.5,
 * ADR-020 D4).
 *
 * Services the two alignments covering the Russian-language signal surface:
 *
 * - INDEPENDENT_RU   — Meduza / Novaya Gazeta Europe
 * - KREMLIN_ALIGNED  — Interfax / TASS / RIA Novosti / Kremlin.ru
 *
 * Note on alignment overlap with {@see WireSourceMonitor}: both alignments
 * also appear in the wire monitor's filter (inherited Sprint 53 scoping).
 * Sprint 54 introduces this monitor to give Russian-language coverage its
 * own fetch cadence (300s vs wire's 180s) and to surface operational
 * behaviour per-language-cluster. Dedup via `source_signals.raw_content_hash`
 * guarantees idempotent persistence across the two monitors.
 *
 * All fetch + parse + dedup + dispatch logic is inherited verbatim from
 * {@see AbstractRssMonitor}; Windows-1251 decoding (legacy RU outlets) also
 * lives in the base class.
 */
final class MediaRuSourceMonitor extends AbstractRssMonitor
{
    /**
     * @return list<EditorialAlignment>
     */
    protected function getAlignmentFilters(): array
    {
        return [
            EditorialAlignment::INDEPENDENT_RU,
            EditorialAlignment::KREMLIN_ALIGNED,
        ];
    }
}
