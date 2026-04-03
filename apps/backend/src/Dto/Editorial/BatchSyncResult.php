<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

final readonly class BatchSyncResult
{
    /**
     * @param list<VaultSyncResult> $results
     */
    public function __construct(
        public array $results,
        public int $created,
        public int $updated,
        public int $skipped,
        public int $failed,
    ) {}
}
