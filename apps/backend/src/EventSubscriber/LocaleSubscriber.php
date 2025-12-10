<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the Gedmo Translatable locale based on the Accept-Language header.
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    private TranslatableListener $translatableListener;

    public function __construct(TranslatableListener $translatableListener)
    {
        $this->translatableListener = $translatableListener;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]], // High priority to run early
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Get locale from Accept-Language header or default to 'ro'
        $locale = $request->headers->get('Accept-Language', 'ro');

        // Extract just the language code (e.g., 'en' from 'en-US' or 'en,ro;q=0.9')
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        // Trim and validate
        $locale = trim($locale);

        // Only allow supported locales
        $supportedLocales = ['ro', 'en', 'ru'];
        if (!\in_array($locale, $supportedLocales, true)) {
            $locale = 'ro'; // fallback to default
        }

        // Set the translatable locale globally for this request
        $this->translatableListener->setTranslatableLocale($locale);

        // IMPORTANT: Also set the locale on the Request object
        // This is used by CategoryProvider and other services
        $request->setLocale($locale);
    }
}
