<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message dispatched when a short link is clicked.
 *
 * Contains all information needed to record the click interaction.
 * Processed asynchronously to ensure fast redirects.
 */
class ShortLinkClickMessage
{
    public function __construct(
        public readonly int $shortLinkId,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $referrer
    ) {
    }
}
