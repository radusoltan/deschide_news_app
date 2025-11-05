<?php

declare(strict_types=1);

namespace App\Message;

use DateTimeImmutable;

class SessionEndEvent
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $visitorId,
        public readonly DateTimeImmutable $startedAt,
        public readonly DateTimeImmutable $endedAt,
        public readonly int $pageCount,
        public readonly int $duration,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $referrer = null
    ) {
    }
}
