<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Editorial;

use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Enum\EditorialAlignment;
use App\Service\ContentHasher;
use PHPUnit\Framework\TestCase;

/**
 * T53.2 — SourceSignal entity unit tests.
 *
 * Covers construction (required fields only), nullable setters for
 * Sprint-54-populated fields, ContentHasher integration contract.
 */
class SourceSignalTest extends TestCase
{
    public function testConstructionPersistsRequiredFieldsAndDefaultsCapturedAt(): void
    {
        $vs = $this->newVerifiedSource();
        $before = new \DateTimeImmutable();
        $signal = new SourceSignal(
            verifiedSource: $vs,
            sourceUrl: 'https://example.invalid/article-1',
            title: 'Breaking news item',
            rawContentHash: str_repeat('a', 64),
        );
        $after = new \DateTimeImmutable();

        self::assertNull($signal->getId());
        self::assertSame($vs, $signal->getVerifiedSource());
        self::assertSame('https://example.invalid/article-1', $signal->getSourceUrl());
        self::assertSame('Breaking news item', $signal->getTitle());
        self::assertSame(str_repeat('a', 64), $signal->getRawContentHash());
        self::assertGreaterThanOrEqual($before, $signal->getCapturedAt());
        self::assertLessThanOrEqual($after, $signal->getCapturedAt());

        // Nullable fields default to null (no Sprint 54 enrichment yet).
        self::assertNull($signal->getCanonicalUrl());
        self::assertNull($signal->getRawSummary());
        self::assertNull($signal->getPublishedAt());
        self::assertNull($signal->getSourceAttribution());
        self::assertNull($signal->getSourceLinksOut());
        self::assertNull($signal->getRawPayload());
    }

    public function testConstructionAcceptsExplicitCapturedAt(): void
    {
        $captured = new \DateTimeImmutable('2026-04-18 09:00:00');

        $signal = new SourceSignal(
            verifiedSource: $this->newVerifiedSource(),
            sourceUrl: 'https://example.invalid/x',
            title: 't',
            rawContentHash: str_repeat('b', 64),
            capturedAt: $captured,
        );

        self::assertEquals($captured, $signal->getCapturedAt());
    }

    public function testNullableSettersMutateCorrectly(): void
    {
        $signal = new SourceSignal(
            verifiedSource: $this->newVerifiedSource(),
            sourceUrl: 'https://example.invalid/x',
            title: 't',
            rawContentHash: str_repeat('c', 64),
        );

        $publishedAt = new \DateTimeImmutable('2026-04-17 15:30:00');

        $signal->setCanonicalUrl('https://example.invalid/x?canonical=1');
        $signal->setRawSummary('A summary.');
        $signal->setPublishedAt($publishedAt);
        $signal->setSourceAttribution('Attributed to a wire report');
        $signal->setSourceLinksOut(['https://a.example', 'https://b.example']);
        $signal->setRawPayload(['guid' => 'abc', 'raw' => ['nested' => true]]);

        self::assertSame('https://example.invalid/x?canonical=1', $signal->getCanonicalUrl());
        self::assertSame('A summary.', $signal->getRawSummary());
        self::assertEquals($publishedAt, $signal->getPublishedAt());
        self::assertSame('Attributed to a wire report', $signal->getSourceAttribution());
        self::assertSame(['https://a.example', 'https://b.example'], $signal->getSourceLinksOut());
        self::assertSame(['guid' => 'abc', 'raw' => ['nested' => true]], $signal->getRawPayload());
    }

    public function testContentHasherProducesAcceptableHashLength(): void
    {
        $hasher = new ContentHasher();
        $hash = $hasher->hash("Title\nhttps://example.invalid\nSummary");

        self::assertSame(64, strlen($hash), 'ContentHasher output fits VARCHAR(64) raw_content_hash column.');

        $signal = new SourceSignal(
            verifiedSource: $this->newVerifiedSource(),
            sourceUrl: 'https://example.invalid',
            title: 'Title',
            rawContentHash: $hash,
        );

        self::assertSame($hash, $signal->getRawContentHash());
    }

    private function newVerifiedSource(): VerifiedSource
    {
        return new VerifiedSource(
            slug: 't53r2-signal-vs-' . uniqid(),
            tier: 1,
            editorialAlignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.95',
        );
    }
}
