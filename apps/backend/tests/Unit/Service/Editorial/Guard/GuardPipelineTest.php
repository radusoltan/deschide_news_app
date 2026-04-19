<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Guard;

use App\Entity\Article;
use App\Service\Editorial\Guard\GuardInterface;
use App\Service\Editorial\Guard\GuardPipeline;
use App\Service\Editorial\Guard\GuardVerdictPart;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for {@see GuardPipeline} aggregation logic (Sprint 55 T55.6).
 */
class GuardPipelineTest extends TestCase
{
    public function testEmptyGuardsProducesPass(): void
    {
        $pipeline = new GuardPipeline([]);
        $verdict = $pipeline->check(new Article());

        $this->assertTrue($verdict->passed);
        $this->assertSame([], $verdict->failures);
        $this->assertSame([], $verdict->warnings);
        $this->assertNull($verdict->escalationCode);
    }

    public function testAllGuardsPassProducesPass(): void
    {
        $pipeline = new GuardPipeline([
            $this->fakeGuard(GuardVerdictPart::pass()),
            $this->fakeGuard(GuardVerdictPart::passWithWarnings(['note: short lead'])),
            $this->fakeGuard(GuardVerdictPart::pass()),
        ]);

        $verdict = $pipeline->check(new Article());

        $this->assertTrue($verdict->passed);
        $this->assertSame([], $verdict->failures);
        $this->assertSame(['note: short lead'], $verdict->warnings);
        $this->assertNull($verdict->escalationCode);
    }

    public function testFailureFromAnyGuardFailsPipeline(): void
    {
        $pipeline = new GuardPipeline([
            $this->fakeGuard(GuardVerdictPart::pass()),
            $this->fakeGuard(new GuardVerdictPart(failures: ['sentence too long'])),
        ]);

        $verdict = $pipeline->check(new Article());

        $this->assertFalse($verdict->passed);
        $this->assertSame(['sentence too long'], $verdict->failures);
        $this->assertNull($verdict->escalationCode);
    }

    public function testFailuresAndWarningsConcatenatedInGuardOrder(): void
    {
        $pipeline = new GuardPipeline([
            $this->fakeGuard(new GuardVerdictPart(failures: ['A-fail'], warnings: ['A-warn'])),
            $this->fakeGuard(new GuardVerdictPart(failures: ['B-fail'], warnings: ['B-warn'])),
        ]);

        $verdict = $pipeline->check(new Article());

        $this->assertFalse($verdict->passed);
        $this->assertSame(['A-fail', 'B-fail'], $verdict->failures);
        $this->assertSame(['A-warn', 'B-warn'], $verdict->warnings);
    }

    public function testEscalationCodeFromFirstGuardWins(): void
    {
        $pipeline = new GuardPipeline([
            $this->fakeGuard(new GuardVerdictPart(failures: ['x'], escalationCode: 'CODE_A')),
            $this->fakeGuard(new GuardVerdictPart(failures: ['y'], escalationCode: 'CODE_B')),
        ]);

        $verdict = $pipeline->check(new Article());

        $this->assertFalse($verdict->passed);
        $this->assertSame('CODE_A', $verdict->escalationCode);
    }

    public function testEscalationCodeSetsPassedFalseEvenWithoutFailures(): void
    {
        // Edge case: a guard could hypothetically set only escalationCode. The
        // pipeline must still treat that as non-passing.
        $pipeline = new GuardPipeline([
            $this->fakeGuard(new GuardVerdictPart(escalationCode: 'ESCALATE_THING')),
        ]);

        $verdict = $pipeline->check(new Article());

        $this->assertFalse($verdict->passed);
        $this->assertSame([], $verdict->failures);
        $this->assertSame('ESCALATE_THING', $verdict->escalationCode);
    }

    public function testContextPassedToGuards(): void
    {
        $expectedContext = ['verdict_type' => 'full_flash', 'confidence' => 0.9];

        $guard = $this->createMock(GuardInterface::class);
        $guard->expects($this->once())
            ->method('validate')
            ->with($this->isInstanceOf(Article::class), $expectedContext)
            ->willReturn(GuardVerdictPart::pass());

        $pipeline = new GuardPipeline([$guard]);
        $pipeline->check(new Article(), $expectedContext);
    }

    public function testIterableInput(): void
    {
        // Generator input (matches real !tagged_iterator semantics).
        $gen = (function () {
            yield $this->fakeGuard(GuardVerdictPart::pass());
            yield $this->fakeGuard(new GuardVerdictPart(warnings: ['w']));
        })();

        $pipeline = new GuardPipeline($gen);
        $verdict = $pipeline->check(new Article());

        $this->assertTrue($verdict->passed);
        $this->assertSame(['w'], $verdict->warnings);
    }

    private function fakeGuard(GuardVerdictPart $part): GuardInterface
    {
        return new class($part) implements GuardInterface {
            public function __construct(private readonly GuardVerdictPart $part) {}

            public function validate(Article $article, array $context = []): GuardVerdictPart
            {
                return $this->part;
            }
        };
    }
}
