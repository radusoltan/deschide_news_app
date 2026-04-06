<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\AggregatorSchedulerCommand;
use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\AggregatorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(AggregatorSchedulerCommand::class)]
class AggregatorSchedulerCommandTest extends TestCase
{
    public function testDryRunDisplaysTable(): void
    {
        $aggregator = $this->createMock(AggregatorInterface::class);
        $aggregator->method('getName')->willReturn('Test Source');
        $aggregator->method('getSourceType')->willReturn(AggregatorSourceType::GOOGLE_NEWS_RSS);
        $aggregator->method('fetch')->willReturn([
            new AggregatorResult(
                title: 'Test article',
                summary: 'Summary',
                sourceUrl: 'https://example.com',
                sourceLanguage: 'en',
                sourceName: 'Test',
                publishedAt: new \DateTimeImmutable('2026-04-06'),
                rawContent: 'Content',
            ),
        ]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $command = new AggregatorSchedulerCommand([$aggregator], $bus);
        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('Test article', $output);
        self::assertStringContainsString('Found 1 results', $output);
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testDispatchesMessages(): void
    {
        $aggregator = $this->createMock(AggregatorInterface::class);
        $aggregator->method('getName')->willReturn('Test Source');
        $aggregator->method('getSourceType')->willReturn(AggregatorSourceType::GOOGLE_NEWS_RSS);
        $aggregator->method('fetch')->willReturn([
            new AggregatorResult(
                title: 'Article 1',
                summary: 'Summary 1',
                sourceUrl: 'https://example.com/1',
                sourceLanguage: 'en',
                sourceName: 'Test',
                publishedAt: new \DateTimeImmutable(),
                rawContent: 'Content 1',
            ),
            new AggregatorResult(
                title: 'Article 2',
                summary: 'Summary 2',
                sourceUrl: 'https://example.com/2',
                sourceLanguage: 'ro',
                sourceName: 'Test',
                publishedAt: new \DateTimeImmutable(),
                rawContent: 'Content 2',
            ),
        ]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $command = new AggregatorSchedulerCommand([$aggregator], $bus);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('Dispatched 2 results', $tester->getDisplay());
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testSourceFilter(): void
    {
        $googleNews = $this->createMock(AggregatorInterface::class);
        $googleNews->method('getName')->willReturn('Google News');
        $googleNews->method('getSourceType')->willReturn(AggregatorSourceType::GOOGLE_NEWS_RSS);
        $googleNews->method('fetch')->willReturn([
            new AggregatorResult(
                title: 'Google result',
                summary: '',
                sourceUrl: 'https://example.com',
                sourceLanguage: 'en',
                sourceName: 'Google',
                publishedAt: new \DateTimeImmutable(),
                rawContent: 'Content',
            ),
        ]);

        $alerts = $this->createMock(AggregatorInterface::class);
        $alerts->method('getName')->willReturn('Alerts');
        $alerts->method('getSourceType')->willReturn(AggregatorSourceType::GOOGLE_ALERTS);
        $alerts->expects(self::never())->method('fetch');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $command = new AggregatorSchedulerCommand([$googleNews, $alerts], $bus);
        $tester = new CommandTester($command);
        $tester->execute(['--source' => 'google_news_rss']);

        self::assertStringContainsString('Google News', $tester->getDisplay());
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testLimitOption(): void
    {
        $aggregator = $this->createMock(AggregatorInterface::class);
        $aggregator->method('getName')->willReturn('Test');
        $aggregator->method('getSourceType')->willReturn(AggregatorSourceType::GOOGLE_NEWS_RSS);
        $aggregator->method('fetch')->willReturn(array_map(
            fn(int $i) => new AggregatorResult(
                title: "Article $i",
                summary: '',
                sourceUrl: "https://example.com/$i",
                sourceLanguage: 'en',
                sourceName: 'Test',
                publishedAt: new \DateTimeImmutable(),
                rawContent: "Content $i",
            ),
            range(1, 10),
        ));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $command = new AggregatorSchedulerCommand([$aggregator], $bus);
        $tester = new CommandTester($command);
        $tester->execute(['--limit' => '3']);

        self::assertSame(0, $tester->getStatusCode());
    }
}
