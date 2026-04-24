<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ArchiveStalePressReleasesMessage
{
    public function __construct(
        public int $days = 30,
    ) {}
}
