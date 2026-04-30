<?php

declare(strict_types=1);

namespace App\Service\Translation;

use App\Entity\Article;

/**
 * Contract for verifying whether an article has the full set of translated
 * fields required to be exposed in a given locale (title, lead, content).
 *
 * Extracted to allow test doubles around the (final readonly) concrete
 * checker without resorting to bypass-finals tooling.
 */
interface ArticleTranslationCompletenessCheckerInterface
{
    public function isComplete(Article $article, string $locale): bool;

    /**
     * @return string[] Locales (including 'ro') for which all required fields are present.
     */
    public function getCompleteLocales(Article $article): array;

    /**
     * @return string[] Required field names that are missing for the given locale.
     */
    public function getMissingFields(Article $article, string $locale): array;
}
