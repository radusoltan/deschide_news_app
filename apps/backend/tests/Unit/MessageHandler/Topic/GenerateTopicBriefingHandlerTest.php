<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Topic;

use App\Entity\Topic;
use App\Entity\TopicBriefing;
use App\Enum\BriefingCadence;
use App\Enum\BriefingStatus;
use App\Message\GenerateTopicArticleMessage;
use App\Message\Topic\GenerateTopicBriefingMessage;
use App\MessageHandler\Topic\GenerateTopicBriefingHandler;
use App\Repository\AppSettingRepository;
use App\Service\Editorial\TopicBriefingWriterService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class GenerateTopicBriefingHandlerTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;
    /** @var TopicBriefingWriterService&MockObject */
    private TopicBriefingWriterService $writer;
    /** @var AppSettingRepository&MockObject */
    private AppSettingRepository $appSettings;
    /** @var MessageBusInterface&MockObject */
    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->writer = $this->createMock(TopicBriefingWriterService::class);
        $this->appSettings = $this->createMock(AppSettingRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
    }

    #[Test]
    public function itSkipsWhenTopicNotFound(): void
    {
        $this->em->method('find')->willReturn(null);
        $this->writer->expects($this->never())->method('generate');
        $this->messageBus->expects($this->never())->method('dispatch');

        $handler = $this->handler();

        $handler($this->message(BriefingCadence::DAILY, 999));
    }

    #[Test]
    public function itCallsWriterServiceForExistingTopic(): void
    {
        $topic = $this->topicWithId(1);
        $this->em->method('find')->willReturn($topic);

        $briefing = $this->briefing($topic, BriefingCadence::DAILY, BriefingStatus::POLISHED);
        $this->writer->expects($this->once())
            ->method('generate')
            ->willReturn($briefing);

        $this->appSettings->method('getBool')->willReturn(true);
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn ($m) => new Envelope($m));

        $handler = $this->handler();
        $handler($this->message(BriefingCadence::DAILY));
    }

    #[Test]
    public function itHandlesNullWriterResult(): void
    {
        $topic = $this->topicWithId(1);
        $this->em->method('find')->willReturn($topic);
        $this->writer->method('generate')->willReturn(null);
        $this->messageBus->expects($this->never())->method('dispatch');

        $handler = $this->handler();
        $handler($this->message(BriefingCadence::HOURLY));
    }

    #[Test]
    public function itChainsArticleDispatchOnHourlyPolished(): void
    {
        $topic = $this->topicWithId(42);
        $this->em->method('find')->willReturn($topic);
        $this->writer->method('generate')->willReturn(
            $this->briefing($topic, BriefingCadence::HOURLY, BriefingStatus::POLISHED),
        );
        $this->appSettings->method('getBool')->willReturn(true);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                return $message instanceof GenerateTopicArticleMessage
                    && $message->topicId === 42;
            }))
            ->willReturnCallback(fn ($m) => new Envelope($m));

        $handler = $this->handler();
        $handler($this->message(BriefingCadence::HOURLY));
    }

    #[Test]
    public function itChainsArticleDispatchOnDailyDraft(): void
    {
        $topic = $this->topicWithId(42);
        $this->em->method('find')->willReturn($topic);
        $this->writer->method('generate')->willReturn(
            // DRAFT = Gemini succeeded, Claude polish failed — still valid signal.
            $this->briefing($topic, BriefingCadence::DAILY, BriefingStatus::DRAFT),
        );
        $this->appSettings->method('getBool')->willReturn(true);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn ($m) => new Envelope($m));

        $handler = $this->handler();
        $handler($this->message(BriefingCadence::DAILY));
    }

    #[Test]
    public function itSkipsArticleDispatchOnWeekly(): void
    {
        $topic = $this->topicWithId(42);
        $this->em->method('find')->willReturn($topic);
        $this->writer->method('generate')->willReturn(
            $this->briefing($topic, BriefingCadence::WEEKLY, BriefingStatus::POLISHED),
        );
        $this->appSettings->method('getBool')->willReturn(true);
        $this->messageBus->expects($this->never())->method('dispatch');

        $handler = $this->handler();
        $handler($this->message(BriefingCadence::WEEKLY));
    }

    #[Test]
    public function itSkipsArticleDispatchWhenFeatureFlagOff(): void
    {
        $topic = $this->topicWithId(42);
        $this->em->method('find')->willReturn($topic);
        $this->writer->method('generate')->willReturn(
            $this->briefing($topic, BriefingCadence::HOURLY, BriefingStatus::POLISHED),
        );
        // Feature flag explicitly disabled.
        $this->appSettings->method('getBool')
            ->with('article_generation.auto_from_briefing', true)
            ->willReturn(false);
        $this->messageBus->expects($this->never())->method('dispatch');

        $handler = $this->handler();
        $handler($this->message(BriefingCadence::HOURLY));
    }

    #[Test]
    public function itSkipsArticleDispatchWhenBriefingFailed(): void
    {
        $topic = $this->topicWithId(42);
        $this->em->method('find')->willReturn($topic);

        $briefing = $this->briefing($topic, BriefingCadence::DAILY, BriefingStatus::FAILED);
        $this->writer->method('generate')->willReturn($briefing);
        $this->appSettings->method('getBool')->willReturn(true);
        $this->messageBus->expects($this->never())->method('dispatch');

        $handler = $this->handler();
        $handler($this->message(BriefingCadence::DAILY));
    }

    private function handler(): GenerateTopicBriefingHandler
    {
        return new GenerateTopicBriefingHandler(
            $this->writer,
            $this->em,
            $this->appSettings,
            $this->messageBus,
            new NullLogger(),
        );
    }

    private function topicWithId(int $id): Topic
    {
        $topic = new Topic();
        $topic->setTitle('Test Topic');
        // Topic::id is assigned by Doctrine; reflect it for assertions.
        (new \ReflectionProperty(Topic::class, 'id'))->setValue($topic, $id);

        return $topic;
    }

    private function briefing(Topic $topic, BriefingCadence $cadence, BriefingStatus $status): TopicBriefing
    {
        $briefing = new TopicBriefing(
            $topic,
            $cadence,
            new \DateTimeImmutable('2026-04-15'),
            new \DateTimeImmutable('2026-04-16'),
        );
        $briefing->setStatus($status);

        return $briefing;
    }

    private function message(BriefingCadence $cadence, int $topicId = 1): GenerateTopicBriefingMessage
    {
        return new GenerateTopicBriefingMessage(
            $topicId,
            $cadence,
            '2026-04-15T00:00:00+00:00',
            '2026-04-16T00:00:00+00:00',
        );
    }
}
