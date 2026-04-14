<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cleaning;

use App\Service\Cleaning\SourceContentCleanerInterface;
use App\Service\Cleaning\SourceContentCleanerRegistry;
use PHPUnit\Framework\TestCase;

class SourceContentCleanerRegistryTest extends TestCase
{
    public function testFindsCorrectCleanerBySourceName(): void
    {
        $cleaner = $this->createMock(SourceContentCleanerInterface::class);
        $cleaner->method('supports')->willReturnCallback(fn (string $s) => str_contains($s, 'newsmaker'));
        $cleaner->method('clean')->willReturnCallback(fn (string $c) => 'cleaned:' . $c);

        $registry = new SourceContentCleanerRegistry([$cleaner]);
        $result = $registry->clean('scrape:newsmaker_md', 'noisy content');

        $this->assertSame('cleaned:noisy content', $result);
    }

    public function testReturnsContentUnchangedIfNoCleanerMatches(): void
    {
        $cleaner = $this->createMock(SourceContentCleanerInterface::class);
        $cleaner->method('supports')->willReturn(false);
        $cleaner->expects($this->never())->method('clean');

        $registry = new SourceContentCleanerRegistry([$cleaner]);
        $result = $registry->clean('scrape:unknown_source', 'original content');

        $this->assertSame('original content', $result);
    }

    public function testChainsMultipleCleanersForSameSource(): void
    {
        $cleaner1 = $this->createMock(SourceContentCleanerInterface::class);
        $cleaner1->method('supports')->willReturn(true);
        $cleaner1->method('clean')->willReturnCallback(fn (string $c) => str_replace('A', '', $c));

        $cleaner2 = $this->createMock(SourceContentCleanerInterface::class);
        $cleaner2->method('supports')->willReturn(true);
        $cleaner2->method('clean')->willReturnCallback(fn (string $c) => str_replace('B', '', $c));

        $registry = new SourceContentCleanerRegistry([$cleaner1, $cleaner2]);
        $result = $registry->clean('any', 'AB content');

        $this->assertSame(' content', $result);
    }

    public function testHasCleanerForReturnsTrueWhenMatches(): void
    {
        $cleaner = $this->createMock(SourceContentCleanerInterface::class);
        $cleaner->method('supports')->willReturnCallback(fn (string $s) => str_contains($s, 'newsmaker'));

        $registry = new SourceContentCleanerRegistry([$cleaner]);

        $this->assertTrue($registry->hasCleanerFor('scrape:newsmaker_md'));
        $this->assertFalse($registry->hasCleanerFor('scrape:unknown'));
    }

    public function testEmptyRegistryPassesThroughContent(): void
    {
        $registry = new SourceContentCleanerRegistry([]);
        $result = $registry->clean('any:source', 'raw content');

        $this->assertSame('raw content', $result);
    }
}
