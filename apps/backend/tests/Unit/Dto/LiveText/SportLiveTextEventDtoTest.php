<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\LiveText;

use App\Dto\LiveText\SportLiveTextEventDto;
use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SportLiveTextEventDtoTest extends TestCase
{
    #[Test]
    public function itConstructsWithAllParameters(): void
    {
        $timestamp = new DateTime('2026-03-20 15:30:00');
        $data = ['score' => '2-1', 'minute' => 75];

        $dto = new SportLiveTextEventDto(
            type: 'score.update',
            liveTextId: 10,
            data: $data,
            timestamp: $timestamp
        );

        $this->assertSame('score.update', $dto->type);
        $this->assertSame(10, $dto->liveTextId);
        $this->assertSame($data, $dto->data);
        $this->assertSame($timestamp, $dto->timestamp);
    }

    #[Test]
    public function itConvertsToArray(): void
    {
        $timestamp = new DateTime('2026-03-20 15:30:00');
        $data = [
            'homeTeam' => 'Zimbru',
            'awayTeam' => 'Sheriff',
            'homeScore' => 1,
            'awayScore' => 0,
        ];

        $dto = new SportLiveTextEventDto(
            type: 'match.status',
            liveTextId: 5,
            data: $data,
            timestamp: $timestamp
        );

        $array = $dto->toArray();

        $this->assertSame('match.status', $array['type']);
        $this->assertSame(5, $array['liveTextId']);
        $this->assertSame($timestamp->format(DateTimeInterface::ATOM), $array['timestamp']);
        $this->assertSame($data, $array['data']);
    }

    #[Test]
    public function itHandlesEmptyData(): void
    {
        $dto = new SportLiveTextEventDto(
            type: 'match.start',
            liveTextId: 1,
            data: [],
            timestamp: new DateTime()
        );

        $array = $dto->toArray();

        $this->assertSame([], $array['data']);
    }

    #[Test]
    public function itToArrayContainsAllRequiredKeys(): void
    {
        $dto = new SportLiveTextEventDto(
            type: 'minute.update',
            liveTextId: 3,
            data: ['minute' => 45],
            timestamp: new DateTime()
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('liveTextId', $array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('data', $array);
    }
}
