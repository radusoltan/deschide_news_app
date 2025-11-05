<?php

declare(strict_types=1);

namespace App\Message;

use DateTimeImmutable;

class PageViewEvent
{
    public function __construct(
        public readonly int $articleId,
        public readonly string $visitorId,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $referrer,
        public readonly DateTimeImmutable $timestamp,
        public readonly ?int $categoryId = null,
        public readonly ?string $sessionId = null
    ) {
    }
}
