<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\SourceType;

final readonly class DuplicateCheckResult
{
    public function __construct(
        public bool $isDuplicate,
        public ?string $existingEntityType = null,
        public ?int $existingEntityId = null,
        public ?SourceType $existingSourceType = null,
    ) {}
}
