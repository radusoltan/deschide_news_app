<?php

declare(strict_types=1);

namespace App\Service\Editorial;

final readonly class ValidationResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public bool $isValid,
        public array $errors = [],
    ) {}
}
