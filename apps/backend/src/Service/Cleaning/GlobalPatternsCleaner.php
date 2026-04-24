<?php

declare(strict_types=1);

namespace App\Service\Cleaning;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Removes noise patterns common across all sources.
 * Runs for every source (supports() always returns true).
 */
#[AsTaggedItem(priority: -100)]
class GlobalPatternsCleaner implements SourceContentCleanerInterface
{
    public function supports(string $sourceName): bool
    {
        return true;
    }

    public function clean(string $content): string
    {
        // Remove AI translation preambles
        $content = preg_replace('/^I will (translate|read) the .*$/mu', '', $content) ?? $content;

        // Remove "Citește și:" / "Читайте также:" / "Read also:" lines
        $content = preg_replace('/^(Citește și|Читайте также|Read also)\s*:.*$/mu', '', $content) ?? $content;

        // Remove "Spelling error report" blocks (Newsmaker mistape.com widget in English)
        $content = preg_replace('/Spelling error report.*$/su', '', $content) ?? $content;

        // Clean up whitespace
        $content = preg_replace('/\n{3,}/', "\n\n", trim($content));

        return $content;
    }
}
