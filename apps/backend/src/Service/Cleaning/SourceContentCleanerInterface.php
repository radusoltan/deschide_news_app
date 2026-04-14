<?php

declare(strict_types=1);

namespace App\Service\Cleaning;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.source_content_cleaner')]
interface SourceContentCleanerInterface
{
    /**
     * Whether this cleaner handles content from the given source.
     */
    public function supports(string $sourceName): bool;

    /**
     * Remove noise patterns from content. Returns cleaned content.
     */
    public function clean(string $content): string;
}
