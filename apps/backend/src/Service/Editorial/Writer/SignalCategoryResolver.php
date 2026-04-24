<?php

declare(strict_types=1);

namespace App\Service\Editorial\Writer;

use App\Entity\Category;
use App\Entity\Editorial\SourceSignal;
use App\Enum\EditorialAlignment;
use App\Repository\CategoryRepository;

/**
 * Maps a {@see SourceSignal} to one of the existing public-site
 * {@see Category} slugs (Sprint 55 T55.3).
 *
 * The resolver is deliberately heuristic: editorial alignment is the primary
 * axis; a Romanian keyword sweep catches MD-political edges that would
 * otherwise land in `externe`. No LLM call — this runs on every FlashWriter
 * invocation and must be deterministic + sub-millisecond.
 *
 * Slug set (T55.3 uses only the slugs that exist at sprint-start):
 *   - `politica` — domestic MD politics + Transnistria / Găgăuzia / CEC sweeps
 *   - `externe`  — wire, Western mainstream, EU/NATO officials, Ukrainian/Russian
 *   - `romania`  — RO mainstream outlets
 *
 * Fallback is `politica` (guaranteed present from CategoryFixtures). If even
 * that is missing the resolver returns null and the caller must decide whether
 * to skip, queue, or hard-fail.
 *
 * Orchestrator deviation: the spec mentioned `moldova` / `politica-moldovei` /
 * `razboi-ucraina` slugs. Those do not exist in the current CategoryFixtures
 * set, so we map MD signals to `politica` instead — documented in T55.3 commit.
 */
class SignalCategoryResolver
{
    /** Case-insensitive keyword triggers that force the `politica` slug. */
    private const POLITICA_KEYWORDS = [
        'transnistria',
        'găgăuzia',
        'gagauzia',
        'cec',
        'comisia electorală centrală',
        'chișinău',
        'chisinau',
    ];

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    public function resolve(SourceSignal $primarySignal): ?Category
    {
        $slug = $this->pickSlug($primarySignal);
        $category = $this->categoryRepository->findOneBy(['slug' => $slug]);
        if ($category !== null) {
            return $category;
        }

        // Primary slug missing — fall back to `politica` which CategoryFixtures
        // guarantees. If that also misses, the caller gets null and decides.
        if ($slug !== 'politica') {
            $category = $this->categoryRepository->findOneBy(['slug' => 'politica']);
        }

        return $category;
    }

    private function pickSlug(SourceSignal $signal): string
    {
        // Keyword sweep: runs first so that a wire-neutral signal reporting
        // Transnistria still ends up in `politica`, not `externe`.
        $haystack = mb_strtolower($signal->getTitle() . ' ' . ($signal->getRawSummary() ?? ''));
        foreach (self::POLITICA_KEYWORDS as $kw) {
            if (str_contains($haystack, $kw)) {
                return 'politica';
            }
        }

        return match ($signal->getVerifiedSource()->getEditorialAlignment()) {
            EditorialAlignment::MD_GOVERNMENT,
            EditorialAlignment::MD_INDEPENDENT_PRO_EU,
            EditorialAlignment::MD_INDEPENDENT_PRO_RU,
            EditorialAlignment::MD_INVESTIGATIVE => 'politica',

            EditorialAlignment::RO_MAINSTREAM => 'romania',

            EditorialAlignment::WIRE_NEUTRAL,
            EditorialAlignment::WESTERN_MAINSTREAM,
            EditorialAlignment::EU_OFFICIAL,
            EditorialAlignment::KREMLIN_ALIGNED,
            EditorialAlignment::INDEPENDENT_RU,
            EditorialAlignment::UKRAINIAN_STATE,
            EditorialAlignment::UKRAINIAN_INDEPENDENT,
            EditorialAlignment::OSINT_CURATED => 'externe',
        };
    }
}
