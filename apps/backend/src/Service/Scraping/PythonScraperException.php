<?php

declare(strict_types=1);

namespace App\Service\Scraping;

class PythonScraperException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly bool $isTimeout = false,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function isTimeout(): bool
    {
        return $this->isTimeout;
    }
}
