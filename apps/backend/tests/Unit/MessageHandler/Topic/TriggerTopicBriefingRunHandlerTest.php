<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Topic;

use App\Dto\Editorial\BriefingEligibilityDecision;
use App\Entity\Topic;
use App\Enum\BriefingCadence;
use App\Message\Topic\GenerateTopicBriefingMessage;
use App\Message\Topic\TriggerTopicBriefingRunMessage;
use App\MessageHandler\Topic\TriggerTopicBriefingRunHandler;
use App\Repository\TopicRepository;
use App\Service\Editorial\BriefingEligibilityGateService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class TriggerTopicBriefingRunHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesOnlyForEligibleTopics(): void
    {
        $topic1 = $this->createTopicWithId(1, 'Eligible');
        $topic2 = $this->createTopicWithId(2, 'Not Eligible');
        $topic3 = $this->createTopicWithId(3, 'Also Eligible');

        $repo = $this->createMock(TopicRepository::class);
        $repo->method('findBy')->willReturn([$topic1, $topic2, $topic3]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $gate = $this->createMock(BriefingEligibilityGateService::class);
        $gate->method('evaluate')
            ->willReturnCallback(function (Topic $topic) {
                $eligible = $topic->getTitle() !== 'Not Eligible';

                return new BriefingEligibilityDecision(
                    $eligible,
                    BriefingCadence::DAILY,
                    $eligible ? 8 : 1,
                    $eligible ? 3.5 : 0.5,
                    $eligible ? [] : ['low_pr_count:1_min:5'],
                );
            });

        $dispatched = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturnCallback(function (object $msg) use (&$dispatched) {
                $dispatched[] = $msg;

                return new Envelope($msg);
            });

        $handler = new TriggerTopicBriefingRunHandler($em, $gate, $bus, new NullLogger());

        $handler(new TriggerTopicBriefingRunMessage(BriefingCadence::DAILY));

        $this->assertCount(2, $dispatched);
        $this->assertContainsOnlyInstancesOf(GenerateTopicBriefingMessage::class, $dispatched);
    }

    #[Test]
    public function itDispatchesNothingWhenAllFiltered(): void
    {
        $repo = $this->createMock(TopicRepository::class);
        $repo->method('findBy')->willReturn([
            $this->createTopicWithId(1, 'T1'),
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $gate = $this->createMock(BriefingEligibilityGateService::class);
        $gate->method('evaluate')
            ->willReturn(new BriefingEligibilityDecision(
                false, BriefingCadence::HOURLY, 0, 0.0, ['low_pr_count:0_min:3'],
            ));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $handler = new TriggerTopicBriefingRunHandler($em, $gate, $bus, new NullLogger());
        $handler(new TriggerTopicBriefingRunMessage(BriefingCadence::HOURLY));
    }

    #[Test]
    public function itHandlesEmptyTopicList(): void
    {
        $repo = $this->createMock(TopicRepository::class);
        $repo->method('findBy')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $gate = $this->createMock(BriefingEligibilityGateService::class);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $handler = new TriggerTopicBriefingRunHandler($em, $gate, $bus, new NullLogger());
        $handler(new TriggerTopicBriefingRunMessage(BriefingCadence::WEEKLY));

        $this->assertTrue(true);
    }

    private function createTopicWithId(int $id, string $title): Topic
    {
        $topic = new Topic();
        $topic->setTitle($title);
        $topic->setIsActive(true);

        // Use reflection to set ID
        $ref = new \ReflectionProperty(Topic::class, 'id');
        $ref->setValue($topic, $id);

        return $topic;
    }
}
