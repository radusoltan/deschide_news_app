<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Escalation;

use App\Entity\Editorial\EditorialEscalationLog;
use App\Enum\Editorial\EscalationCategory;
use App\Service\Editorial\Escalation\EscalationLogWriter;
use App\Service\Editorial\Escalation\EscalationSlaCalculator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Unit test for {@see EscalationLogWriter} (Sprint 55 T55.8).
 */
class EscalationLogWriterTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private EscalationSlaCalculator&MockObject $slaCalculator;
    private HubInterface&MockObject $hub;
    private LoggerInterface&MockObject $logger;
    private EscalationLogWriter $writer;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->slaCalculator = $this->createMock(EscalationSlaCalculator::class);
        $this->hub = $this->createMock(HubInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->writer = new EscalationLogWriter(
            $this->em,
            $this->slaCalculator,
            $this->hub,
            $this->logger,
        );
    }

    public function testWritePersistsLogAndPublishesMercure(): void
    {
        $expiresAt = new \DateTimeImmutable('+10 minutes');
        $this->slaCalculator->method('computeExpiresAt')->willReturn($expiresAt);

        $persistedLog = null;
        $this->em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persistedLog): void {
                $this->assertInstanceOf(EditorialEscalationLog::class, $entity);
                $persistedLog = $entity;
            });
        $this->em->expects($this->once())->method('flush');

        $publishedUpdate = null;
        $this->hub->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedUpdate): string {
                $publishedUpdate = $update;

                return 'mercure-msg-id';
            });

        $result = $this->writer->write(
            category: EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION,
            articleSnapshot: ['title' => 'Test', 'id' => 42],
            originGraphSnapshot: ['press_releases' => [11, 12]],
        );

        $this->assertInstanceOf(EditorialEscalationLog::class, $result);
        $this->assertSame($persistedLog, $result);
        $this->assertSame(EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION, $result->getCategory());
        $this->assertEquals($expiresAt, $result->getExpiresAt());

        $this->assertNotNull($publishedUpdate);
        $this->assertSame(EscalationLogWriter::MERCURE_TOPIC, $publishedUpdate->getTopics()[0]);
        $payload = json_decode($publishedUpdate->getData(), true);
        $this->assertSame('new', $payload['event']);
        $this->assertSame('categ_6', $payload['category']);
        $this->assertSame('CATEGORY_6_CRIMINAL_ACCUSATION', $payload['category_name']);
    }

    public function testMercurePublishFailureDoesNotBreakPersistence(): void
    {
        // Fail-open: even if Mercure is unreachable, the DB row must still land.
        $this->slaCalculator->method('computeExpiresAt')
            ->willReturn(new \DateTimeImmutable('+10 minutes'));

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->hub->method('publish')->willThrowException(new \RuntimeException('hub unreachable'));

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('escalation_mercure_publish_failed', $this->isArray());

        $result = $this->writer->write(
            category: EscalationCategory::FAMILY_B_EU_NATO_RUSSIA,
            articleSnapshot: ['id' => 1],
            originGraphSnapshot: [],
        );

        $this->assertInstanceOf(EditorialEscalationLog::class, $result);
    }

    public function testProvidedNowTimestampIsRespected(): void
    {
        $now = new \DateTimeImmutable('2026-04-20T12:00:00+00:00');
        $this->slaCalculator->method('computeExpiresAt')->willReturn(
            $now->modify('+600 seconds'),
        );

        $result = $this->writer->write(
            category: EscalationCategory::CATEGORY_1_NUCLEAR_WAR,
            articleSnapshot: [],
            originGraphSnapshot: [],
            now: $now,
        );

        $this->assertEquals($now, $result->getCreatedAt());
    }

    public function testLoggerReceivesStructuredEscalationLoggedEntry(): void
    {
        $this->slaCalculator->method('computeExpiresAt')
            ->willReturn(new \DateTimeImmutable('+10 minutes'));

        $loggedContext = null;
        $this->logger->expects($this->once())
            ->method('info')
            ->with('escalation_logged', $this->callback(function (array $ctx) use (&$loggedContext): bool {
                $loggedContext = $ctx;

                return true;
            }));

        $this->writer->write(
            category: EscalationCategory::FAMILY_C_TRANSNISTRIA_GAGAUZIA,
            articleSnapshot: ['id' => 1],
            originGraphSnapshot: [],
        );

        $this->assertNotNull($loggedContext);
        $this->assertSame('family_c', $loggedContext['category']);
        $this->assertSame('FAMILY_C_TRANSNISTRIA_GAGAUZIA', $loggedContext['category_name']);
    }
}
