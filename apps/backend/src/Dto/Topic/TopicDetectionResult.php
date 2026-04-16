<?php

declare(strict_types=1);

namespace App\Dto\Topic;

use App\Enum\TopicDetectionMethod;

/**
 * Result of topic detection for a single topic match.
 */
final readonly class TopicDetectionResult
{
    public function __construct(
        public int $topicId,
        public float $confidence,
        public TopicDetectionMethod $detectedBy,
    ) {
    }
}
