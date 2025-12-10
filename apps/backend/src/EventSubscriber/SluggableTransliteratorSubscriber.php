<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\RomanianSlugger;
use Gedmo\Sluggable\SluggableListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Configures the Gedmo Sluggable listener to use Romanian-aware transliteration.
 *
 * This subscriber sets up the sluggable listener to properly handle Romanian characters
 * (Ș/ș, Ț/ț, Ă/ă, Â/â, Î/î) when generating slugs from entity fields.
 */
class SluggableTransliteratorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SluggableListener $sluggableListener,
    ) {
    }

    /**
     * Configure the sluggable listener with Romanian transliterator
     * This runs on the first kernel request to ensure the listener is properly configured.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        // Only execute once on the main request
        if (!$event->isMainRequest()) {
            return;
        }

        // Set the custom transliterator for proper Romanian character handling
        $this->sluggableListener->setTransliterator([RomanianSlugger::class, 'slugifyStatic']);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run early to configure the listener before any entity operations
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
        ];
    }
}
