<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\TranslatableEntityType;

final readonly class TranslateEntityMessage
{
    /**
     * @param string[] $locales
     */
    public function __construct(
        public TranslatableEntityType $entityType,
        public int $entityId,
        public array $locales = ['ru', 'en'],
        public bool $force = false,
    ) {
    }
}
