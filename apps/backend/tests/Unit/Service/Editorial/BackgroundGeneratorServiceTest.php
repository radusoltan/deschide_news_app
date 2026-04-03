<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Dto\Editorial\BackgroundResult;
use App\Dto\Editorial\ContextData;
use PHPUnit\Framework\TestCase;

class BackgroundGeneratorServiceTest extends TestCase
{
    public function testContextDataIsEmptyByDefault(): void
    {
        $context = new ContextData();

        self::assertTrue($context->isEmpty());
        self::assertSame(0, $context->sourcesCount());
    }

    public function testContextDataWithPreviousArticles(): void
    {
        $context = new ContextData(
            previousArticles: [
                ['id' => 1, 'score' => 0.9, 'source' => ['title_ro' => 'Test']],
            ],
        );

        self::assertFalse($context->isEmpty());
        self::assertSame(1, $context->sourcesCount());
    }

    public function testContextDataWithMocContent(): void
    {
        $context = new ContextData(
            mocContent: '# MOC Test Content',
            mocName: 'MOC-Test.md',
        );

        self::assertFalse($context->isEmpty());
        self::assertSame(1, $context->sourcesCount());
    }

    public function testContextDataWithAtomicNotes(): void
    {
        $context = new ContextData(
            atomicNotes: [
                ['entity' => 'Ion Popescu', 'content' => 'Ministrul Economiei'],
            ],
        );

        self::assertFalse($context->isEmpty());
        self::assertSame(1, $context->sourcesCount());
    }

    public function testContextDataSourcesCountCombined(): void
    {
        $context = new ContextData(
            previousArticles: [
                ['id' => 1, 'score' => 0.9, 'source' => []],
                ['id' => 2, 'score' => 0.8, 'source' => []],
            ],
            mocContent: '# MOC',
            mocName: 'MOC-Test.md',
            atomicNotes: [
                ['entity' => 'Entity1', 'content' => 'Content1'],
            ],
        );

        // 2 articles + 1 MOC + 1 atomic note = 4
        self::assertSame(4, $context->sourcesCount());
    }

    public function testBackgroundResultCreation(): void
    {
        $result = new BackgroundResult(
            backgroundText: 'Amintim că subiectul acesta a fost discutat anterior.',
            referencedArticles: [100, 200],
            mocUsed: 'MOC-Politica-Interna.md',
            sourcesCount: 3,
        );

        self::assertSame('Amintim că subiectul acesta a fost discutat anterior.', $result->backgroundText);
        self::assertCount(2, $result->referencedArticles);
        self::assertSame('MOC-Politica-Interna.md', $result->mocUsed);
        self::assertSame(3, $result->sourcesCount);
    }

    public function testBackgroundResultWithNoReferences(): void
    {
        $result = new BackgroundResult(
            backgroundText: 'Un paragraf simplu de context.',
        );

        self::assertSame([], $result->referencedArticles);
        self::assertNull($result->mocUsed);
        self::assertSame(0, $result->sourcesCount);
    }

    public function testBackgroundResultBackgroundTextIsAccessible(): void
    {
        $text = 'Amintim că în luna martie 2026, Parlamentul a votat această lege.';
        $result = new BackgroundResult(
            backgroundText: $text,
            referencedArticles: [1, 2, 3],
            mocUsed: 'MOC-Politica-Interna.md',
            sourcesCount: 5,
        );

        self::assertSame($text, $result->backgroundText);
        self::assertSame([1, 2, 3], $result->referencedArticles);
        self::assertSame(5, $result->sourcesCount);
    }
}
