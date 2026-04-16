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

    /**
     * Last hour with 70-minute lookback to cover topic_detection worker async lag.
     * PRs ingested at minute 58 may not have topic assignment until minute 5 of next hour.
     */
    public static function lastHour(): self
    {
        return new self(
            new \DateTimeImmutable('-70 minutes'),
            new \DateTimeImmutable(),
        );
    }

    public static function lastDay(): self
    {
        return new self(
            new \DateTimeImmutable('-24 hours'),
            new \DateTimeImmutable(),
        );
    }

    public static function lastWeek(): self
    {
        return new self(
            new \DateTimeImmutable('-7 days'),
            new \DateTimeImmutable(),
        );
    }

    /**
     * Factory to get the DateRange for a given BriefingCadence.
     */
    public static function forCadence(\App\Enum\BriefingCadence $cadence): self
    {
        return match ($cadence) {
            \App\Enum\BriefingCadence::HOURLY => self::lastHour(),
            \App\Enum\BriefingCadence::DAILY => self::lastDay(),
            \App\Enum\BriefingCadence::WEEKLY => self::lastWeek(),
        };
    }

    public function format(): string
    {
        return $this->from->format('Y-m-d') . ' to ' . $this->to->format('Y-m-d');
    }
}
