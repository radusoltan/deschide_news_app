<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Editorial;

use App\Entity\Editorial\SourceClaimHistory;
use App\Entity\Editorial\VerifiedSource;
use App\Enum\ClaimOutcome;
use App\Enum\EditorialAlignment;
use PHPUnit\Framework\TestCase;

/**
 * T53.3 — SourceClaimHistory schema-only tests.
 *
 * Schema shape is validated by Doctrine migrations + schema:validate; these
 * tests lock the minimal PHP contract that Sprint 54 business logic will
 * depend on (constructor args, enum type, timestamp default).
 */
class SourceClaimHistoryTest extends TestCase
{
    public function testConstructionDefaultsObservedAtToNow(): void
    {
        $before = new \DateTimeImmutable();
        $history = new SourceClaimHistory(
            verifiedSource: $this->newVerifiedSource(),
            claimText: 'The government signed a decree on 2026-04-18',
            outcome: ClaimOutcome::UNRESOLVED,
        );
        $after = new \DateTimeImmutable();

        self::assertNull($history->getId());
        self::assertSame('The government signed a decree on 2026-04-18', $history->getClaimText());
        self::assertSame(ClaimOutcome::UNRESOLVED, $history->getOutcome());
        self::assertGreaterThanOrEqual($before, $history->getObservedAt());
        self::assertLessThanOrEqual($after, $history->getObservedAt());
        self::assertNull($history->getConfirmedAt());
        self::assertNull($history->getNotes());
    }

    public function testConstructionAcceptsExplicitObservedAt(): void
    {
        $observed = new \DateTimeImmutable('2026-04-17 12:00:00');
        $history = new SourceClaimHistory(
            verifiedSource: $this->newVerifiedSource(),
            claimText: 'Claim',
            outcome: ClaimOutcome::CONFIRMED,
            observedAt: $observed,
        );

        self::assertEquals($observed, $history->getObservedAt());
    }

    public function testMutatorsForOutcomeAndConfirmationTrail(): void
    {
        $history = new SourceClaimHistory(
            verifiedSource: $this->newVerifiedSource(),
            claimText: 'Claim',
            outcome: ClaimOutcome::UNRESOLVED,
        );

        $confirmedAt = new \DateTimeImmutable();
        $history->setOutcome(ClaimOutcome::CONFIRMED);
        $history->setConfirmedAt($confirmedAt);
        $history->setNotes('Confirmed via Reuters cross-reference');

        self::assertSame(ClaimOutcome::CONFIRMED, $history->getOutcome());
        self::assertEquals($confirmedAt, $history->getConfirmedAt());
        self::assertSame('Confirmed via Reuters cross-reference', $history->getNotes());
    }

    private function newVerifiedSource(): VerifiedSource
    {
        return new VerifiedSource(
            slug: 't53r3-claim-vs-' . uniqid(),
            tier: 2,
            editorialAlignment: EditorialAlignment::MD_INVESTIGATIVE,
            trustScoreBaseline: '0.90',
        );
    }
}
