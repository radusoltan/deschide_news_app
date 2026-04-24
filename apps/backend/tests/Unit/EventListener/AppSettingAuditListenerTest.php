<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\AppSetting;
use App\Entity\AppSettingAuditLog;
use App\EventListener\AppSettingAuditListener;
use App\Service\Editorial\AppSettingChangeAuditContext;
use App\Service\Editorial\ChangedByResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Unit tests for {@see AppSettingAuditListener} (T57.P3, ADR-024 D5).
 *
 * Covers the four operational flows the listener must handle:
 *   - Scope guard: non-AppSetting entities and non-value field changes are ignored.
 *   - Critical flip with reason: audit row populated, Mercure broadcast with
 *     `is_critical: true` on the canonical topic.
 *   - Critical flip WITHOUT reason (caller-contract violation): warning logged,
 *     row still commits with `reason = null`, Mercure still fires.
 *   - Non-critical flip with reason: reason is an optional attribute — payload
 *     propagates it verbatim with `is_critical: false`.
 *   - Mercure publish failure: fail-open — persist already committed in the
 *     preceding flush, listener logs and swallows the hub exception.
 */
#[AllowMockObjectsWithoutExpectations]
class AppSettingAuditListenerTest extends TestCase
{
    public function testPreUpdateIgnoresNonAppSettingEntities(): void
    {
        $listener = $this->makeListener();

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn(new \stdClass());
        $args->expects($this->never())->method('hasChangedField');

        $listener->preUpdate($args);
    }

    public function testPreUpdateIgnoresWhenValueFieldUnchanged(): void
    {
        $listener = $this->makeListener();

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn(new AppSetting('some.key', 'irrelevant'));
        $args->method('hasChangedField')->with('value')->willReturn(false);
        $args->expects($this->never())->method('getOldValue');

        $listener->preUpdate($args);
    }

    public function testCriticalFlipWithReasonPersistsAuditAndPublishes(): void
    {
        $auditContext = new AppSettingChangeAuditContext();
        $auditContext->setReason('Emergency halt INC-42');

        $changedBy = $this->createStub(ChangedByResolver::class);
        $changedBy->method('resolve')->willReturn('cli:radu');

        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (object $entity) use (&$persisted): bool {
                $persisted = $entity;
                return $entity instanceof AppSettingAuditLog;
            }));
        $em->expects($this->once())->method('flush');

        $publishedUpdate = null;
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) use (&$publishedUpdate): bool {
                $publishedUpdate = $update;
                return true;
            }));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $listener = new AppSettingAuditListener($auditContext, $changedBy, $hub, $logger);

        $this->fireUpdate(
            $listener,
            $em,
            new AppSetting('editorial.emergency_halt', 'true'),
            oldValue: 'false',
            newValue: 'true',
        );

        $this->assertInstanceOf(AppSettingAuditLog::class, $persisted);
        $this->assertSame('editorial.emergency_halt', $persisted->getSettingKey());
        $this->assertSame('false', $persisted->getOldValue());
        $this->assertSame('true', $persisted->getNewValue());
        $this->assertSame('cli:radu', $persisted->getChangedBy());
        $this->assertSame('Emergency halt INC-42', $persisted->getReason());
        $this->assertTrue($persisted->isCritical());

        $this->assertInstanceOf(Update::class, $publishedUpdate);
        $this->assertSame([AppSettingAuditListener::MERCURE_TOPIC], $publishedUpdate->getTopics());
        $payload = json_decode($publishedUpdate->getData(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('editorial.emergency_halt', $payload['key']);
        $this->assertSame('false', $payload['old_value']);
        $this->assertSame('true', $payload['new_value']);
        $this->assertSame('cli:radu', $payload['changed_by']);
        $this->assertSame('Emergency halt INC-42', $payload['reason']);
        $this->assertTrue($payload['is_critical']);
        $this->assertSame('app_setting.updated', $payload['event']);
    }

    public function testCriticalFlipWithoutReasonLogsWarningAndPersistsWithNullReason(): void
    {
        $auditContext = new AppSettingChangeAuditContext(); // no reason set
        $changedBy = $this->createStub(ChangedByResolver::class);
        $changedBy->method('resolve')->willReturn('system');

        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (object $entity) use (&$persisted): bool {
                $persisted = $entity;
                return true;
            }));
        $em->expects($this->once())->method('flush');

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('publish');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'app_setting_critical_update_missing_reason',
                $this->callback(fn (array $ctx): bool => $ctx['key'] === 'editorial.pipeline.enabled'
                    && $ctx['changed_by'] === 'system'
                    && $ctx['new_value'] === 'true'),
            );

        $listener = new AppSettingAuditListener($auditContext, $changedBy, $hub, $logger);

        $this->fireUpdate(
            $listener,
            $em,
            new AppSetting('editorial.pipeline.enabled', 'true'),
            oldValue: 'false',
            newValue: 'true',
        );

        $this->assertInstanceOf(AppSettingAuditLog::class, $persisted);
        $this->assertNull($persisted->getReason());
        $this->assertTrue($persisted->isCritical());
    }

    public function testNonCriticalFlipWithReasonPropagatesReasonAndMarksIsCriticalFalse(): void
    {
        // Edge case: operator volunteers a reason on a non-critical flip.
        // Reason must propagate verbatim; is_critical still false.
        $auditContext = new AppSettingChangeAuditContext();
        $auditContext->setReason('tuning for sprint-end rollout');

        $changedBy = $this->createStub(ChangedByResolver::class);
        $changedBy->method('resolve')->willReturn('user:editor@deschide.md');

        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $em->expects($this->once())->method('flush');

        $publishedUpdate = null;
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedUpdate): string {
                $publishedUpdate = $update;
                return 'ok';
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $listener = new AppSettingAuditListener($auditContext, $changedBy, $hub, $logger);

        $this->fireUpdate(
            $listener,
            $em,
            new AppSetting('article_generation.window_hours', '48'),
            oldValue: '24',
            newValue: '48',
        );

        $this->assertInstanceOf(AppSettingAuditLog::class, $persisted);
        $this->assertSame('tuning for sprint-end rollout', $persisted->getReason());
        $this->assertFalse($persisted->isCritical());

        $payload = json_decode($publishedUpdate->getData(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($payload['is_critical']);
        $this->assertSame('tuning for sprint-end rollout', $payload['reason']);
    }

    public function testPostFlushIsNoOpWhenNoPendingAudits(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('publish');

        $listener = new AppSettingAuditListener(
            new AppSettingChangeAuditContext(),
            $this->createStub(ChangedByResolver::class),
            $hub,
            $this->createStub(LoggerInterface::class),
        );

        $args = $this->createMock(PostFlushEventArgs::class);
        $args->expects($this->never())->method('getObjectManager');

        $listener->postFlush($args);
    }

    public function testMercurePublishFailureDoesNotBubble(): void
    {
        // Fail-open: hub down must not unwind the already-committed audit row flush.
        $auditContext = new AppSettingChangeAuditContext();
        $auditContext->setReason('network incident drill');

        $changedBy = $this->createStub(ChangedByResolver::class);
        $changedBy->method('resolve')->willReturn('cli:radu');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $hub = $this->createMock(HubInterface::class);
        $hub->method('publish')->willThrowException(new \RuntimeException('mercure hub unreachable'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('app_setting_mercure_publish_failed', $this->callback(
                fn (array $ctx): bool => $ctx['key'] === 'editorial.emergency_halt'
                    && str_contains($ctx['error'], 'mercure hub unreachable'),
            ));

        $listener = new AppSettingAuditListener($auditContext, $changedBy, $hub, $logger);

        $this->fireUpdate(
            $listener,
            $em,
            new AppSetting('editorial.emergency_halt', 'true'),
            oldValue: 'false',
            newValue: 'true',
        );
        // Test passes if no exception escaped.
        $this->addToAssertionCount(1);
    }

    /**
     * Drives the listener through the preUpdate → postFlush cycle for a single
     * AppSetting change.
     */
    private function fireUpdate(
        AppSettingAuditListener $listener,
        EntityManagerInterface $em,
        AppSetting $setting,
        ?string $oldValue,
        string $newValue,
    ): void {
        $preArgs = $this->createMock(PreUpdateEventArgs::class);
        $preArgs->method('getObject')->willReturn($setting);
        $preArgs->method('hasChangedField')->with('value')->willReturn(true);
        $preArgs->method('getOldValue')->with('value')->willReturn($oldValue);
        $preArgs->method('getNewValue')->with('value')->willReturn($newValue);

        $listener->preUpdate($preArgs);

        $postArgs = $this->createMock(PostFlushEventArgs::class);
        $postArgs->method('getObjectManager')->willReturn($em);

        $listener->postFlush($postArgs);
    }

    private function makeListener(): AppSettingAuditListener
    {
        return new AppSettingAuditListener(
            new AppSettingChangeAuditContext(),
            $this->createStub(ChangedByResolver::class),
            $this->createStub(HubInterface::class),
            $this->createStub(LoggerInterface::class),
        );
    }
}
