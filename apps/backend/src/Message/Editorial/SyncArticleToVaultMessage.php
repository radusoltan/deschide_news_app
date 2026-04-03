<?php

declare(strict_types=1);

namespace App\Message\Editorial;

final readonly class SyncArticleToVaultMessage
{
    /**
     * @param 'sync'|'archive' $action
     */
    public function __construct(
        public int $articleId,
        public string $action = 'sync',
        public ?string $slug = null,
        public ?string $createdAt = null,
    ) {}
}
