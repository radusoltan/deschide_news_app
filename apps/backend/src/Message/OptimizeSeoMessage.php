<?php

declare(strict_types=1);

namespace App\Message;

final readonly class OptimizeSeoMessage
{
    public function __construct(
        public int $articleId,
        public bool $generateMeta = true,
        public bool $suggestTags = true,
        public bool $force = false,
    ) {
    }
}
