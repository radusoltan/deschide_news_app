<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\SluggableTransliteratorSubscriber;
use App\Service\RomanianSlugger;
use Gedmo\Sluggable\SluggableListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SluggableTransliteratorSubscriberTest extends TestCase
{
    private SluggableListener $sluggableListener;
    private SluggableTransliteratorSubscriber $subscriber;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->sluggableListener = $this->createMock(SluggableListener::class);
        $this->subscriber = new SluggableTransliteratorSubscriber($this->sluggableListener);
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = SluggableTransliteratorSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertSame(['onKernelRequest', 1024], $events[KernelEvents::REQUEST]);
    }

    public function testSetsTransliteratorOnMainRequest(): void
    {
        $request = Request::create('/api/articles');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->sluggableListener->expects($this->once())
            ->method('setTransliterator')
            ->with([RomanianSlugger::class, 'slugifyStatic']);

        $this->subscriber->onKernelRequest($event);
    }

    public function testSkipsSubRequests(): void
    {
        $request = Request::create('/api/articles');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->sluggableListener->expects($this->never())
            ->method('setTransliterator');

        $this->subscriber->onKernelRequest($event);
    }
}
