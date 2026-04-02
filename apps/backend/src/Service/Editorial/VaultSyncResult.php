<?php

declare(strict_types=1);

namespace App\Service\Editorial;

final readonly class VaultSyncResult
{
    public const STATUS_CREATED = 'created';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_VALIDATION_ERROR = 'validation_error';
    public const STATUS_PARSE_ERROR = 'parse_error';

    /**
     * @param list<string> $errors
     */
    public function __construct(
        public string $status,
        public ?int $articleId = null,
        public array $errors = [],
    ) {}
}
