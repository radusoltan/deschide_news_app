<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\LocaleSubscriber;
use Gedmo\Translatable\TranslatableListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LocaleSubscriberTest extends TestCase
{
    private TranslatableListener $translatableListener;
    private LocaleSubscriber $subscriber;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->translatableListener = $this->createMock(TranslatableListener::class);
        $this->subscriber = new LocaleSubscriber($this->translatableListener);
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = LocaleSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    }

    public function testSetsRomanianLocaleByDefault(): void
    {
        $request = Request::create('/api/articles');
        $request->headers->remove('Accept-Language');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->translatableListener->expects($this->once())
            ->method('setTranslatableLocale')
            ->with('ro');

        $this->subscriber->onKernelRequest($event);
        $this->assertSame('ro', $request->getLocale());
    }

    public function testSetsEnglishLocale(): void
    {
        $request = Request::create('/api/articles');
        $request->headers->set('Accept-Language', 'en');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->translatableListener->expects($this->once())
            ->method('setTranslatableLocale')
            ->with('en');

        $this->subscriber->onKernelRequest($event);
        $this->assertSame('en', $request->getLocale());
    }

    public function testSetsRussianLocale(): void
    {
        $request = Request::create('/api/articles');
        $request->headers->set('Accept-Language', 'ru');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->translatableListener->expects($this->once())
            ->method('setTranslatableLocale')
            ->with('ru');

        $this->subscriber->onKernelRequest($event);
        $this->assertSame('ru', $request->getLocale());
    }

    public function testExtractsLanguageCodeFromDash(): void
    {
        $request = Request::create('/api/articles');
        $request->headers->set('Accept-Language', 'en-US');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->translatableListener->expects($this->once())
            ->method('setTranslatableLocale')
            ->with('en');

        $this->subscriber->onKernelRequest($event);
    }

    public function testExtractsLanguageCodeFromComma(): void
    {
        $request = Request::create('/api/articles');
        $request->headers->set('Accept-Language', 'en,ro;q=0.9');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->translatableListener->expects($this->once())
            ->method('setTranslatableLocale')
            ->with('en');

        $this->subscriber->onKernelRequest($event);
    }

    public function testFallsBackToRoForUnsupportedLocale(): void
    {
        $request = Request::create('/api/articles');
        $request->headers->set('Accept-Language', 'fr');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->translatableListener->expects($this->once())
            ->method('setTranslatableLocale')
            ->with('ro');

        $this->subscriber->onKernelRequest($event);
    }

    public function testSkipsSubRequests(): void
    {
        $request = Request::create('/api/articles');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->translatableListener->expects($this->never())
            ->method('setTranslatableLocale');

        $this->subscriber->onKernelRequest($event);
    }
}
