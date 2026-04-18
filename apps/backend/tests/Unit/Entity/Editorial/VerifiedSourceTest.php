<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Editorial;

use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Enum\EditorialAlignment;
use App\Enum\SourceCategory;
use PHPUnit\Framework\TestCase;

/**
 * T53.1 — VerifiedSource entity unit tests.
 *
 * Covers construction, FK-delegation (with Source attached and detached),
 * slug-as-fallback for display, and ADR-020 D4 amendment 2026-04-18 guarantee
 * that canonical Source registry fields are never duplicated.
 */
class VerifiedSourceTest extends TestCase
{
    public function testConstructionWithoutSourceYieldsNullDelegatedAccessors(): void
    {
        $vs = new VerifiedSource(
            slug: 'reuters',
            tier: 1,
            editorialAlignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.95',
        );

        self::assertNull($vs->getId());
        self::assertSame('reuters', $vs->getSlug());
        self::assertNull($vs->getSource());
        self::assertSame(1, $vs->getTier());
        self::assertSame(EditorialAlignment::WIRE_NEUTRAL, $vs->getEditorialAlignment());
        self::assertSame('0.95', $vs->getTrustScoreBaseline());
        self::assertNull($vs->getTrustScoreRolling());
        self::assertTrue($vs->isEnabled());
        self::assertNull($vs->getEditorialNotes());

        // Delegated accessors return null when Source is detached...
        self::assertNull($vs->getRssUrl());
        self::assertNull($vs->getCredibility());
        self::assertNull($vs->getCountry());
        self::assertNull($vs->getFetchFrequencyMinutes());
        // ...except getName(), which falls back to slug so UI always has a label.
        self::assertSame('reuters', $vs->getName());
    }

    public function testDelegationToLinkedSource(): void
    {
        $source = (new Source())
            ->setName('Reuters')
            ->setRssUrl('https://feeds.reuters.com/reuters/topNews')
            ->setCredibilityWeight(0.95)
            ->setCountry('GB')
            ->setSourceCategory(SourceCategory::AGENCY)
            ->setFetchFrequencyMinutes(30)
            ->setIsActive(true);

        $vs = new VerifiedSource(
            slug: 'reuters',
            tier: 1,
            editorialAlignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.95',
            source: $source,
        );

        self::assertSame('Reuters', $vs->getName(), 'getName() delegates to Source, not slug');
        self::assertSame('https://feeds.reuters.com/reuters/topNews', $vs->getRssUrl());
        self::assertSame(0.95, $vs->getCredibility());
        self::assertSame('GB', $vs->getCountry());
        self::assertSame(30, $vs->getFetchFrequencyMinutes());
    }

    public function testOwnStoredLanguageAndUrl(): void
    {
        $vs = new VerifiedSource(
            slug: 'meduza',
            tier: 1,
            editorialAlignment: EditorialAlignment::INDEPENDENT_RU,
            trustScoreBaseline: '0.80',
        );
        $vs->setLanguage('ru');
        $vs->setUrl('https://meduza.io');

        self::assertSame('ru', $vs->getLanguage());
        self::assertSame('https://meduza.io', $vs->getUrl());
    }

    public function testDetachingSourceFallsBackToSlugForName(): void
    {
        $source = (new Source())->setName('Ukrinform')->setCountry('UA');
        $vs = new VerifiedSource(
            slug: 'ukrinform',
            tier: 1,
            editorialAlignment: EditorialAlignment::UKRAINIAN_STATE,
            trustScoreBaseline: '0.80',
            source: $source,
        );

        self::assertSame('Ukrinform', $vs->getName());
        self::assertSame('UA', $vs->getCountry());

        $vs->setSource(null);

        self::assertSame('ukrinform', $vs->getName(), 'Name falls back to slug when Source detached');
        self::assertNull($vs->getCountry(), 'Country returns null when Source detached (not duplicated locally)');
    }

    public function testMutators(): void
    {
        $vs = new VerifiedSource(
            slug: 'tv8-md',
            tier: 2,
            editorialAlignment: EditorialAlignment::MD_INDEPENDENT_PRO_EU,
            trustScoreBaseline: '0.85',
        );

        $vs->setTier(3);
        $vs->setEditorialAlignment(EditorialAlignment::MD_GOVERNMENT);
        $vs->setTrustScoreBaseline('0.70');
        $vs->setTrustScoreRolling('0.65');
        $vs->setEnabled(false);
        $vs->setEditorialNotes('Temporarily disabled pending credibility review');

        self::assertSame(3, $vs->getTier());
        self::assertSame(EditorialAlignment::MD_GOVERNMENT, $vs->getEditorialAlignment());
        self::assertSame('0.70', $vs->getTrustScoreBaseline());
        self::assertSame('0.65', $vs->getTrustScoreRolling());
        self::assertFalse($vs->isEnabled());
        self::assertSame('Temporarily disabled pending credibility review', $vs->getEditorialNotes());
    }

    public function testTimestampsOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $vs = new VerifiedSource(
            slug: 'zdg',
            tier: 1,
            editorialAlignment: EditorialAlignment::MD_INVESTIGATIVE,
            trustScoreBaseline: '0.90',
        );
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $vs->getCreatedAt());
        self::assertLessThanOrEqual($after, $vs->getCreatedAt());
        self::assertEquals($vs->getCreatedAt(), $vs->getUpdatedAt());
    }

    public function testOnPreUpdateRefreshesUpdatedAtTimestamp(): void
    {
        $vs = new VerifiedSource(
            slug: 'agerpres',
            tier: 1,
            editorialAlignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.85',
        );

        $initialUpdatedAt = $vs->getUpdatedAt();
        usleep(1100);
        $vs->onPreUpdate();

        self::assertGreaterThan($initialUpdatedAt, $vs->getUpdatedAt());
    }
}
