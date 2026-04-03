<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

final readonly class VaultSyncResult
{
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_ARCHIVED = 'archived';
    public const ACTION_SKIPPED = 'skipped';
    public const ACTION_FAILED = 'failed';

    public function __construct(
        public int $articleId,
        public string $action,
        public ?string $vaultPath = null,
        public ?string $error = null,
    ) {}

    public function isSuccess(): bool
    {
        return \in_array($this->action, [self::ACTION_CREATED, self::ACTION_UPDATED, self::ACTION_ARCHIVED], true);
    }
}
