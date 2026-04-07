<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AggregatorRun;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class AggregatorRunTest extends TestCase
{
    #[Test]
    public function itInitializesWithDefaults(): void
    {
        $run = new AggregatorRun();

        $this->assertInstanceOf(Uuid::class, $run->getId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $run->getStartedAt());
        $this->assertSame('running', $run->getStatus());
        $this->assertSame(0, $run->getArticlesFound());
        $this->assertSame(0, $run->getDuplicatesSkipped());
        $this->assertSame(0, $run->getErrorsCount());
        $this->assertNull($run->getErrorDetails());
        $this->assertNull($run->getFinishedAt());
        $this->assertSame('scheduler', $run->getTriggeredBy());
    }

    #[Test]
    public function itGeneratesUuidV7(): void
    {
        $run = new AggregatorRun();
        $id = $run->getId();

        $this->assertInstanceOf(Uuid::class, $id);
        // UUID v7 starts with a time-based component; verify it is a valid UUID string
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id->toRfc4122()
        );
    }

    #[Test]
    public function itSetsAndGetsSource(): void
    {
        $run = new AggregatorRun();
        $result = $run->setSource('google_news_rss');

        $this->assertSame('google_news_rss', $run->getSource());
        $this->assertSame($run, $result);
    }

    #[Test]
    public function itSetsAndGetsTriggeredBy(): void
    {
        $run = new AggregatorRun();
        $run->setTriggeredBy('manual');

        $this->assertSame('manual', $run->getTriggeredBy());
    }

    #[Test]
    public function itSetsAndGetsStatus(): void
    {
        $run = new AggregatorRun();
        $run->setStatus('completed');

        $this->assertSame('completed', $run->getStatus());
    }

    #[Test]
    public function itSetsAndGetsArticlesFound(): void
    {
        $run = new AggregatorRun();
        $run->setArticlesFound(15);

        $this->assertSame(15, $run->getArticlesFound());
    }

    #[Test]
    public function itIncrementsArticlesFound(): void
    {
        $run = new AggregatorRun();

        $run->incrementArticlesFound();
        $this->assertSame(1, $run->getArticlesFound());

        $run->incrementArticlesFound(5);
        $this->assertSame(6, $run->getArticlesFound());
    }

    #[Test]
    public function itIncrementsDuplicatesSkipped(): void
    {
        $run = new AggregatorRun();

        $run->incrementDuplicatesSkipped();
        $this->assertSame(1, $run->getDuplicatesSkipped());

        $run->incrementDuplicatesSkipped(3);
        $this->assertSame(4, $run->getDuplicatesSkipped());
    }

    #[Test]
    public function itSetsAndGetsDuplicatesSkipped(): void
    {
        $run = new AggregatorRun();
        $run->setDuplicatesSkipped(10);

        $this->assertSame(10, $run->getDuplicatesSkipped());
    }

    #[Test]
    public function itIncrementsErrorsCount(): void
    {
        $run = new AggregatorRun();

        $run->incrementErrorsCount();
        $this->assertSame(1, $run->getErrorsCount());

        $run->incrementErrorsCount(2);
        $this->assertSame(3, $run->getErrorsCount());
    }

    #[Test]
    public function itSetsAndGetsErrorDetails(): void
    {
        $run = new AggregatorRun();
        $details = ['Error 1', 'Error 2'];
        $run->setErrorDetails($details);

        $this->assertSame($details, $run->getErrorDetails());

        $run->setErrorDetails(null);
        $this->assertNull($run->getErrorDetails());
    }

    #[Test]
    public function itAddsErrorDetail(): void
    {
        $run = new AggregatorRun();

        $run->addErrorDetail('First error');
        $this->assertSame(['First error'], $run->getErrorDetails());

        $run->addErrorDetail('Second error');
        $this->assertSame(['First error', 'Second error'], $run->getErrorDetails());
    }

    #[Test]
    public function itSetsAndGetsFinishedAt(): void
    {
        $run = new AggregatorRun();
        $now = new \DateTimeImmutable();
        $run->setFinishedAt($now);

        $this->assertSame($now, $run->getFinishedAt());

        $run->setFinishedAt(null);
        $this->assertNull($run->getFinishedAt());
    }

    #[Test]
    public function markCompletedSetsStatusAndFinishedAt(): void
    {
        $run = new AggregatorRun();
        $this->assertSame('running', $run->getStatus());
        $this->assertNull($run->getFinishedAt());

        $result = $run->markCompleted();

        $this->assertSame('completed', $run->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $run->getFinishedAt());
        $this->assertSame($run, $result);
    }

    #[Test]
    public function markFailedSetsStatusFinishedAtAndError(): void
    {
        $run = new AggregatorRun();

        $result = $run->markFailed('Connection timeout');

        $this->assertSame('failed', $run->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $run->getFinishedAt());
        $this->assertSame(['Connection timeout'], $run->getErrorDetails());
        $this->assertSame(1, $run->getErrorsCount());
        $this->assertSame($run, $result);
    }

    #[Test]
    public function markFailedAccumulatesErrors(): void
    {
        $run = new AggregatorRun();
        $run->addErrorDetail('Previous error');
        $run->incrementErrorsCount();

        $run->markFailed('Fatal error');

        $this->assertSame(['Previous error', 'Fatal error'], $run->getErrorDetails());
        $this->assertSame(2, $run->getErrorsCount());
    }

    #[Test]
    public function startedAtIsSetAtConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $run = new AggregatorRun();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $run->getStartedAt());
        $this->assertLessThanOrEqual($after, $run->getStartedAt());
    }

    #[Test]
    public function twoInstancesGetDifferentIds(): void
    {
        $run1 = new AggregatorRun();
        $run2 = new AggregatorRun();

        $this->assertFalse($run1->getId()->equals($run2->getId()));
    }
}
