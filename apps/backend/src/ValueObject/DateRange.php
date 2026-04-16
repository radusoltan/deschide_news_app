<?php

declare(strict_types=1);

namespace App\ValueObject;

final readonly class DateRange
{
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
    ) {
    }

    public static function lastDays(int $days): self
    {
        return new self(
            new \DateTimeImmutable("-{$days} days"),
            new \DateTimeImmutable(),
        );
    }

    public function format(): string
    {
        return $this->from->format('Y-m-d') . ' to ' . $this->to->format('Y-m-d');
    }
}
