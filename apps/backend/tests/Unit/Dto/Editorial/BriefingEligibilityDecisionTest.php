<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Editorial;

use App\Dto\Editorial\BriefingEligibilityDecision;
use App\Enum\BriefingCadence;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BriefingEligibilityDecisionTest extends TestCase
{
    #[Test]
    public function itCreatesEligibleDecision(): void
    {
        $decision = new BriefingEligibilityDecision(
            eligible: true,
            cadence: BriefingCadence::DAILY,
            prCount: 8,
            avgRelevance: 3.5,
            reasons: [],
        );

        $this->assertTrue($decision->eligible);
        $this->assertSame(BriefingCadence::DAILY, $decision->cadence);
        $this->assertSame(8, $decision->prCount);
        $this->assertSame(3.5, $decision->avgRelevance);
        $this->assertSame([], $decision->reasons);
    }

    #[Test]
    public function itCreatesIneligibleDecisionWithReasons(): void
    {
        $decision = new BriefingEligibilityDecision(
            eligible: false,
            cadence: BriefingCadence::HOURLY,
            prCount: 1,
            avgRelevance: 1.5,
            reasons: ['low_pr_count:1_min:3', 'low_relevance:1.50_min:3.00'],
        );

        $this->assertFalse($decision->eligible);
        $this->assertSame(BriefingCadence::HOURLY, $decision->cadence);
        $this->assertSame(1, $decision->prCount);
        $this->assertCount(2, $decision->reasons);
    }

    #[Test]
    public function itIsReadonly(): void
    {
        $decision = new BriefingEligibilityDecision(
            eligible: true,
            cadence: BriefingCadence::WEEKLY,
            prCount: 15,
            avgRelevance: 2.5,
            reasons: [],
        );

        $ref = new \ReflectionClass($decision);
        $this->assertTrue($ref->isReadOnly());
    }
}
