<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\AdminEscalationController;
use App\Entity\Editorial\EditorialEscalationLog;
use App\Enum\Editorial\EscalationCategory;
use App\Message\Editorial\WriteFlashMessage;
use App\Repository\Editorial\EditorialEscalationLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Targeted unit test for {@see AdminEscalationController::dispatchFromSnapshot()}
 * covering the T56.05 B-H4 fix.
 *
 * A pure-unit test (reflection-driven invocation of the private method) is the
 * right level here: the behaviour under test is a single `MessageBus::dispatch`
 * call carrying the new `approvedEscalationLogId` field. The existing
 * {@see \App\Tests\Functional\Controller\AdminEscalationControllerTest} covers
 * the HTTP envelope; spinning up an in-memory Messenger transport just to
 * assert one constructor argument would be disproportionate.
 */
class AdminEscalationControllerDispatchTest extends TestCase
{
    public function testDispatchFromSnapshotCarriesApprovedEscalationLogId(): void
    {
        /** @var list<object> $dispatched */
        $dispatched = [];

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            });

        $controller = new AdminEscalationController(
            $this->createMock(EditorialEscalationLogRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $messageBus,
            $this->createMock(HubInterface::class),
            $this->createMock(RateLimiterFactoryInterface::class),
            $this->createMock(RateLimiterFactoryInterface::class),
            $this->createMock(LoggerInterface::class),
        );

        $log = new EditorialEscalationLog(
            articleSnapshot: [
                'primary_signal_id' => 1234,
                'supporting_signal_ids' => [1235, 1236],
                'verdict_type' => 'full_flash',
                'topic_id' => 7,
            ],
            category: EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION,
            originGraphSnapshot: [],
        );
        // Seed an id on the log so we can assert the approvedEscalationLogId
        // propagation. In production Doctrine assigns the id at flush; in unit
        // tests reflection is the shortest path to a stable known value.
        $idProp = new \ReflectionProperty(EditorialEscalationLog::class, 'id');
        $idProp->setValue($log, 9876);

        $method = new \ReflectionMethod(AdminEscalationController::class, 'dispatchFromSnapshot');
        /** @var array{status: string} $result */
        $result = $method->invoke($controller, $log);

        $this->assertSame('dispatched', $result['status']);
        $this->assertCount(1, $dispatched);
        $this->assertInstanceOf(WriteFlashMessage::class, $dispatched[0]);
        /** @var WriteFlashMessage $msg */
        $msg = $dispatched[0];

        // Existing fields preserved.
        $this->assertSame(1234, $msg->primarySignalId);
        $this->assertSame([1235, 1236], $msg->supportingSignalIds);
        $this->assertSame('full_flash', $msg->verdictType);
        $this->assertSame(7, $msg->topicId);

        // T56.05 core assertion: the log id rides on the dispatched message
        // so the handler knows to skip GuardPipeline.
        $this->assertSame(9876, $msg->approvedEscalationLogId);
    }

    public function testDispatchFromSnapshotSkipsWhenPrimarySignalMissing(): void
    {
        // Defensive path predating T56.05: if the snapshot lacks
        // primary_signal_id we return a warning without dispatching.
        // Regression guard — the new approvedEscalationLogId field must NOT
        // change this contract.
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $controller = new AdminEscalationController(
            $this->createMock(EditorialEscalationLogRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $messageBus,
            $this->createMock(HubInterface::class),
            $this->createMock(RateLimiterFactoryInterface::class),
            $this->createMock(RateLimiterFactoryInterface::class),
            $this->createMock(LoggerInterface::class),
        );

        $log = new EditorialEscalationLog(
            articleSnapshot: ['title' => 'no primary signal id'],
            category: EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION,
            originGraphSnapshot: [],
        );

        $method = new \ReflectionMethod(AdminEscalationController::class, 'dispatchFromSnapshot');
        /** @var array{status: string} $result */
        $result = $method->invoke($controller, $log);

        $this->assertSame('missing_signal_id', $result['status']);
    }
}
