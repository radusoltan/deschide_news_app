<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\TranslationPriority;

/**
 * Dispatched by Symfony Scheduler to trigger batch translation of pending articles.
 */
final readonly class TranslatePendingBatch
{
    public function __construct(
        public TranslationPriority $priority,
        public int $limit = 10,
    ) {
    }
}
