<?php

declare(strict_types=1);

namespace App\Service\Cleaning;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class SourceContentCleanerRegistry
{
    /** @var iterable<SourceContentCleanerInterface> */
    private readonly iterable $cleaners;

    /**
     * @param iterable<SourceContentCleanerInterface> $cleaners
     */
    public function __construct(
        #[AutowireIterator('app.source_content_cleaner')]
        iterable $cleaners,
    ) {
        $this->cleaners = $cleaners;
    }

    /**
     * Apply all matching cleaners to the content.
     */
    public function clean(string $sourceName, string $content): string
    {
        foreach ($this->cleaners as $cleaner) {
            if ($cleaner->supports($sourceName)) {
                $content = $cleaner->clean($content);
            }
        }

        return $content;
    }

    /**
     * Check if any cleaner supports this source.
     */
    public function hasCleanerFor(string $sourceName): bool
    {
        foreach ($this->cleaners as $cleaner) {
            if ($cleaner->supports($sourceName)) {
                return true;
            }
        }

        return false;
    }
}
