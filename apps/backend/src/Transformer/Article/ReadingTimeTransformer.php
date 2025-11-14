<?php

declare(strict_types=1);

namespace App\Transformer\Article;

use App\Entity\Article;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

/**
 * Transformer to calculate reading time from article content.
 */
final class ReadingTimeTransformer implements TransformCallableInterface
{
    public function __invoke(mixed $value, object $source, ?object $target): ?int
    {
        if (!$source instanceof Article || !$source->getContent()) {
            return null;
        }

        // Calculate: ~200 words per minute
        $wordCount = str_word_count(strip_tags($source->getContent()));

        return (int) ceil($wordCount / 200);
    }
}
