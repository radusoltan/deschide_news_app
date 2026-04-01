<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\LiveText;
use App\Enum\LiveTextStatus;
use App\Service\LiveTextNotificationService;
use App\Service\SocialMediaService;
use App\State\LiveTextProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LiveTextProcessorTest extends TestCase
{
    private LiveTextProcessor $processor;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private LiveTextNotificationService $notificationService;
    private SocialMediaService $socialMediaService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->notificationService = $this->createMock(LiveTextNotificationService::class);
        $this->socialMediaService = $this->createMock(SocialMediaService::class);

        $this->processor = new LiveTextProcessor(
            $this->entityManager,
            $this->requestStack,
            $this->notificationService,
            $this->socialMediaService
        );
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesLiveText(): void
    {
        $this->setupRequest('ro');

        $liveText = $this->createStub(LiveText::class);

        $this->entityManager->expects($this->once())->method('remove')->with($liveText);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Delete();
        $result = $this->processor->process($liveText, $operation);

        $this->assertNull($result);
    }

    // ========================
    // CREATE Operation Tests
    // ========================

    #[Test]
    public function itCreatesNewLiveText(): void
    {
        $this->setupRequest('ro');

        $liveText = new LiveText();
        $liveText->setTitle('Live Event');
        $liveText->setStatus(LiveTextStatus::DRAFT);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Post();
        $result = $this->processor->process($liveText, $operation);

        $this->assertInstanceOf(LiveText::class, $result);
        $this->assertEquals(LiveTextStatus::DRAFT, $result->getStatus());
    }

    #[Test]
    public function itSetsDefaultStatusToDraft(): void
    {
        $this->setupRequest('ro');

        $liveText = new LiveText();
        $liveText->setTitle('Live Event');
        // Status not set; test should verify default

        $operation = new Post();
        $result = $this->processor->process($liveText, $operation);

        $this->assertEquals(LiveTextStatus::DRAFT, $result->getStatus());
    }

    #[Test]
    public function itValidatesEndTimeAfterStartTime(): void
    {
        $this->setupRequest('ro');

        $liveText = new LiveText();
        $liveText->setTitle('Live Event');
        $liveText->setStatus(LiveTextStatus::DRAFT);
        $liveText->setStartTime(new \DateTime('2026-03-26 15:00:00'));
        $liveText->setEndTime(new \DateTime('2026-03-26 10:00:00')); // Before start

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('End time must be after start time');

        $operation = new Post();
        $this->processor->process($liveText, $operation);
    }

    // ========================
    // UPDATE Operation Tests
    // ========================

    #[Test]
    public function itUpdatesExistingLiveText(): void
    {
        $this->setupRequest('ro');

        $existingLiveText = $this->createMock(LiveText::class);
        $existingLiveText->method('getId')->willReturn(10);
        $existingLiveText->method('getStatus')->willReturn(LiveTextStatus::DRAFT);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($existingLiveText);

        $this->entityManager->method('getRepository')
            ->with(LiveText::class)
            ->willReturn($repo);

        $data = $this->createStub(LiveText::class);
        $data->method('getTitle')->willReturn('Updated Title');
        $data->method('getDescription')->willReturn('Updated Desc');
        $data->method('getStatus')->willReturn(LiveTextStatus::DRAFT);
        $data->method('getStartTime')->willReturn(null);
        $data->method('getEndTime')->willReturn(null);
        $data->method('getCategory')->willReturn(null);

        $existingLiveText->expects($this->once())->method('setTitle')->with('Updated Title');

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(LiveText::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenUpdatingNonExistentLiveText(): void
    {
        $this->setupRequest('ro');

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')->willReturn($repo);

        $data = $this->createStub(LiveText::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LiveText not found');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 999]);
    }

    #[Test]
    public function itPublishesStatusChangedEvent(): void
    {
        $this->setupRequest('ro');

        $existingLiveText = $this->createMock(LiveText::class);
        $existingLiveText->method('getId')->willReturn(10);
        $existingLiveText->method('getStatus')->willReturn(LiveTextStatus::DRAFT);
        $existingLiveText->method('getTitle')->willReturn('Existing Title');

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($existingLiveText);

        $this->entityManager->method('getRepository')
            ->with(LiveText::class)
            ->willReturn($repo);

        $data = $this->createStub(LiveText::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getDescription')->willReturn(null);
        $data->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $data->method('getStartTime')->willReturn(new \DateTime());
        $data->method('getEndTime')->willReturn(null);
        $data->method('getCategory')->willReturn(null);

        $this->notificationService->expects($this->once())->method('publishEvent');
        $this->socialMediaService->expects($this->once())->method('postLiveTextStarted');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

    #[Test]
    public function itPreventsTransitionFromEndedToOtherStatus(): void
    {
        $this->setupRequest('ro');

        $existingLiveText = $this->createMock(LiveText::class);
        $existingLiveText->method('getId')->willReturn(10);
        $existingLiveText->method('getStatus')->willReturn(LiveTextStatus::ENDED);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($existingLiveText);

        $this->entityManager->method('getRepository')
            ->with(LiveText::class)
            ->willReturn($repo);

        $data = $this->createStub(LiveText::class);
        $data->method('getTitle')->willReturn('Title');
        $data->method('getDescription')->willReturn(null);
        $data->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $data->method('getStartTime')->willReturn(null);
        $data->method('getEndTime')->willReturn(null);
        $data->method('getCategory')->willReturn(null);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Cannot change status from ENDED');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

    // ========================
    // Non-LiveText Data Tests
    // ========================

    #[Test]
    public function itReturnsNullForNonLiveTextData(): void
    {
        $this->setupRequest('ro');

        $operation = new Post();
        $result = $this->processor->process('not-a-livetext', $operation);

        $this->assertNull($result);
    }

    // ========================
    // Helper Methods
    // ========================

    private function setupRequest(string $locale): void
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => $locale]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
    }
}
