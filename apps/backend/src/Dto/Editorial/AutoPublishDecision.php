<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

/**
 * Result of the AutoPublishGateService evaluation.
 */
final readonly class AutoPublishDecision
{
    /**
     * @param list<string> $reasons  Reason codes explaining why the article was blocked (empty if approved)
     * @param string       $gate     Gate decision: 'auto_publish' | 'pending_review'
     */
    public function __construct(
        public bool $canPublish,
        public array $reasons,
        public string $gate,
    ) {}
}
