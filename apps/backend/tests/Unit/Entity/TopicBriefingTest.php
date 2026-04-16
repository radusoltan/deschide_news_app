<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Topic;
use App\Entity\TopicBriefing;
use App\Enum\BriefingCadence;
use App\Enum\BriefingStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TopicBriefingTest extends TestCase
{
    private function createBriefing(BriefingCadence $cadence = BriefingCadence::DAILY): TopicBriefing
    {
        $topic = new Topic();
        $topic->setTitle('Test Topic');

        return new TopicBriefing(
            topic: $topic,
            cadence: $cadence,
            periodFrom: new \DateTimeImmutable('2026-04-15 00:00:00'),
            periodTo: new \DateTimeImmutable('2026-04-16 00:00:00'),
        );
    }

    #[Test]
    public function itInitializesWithConstructorArgs(): void
    {
        $briefing = $this->createBriefing();

        $this->assertNull($briefing->getId());
        $this->assertSame('Test Topic', $briefing->getTopic()->getTitle());
        $this->assertSame(BriefingCadence::DAILY, $briefing->getCadence());
        $this->assertSame(BriefingStatus::PENDING, $briefing->getStatus());
        $this->assertNull($briefing->getTitle());
        $this->assertNull($briefing->getSummaryShort());
        $this->assertNull($briefing->getSummaryLong());
        $this->assertNull($briefing->getKeyFacts());
        $this->assertNull($briefing->getWhyItMatters());
        $this->assertNull($briefing->getGeminiDraftRaw());
        $this->assertFalse($briefing->isClaudePolished());
        $this->assertSame(0, $briefing->getPrCount());
        $this->assertNull($briefing->getGeneratedAt());
    }

    #[Test]
    public function itSetsAndGetsStatus(): void
    {
        $briefing = $this->createBriefing();
        $result = $briefing->setStatus(BriefingStatus::GENERATING);

        $this->assertSame(BriefingStatus::GENERATING, $briefing->getStatus());
        $this->assertSame($briefing, $result);
    }

    #[Test]
    public function itSetsAndGetsTitle(): void
    {
        $briefing = $this->createBriefing();
        $briefing->setTitle('Sumar zilnic: Politică externă');

        $this->assertSame('Sumar zilnic: Politică externă', $briefing->getTitle());
    }

    #[Test]
    public function itSetsAndGetsSummaryShort(): void
    {
        $briefing = $this->createBriefing();
        $briefing->setSummaryShort('Rezumatul evenimentelor principale.');

        $this->assertSame('Rezumatul evenimentelor principale.', $briefing->getSummaryShort());
    }

    #[Test]
    public function itSetsAndGetsSummaryLong(): void
    {
        $briefing = $this->createBriefing();
        $briefing->setSummaryLong('Un rezumat detaliat al evenimentelor din ultimele 24 de ore.');

        $this->assertSame('Un rezumat detaliat al evenimentelor din ultimele 24 de ore.', $briefing->getSummaryLong());
    }

    #[Test]
    public function itSetsAndGetsKeyFacts(): void
    {
        $briefing = $this->createBriefing();
        $facts = ['Fapt 1', 'Fapt 2', 'Fapt 3'];
        $briefing->setKeyFacts($facts);

        $this->assertSame($facts, $briefing->getKeyFacts());
    }

    #[Test]
    public function itSetsAndGetsWhyItMatters(): void
    {
        $briefing = $this->createBriefing();
        $briefing->setWhyItMatters('Relevant pentru parcursul european.');

        $this->assertSame('Relevant pentru parcursul european.', $briefing->getWhyItMatters());
    }

    #[Test]
    public function itSetsAndGetsGeminiDraftRaw(): void
    {
        $briefing = $this->createBriefing();
        $raw = '{"summary_short": "test"}';
        $briefing->setGeminiDraftRaw($raw);

        $this->assertSame($raw, $briefing->getGeminiDraftRaw());
    }

    #[Test]
    public function itSetsAndGetsClaudePolished(): void
    {
        $briefing = $this->createBriefing();
        $briefing->setClaudePolished(true);

        $this->assertTrue($briefing->isClaudePolished());
    }

    #[Test]
    public function itSetsAndGetsPrCount(): void
    {
        $briefing = $this->createBriefing();
        $briefing->setPrCount(12);

        $this->assertSame(12, $briefing->getPrCount());
    }

    #[Test]
    public function itReturnsPeriodRange(): void
    {
        $briefing = $this->createBriefing();

        $this->assertSame('2026-04-15', $briefing->getPeriodFrom()->format('Y-m-d'));
        $this->assertSame('2026-04-16', $briefing->getPeriodTo()->format('Y-m-d'));
    }

    #[Test]
    public function itSetsAndGetsGeneratedAt(): void
    {
        $briefing = $this->createBriefing();
        $now = new \DateTimeImmutable();
        $briefing->setGeneratedAt($now);

        $this->assertSame($now, $briefing->getGeneratedAt());
    }

    #[Test]
    public function itSupportsAllCadences(): void
    {
        foreach (BriefingCadence::cases() as $cadence) {
            $briefing = $this->createBriefing($cadence);
            $this->assertSame($cadence, $briefing->getCadence());
        }
    }

    #[Test]
    public function itHasFluentInterface(): void
    {
        $briefing = $this->createBriefing();

        $result = $briefing
            ->setTitle('Test')
            ->setSummaryShort('Short')
            ->setSummaryLong('Long')
            ->setKeyFacts(['a', 'b'])
            ->setWhyItMatters('Matters')
            ->setGeminiDraftRaw('raw')
            ->setClaudePolished(true)
            ->setPrCount(5)
            ->setStatus(BriefingStatus::POLISHED)
            ->setGeneratedAt(new \DateTimeImmutable());

        $this->assertSame($briefing, $result);
    }
}
