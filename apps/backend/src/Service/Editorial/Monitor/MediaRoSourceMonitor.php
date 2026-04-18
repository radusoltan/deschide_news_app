<?php

declare(strict_types=1);

namespace App\Service\Editorial\Monitor;

use App\Enum\EditorialAlignment;

/**
 * Editorial-pipeline monitor for MD/RO domestic outlets (Sprint 53 T53.7,
 * ADR-020 D4).
 *
 * Services the four alignments that cover the Moldovan + Romanian editorial
 * surface:
 *
 * - MD_INVESTIGATIVE     — Ziarul de Gardă (+ Sprint 54 RISE Moldova)
 * - MD_INDEPENDENT_PRO_EU — NewsMaker / TV8 (+ Sprint 54 Agora / RFE-RL MD)
 * - MD_GOVERNMENT        — Moldpres
 * - RO_MAINSTREAM        — Digi24 / HotNews / G4Media
 *
 * All fetch + parse + dedup + dispatch logic is inherited verbatim from
 * {@see AbstractRssMonitor}; this class is deliberately thin so alignment
 * routing lives in exactly one declaration.
 */
final class MediaRoSourceMonitor extends AbstractRssMonitor
{
    /**
     * @return list<EditorialAlignment>
     */
    protected function getAlignmentFilters(): array
    {
        return [
            EditorialAlignment::MD_INVESTIGATIVE,
            EditorialAlignment::MD_INDEPENDENT_PRO_EU,
            EditorialAlignment::MD_GOVERNMENT,
            EditorialAlignment::RO_MAINSTREAM,
        ];
    }
}
