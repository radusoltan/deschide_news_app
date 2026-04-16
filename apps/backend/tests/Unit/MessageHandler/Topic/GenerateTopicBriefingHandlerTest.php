<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Topic;

use App\Entity\Topic;
use App\Entity\TopicBriefing;
use App\Enum\BriefingCadence;
use App\Enum\BriefingStatus;
use App\Message\Topic\GenerateTopicBriefingMessage;
use App\MessageHandler\Topic\GenerateTopicBriefingHandler;
use App\Service\Editorial\TopicBriefingWriterService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GenerateTopicBriefingHandlerTest extends TestCase
{
    #[Test]
    public function itSkipsWhenTopicNotFound(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);

        $writer = $this->createMock(TopicBriefingWriterService::class);
        $writer->expects($this->never())->method('generate');

        $handler = new GenerateTopicBriefingHandler($writer, $em, new NullLogger());

        $msg = new GenerateTopicBriefingMessage(
            999, BriefingCadence::DAILY,
            '2026-04-15T00:00:00+00:00',
            '2026-04-16T00:00:00+00:00',
        );

        $handler($msg);
    }

    #[Test]
    public function itCallsWriterServiceForExistingTopic(): void
    {
        $topic = new Topic();
        $topic->setTitle('Test');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($topic);

        $briefing = new TopicBriefing(
            $topic, BriefingCadence::DAILY,
            new \DateTimeImmutable('2026-04-15'),
            new \DateTimeImmutable('2026-04-16'),
        );

        $writer = $this->createMock(TopicBriefingWriterService::class);
        $writer->expects($this->once())
            ->method('generate')
            ->willReturn($briefing);

        $handler = new GenerateTopicBriefingHandler($writer, $em, new NullLogger());

        $msg = new GenerateTopicBriefingMessage(
            1, BriefingCadence::DAILY,
            '2026-04-15T00:00:00+00:00',
            '2026-04-16T00:00:00+00:00',
        );

        $handler($msg);
    }

    #[Test]
    public function itHandlesNullWriterResult(): void
    {
        $topic = new Topic();
        $topic->setTitle('Test');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($topic);

        $writer = $this->createMock(TopicBriefingWriterService::class);
        $writer->method('generate')->willReturn(null);

        $handler = new GenerateTopicBriefingHandler($writer, $em, new NullLogger());

        $msg = new GenerateTopicBriefingMessage(
            1, BriefingCadence::HOURLY,
            '2026-04-16T13:00:00+00:00',
            '2026-04-16T14:00:00+00:00',
        );

        // Should not throw
        $handler($msg);
        $this->assertTrue(true);
    }
}
