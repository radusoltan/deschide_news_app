<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\MetricsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class HttpMetricsSubscriber implements EventSubscriberInterface
{
    private array $requestStartTimes = [];

    public function __construct(
        private readonly MetricsService $metrics
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1000],
            KernelEvents::RESPONSE => ['onKernelResponse', -1000],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $requestId = spl_object_id($request);
        $this->requestStartTimes[$requestId] = microtime(true);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $requestId = spl_object_id($request);

        if (!isset($this->requestStartTimes[$requestId])) {
            return;
        }

        $duration = microtime(true) - $this->requestStartTimes[$requestId];
        $path = $request->getPathInfo();

        // Skip metrics endpoint to avoid recursion
        if ($path === '/metrics') {
            unset($this->requestStartTimes[$requestId]);

            return;
        }

        // Determine if cached (check for cache hit header)
        $cached = $response->headers->has('X-Cache-Hit');

        // Normalize path to reduce cardinality
        $normalizedPath = $this->normalizePath($path);

        $this->metrics->recordHttpRequestDuration($normalizedPath, $duration, $cached);

        unset($this->requestStartTimes[$requestId]);
    }

    /**
     * Normalize path to reduce cardinality in metrics.
     * Replace IDs with placeholders.
     */
    private function normalizePath(string $path): string
    {
        // Replace numeric IDs with {id}
        $path = preg_replace('/\/\d+/', '/{id}', $path);

        // Replace UUIDs with {uuid}
        $path = preg_replace('/\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '/{uuid}', $path);

        return $path;
    }
}
