<?php

declare(strict_types=1);

namespace App\Service\Editorial\Monitor;

use App\Enum\EditorialAlignment;

/**
 * Editorial-pipeline monitor for "wire-like" sources (Sprint 53 T53.6, ADR-020 D4).
 *
 * Services the four wire/state/opposition alignments that together cover
 * the international signal surface:
 *
 * - WIRE_NEUTRAL      — Reuters / AP / AFP / Agerpres
 * - UKRAINIAN_STATE   — Ukrinform (+ Sprint 54 additions)
 * - INDEPENDENT_RU    — Meduza / Novaya Gazeta Europe
 * - KREMLIN_ALIGNED   — Interfax (+ Sprint 54 TASS / RIA)
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
            EditorialAlignment::INDEPENDENT_RU,
            EditorialAlignment::KREMLIN_ALIGNED,
        ];
    }
}
