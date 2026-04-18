<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Editorial;

use App\Entity\Editorial\EditorialEscalationLog;
use App\Enum\Editorial\EscalationCategory;
use App\Enum\EscalationDecision;
use App\Message\Editorial\ExpireEscalationsMessage;
use App\MessageHandler\Editorial\ExpireEscalationsMessageHandler;
use App\Repository\AppSettingRepository;
use App\Repository\Editorial\EditorialEscalationLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Unit test for {@see ExpireEscalationsMessageHandler} (Sprint 55 T55.11).
 */
class ExpireEscalationsMessageHandlerTest extends TestCase
{
    private EditorialEscalationLogRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $em;
    private HubInterface&MockObject $hub;
    private AppSettingRepository&MockObject $appSettings;
    private LoggerInterface&MockObject $logger;
    private ExpireEscalationsMessageHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EditorialEscalationLogRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->hub = $this->createMock(HubInterface::class);
        $this->appSettings = $this->createMock(AppSettingRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->appSettings->method('getBool')
            ->with('editorial.pipeline.enabled', false)
            ->willReturn(true);

        $this->handler = new ExpireEscalationsMessageHandler(
            $this->repository,
            $this->em,
            $this->hub,
            $this->appSettings,
            $this->logger,
        );
    }

    public function testExpiredRowsTransitionedToExpiredWithDecidedAt(): void
    {
        $logA = $this->makeLog(101, EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION);
        $logB = $this->makeLog(102, EscalationCategory::FAMILY_C_TRANSNISTRIA_GAGAUZIA);
        $logC = $this->makeLog(103, EscalationCategory::CATEGORY_7_PRE_CEC_ELECTORAL);

        $this->repository->expects($this->once())
            ->method('findSlaExpired')
            ->with($this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([$logA, $logB, $logC]);

        $this->em->expects($this->once())->method('flush');

        $publishedPayloads = [];
        $this->hub->expects($this->exactly(3))
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedPayloads): string {
                $publishedPayloads[] = json_decode($update->getData(), true);

                return 'mercure-' . count($publishedPayloads);
            });

        ($this->handler)(new ExpireEscalationsMessage());

        // All three rows flipped to EXPIRED.
        $this->assertSame(EscalationDecision::EXPIRED, $logA->getDecision());
        $this->assertSame(EscalationDecision::EXPIRED, $logB->getDecision());
        $this->assertSame(EscalationDecision::EXPIRED, $logC->getDecision());
        $this->assertNotNull($logA->getDecidedAt());
        $this->assertNotNull($logB->getDecidedAt());
        $this->assertNotNull($logC->getDecidedAt());

        // decidedBy stays null — EXPIRED is NOT a human decision.
        $this->assertNull($logA->getDecidedBy());
        $this->assertNull($logB->getDecidedBy());
        $this->assertNull($logC->getDecidedBy());

        // Mercure payloads carry event=expired, not event=decided.
        $this->assertCount(3, $publishedPayloads);
        foreach ($publishedPayloads as $payload) {
            $this->assertSame('expired', $payload['event']);
        }
    }

    public function testEmptyExpiredListSkipsFlushAndPublish(): void
    {
        $this->repository->method('findSlaExpired')->willReturn([]);

        $this->em->expects($this->never())->method('flush');
        $this->hub->expects($this->never())->method('publish');

        ($this->handler)(new ExpireEscalationsMessage());
    }

    public function testPipelineDisabledShortCircuitsWithoutQuery(): void
    {
        $gatedAppSettings = $this->createMock(AppSettingRepository::class);
        $gatedAppSettings->method('getBool')
            ->with('editorial.pipeline.enabled', false)
            ->willReturn(false);

        $handler = new ExpireEscalationsMessageHandler(
            $this->repository,
            $this->em,
            $this->hub,
            $gatedAppSettings,
            $this->logger,
        );

        // Pipeline is off mid-tick — handler must not even query the repo.
        $this->repository->expects($this->never())->method('findSlaExpired');
        $this->em->expects($this->never())->method('flush');
        $this->hub->expects($this->never())->method('publish');

        $handler(new ExpireEscalationsMessage());
    }

    public function testMercurePublishFailureDoesNotBlockOtherRows(): void
    {
        $logA = $this->makeLog(201, EscalationCategory::CATEGORY_1_NUCLEAR_WAR);
        $logB = $this->makeLog(202, EscalationCategory::FAMILY_A_CHURCH);

        $this->repository->method('findSlaExpired')->willReturn([$logA, $logB]);
        $this->em->expects($this->once())->method('flush');

        $callCount = 0;
        $this->hub->method('publish')->willReturnCallback(
            function () use (&$callCount): string {
                ++$callCount;
                if ($callCount === 1) {
                    throw new \RuntimeException('hub unreachable');
                }

                return 'mercure-2';
            },
        );

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('escalation_expired_mercure_publish_failed', $this->isArray());

        ($this->handler)(new ExpireEscalationsMessage());

        // Both rows were still persisted (flush ran before publish loop).
        $this->assertSame(EscalationDecision::EXPIRED, $logA->getDecision());
        $this->assertSame(EscalationDecision::EXPIRED, $logB->getDecision());
        $this->assertSame(2, $callCount, 'Both publishes attempted despite the first failing');
    }

    public function testRepositoryThrowLoggedButNotRethrown(): void
    {
        $this->repository->method('findSlaExpired')->willThrowException(
            new \RuntimeException('DB connection lost'),
        );

        $this->logger->expects($this->once())
            ->method('error')
            ->with('ExpireEscalationsMessageHandler: sweep threw', $this->isArray());

        // Must not rethrow — avoids scheduler retry storm per S53+ failure contract.
        ($this->handler)(new ExpireEscalationsMessage());
    }

    private function makeLog(int $id, EscalationCategory $category): EditorialEscalationLog
    {
        $log = new EditorialEscalationLog(
            articleSnapshot: ['id' => $id, 'title' => 'fake'],
            category: $category,
            originGraphSnapshot: [],
        );
        $log->setExpiresAt(new \DateTimeImmutable('-5 minutes'));

        // Seed id via reflection so tests can assert it in Mercure payloads.
        $ref = new \ReflectionProperty(EditorialEscalationLog::class, 'id');
        $ref->setValue($log, $id);

        return $log;
    }
}
