<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class RateLimiterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $apiGeneralLimiter,
        private readonly RateLimiterFactoryInterface $apiLoginLimiter,
        private readonly RateLimiterFactoryInterface $apiWriteLimiter,
        private readonly RateLimiterFactoryInterface $apiImageOperationsLimiter,
        private readonly string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Skip rate limiting in test and dev environments
        if ($this->environment === 'test' || $this->environment === 'dev') {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Skip rate limiting for localhost (CLI commands, internal services)
        $clientIp = $request->getClientIp();
        if ($clientIp === '127.0.0.1' || $clientIp === '::1') {
            return;
        }

        // Skip rate limiting for non-API routes
        if (!str_starts_with($path, '/api')) {
            return;
        }

        $clientIp = $request->getClientIp() ?? 'unknown';
        $method = $request->getMethod();

        // Select appropriate limiter based on endpoint and method
        $limiter = $this->selectLimiter($path, $method, $clientIp);
        $limit = $limiter->consume();

        // Add rate limit headers to request attributes (will be added to response later)
        $headers = [
            'X-RateLimit-Remaining' => $limit->getRemainingTokens(),
            'X-RateLimit-Limit' => $limit->getLimit(),
        ];

        $retryAfter = $limit->getRetryAfter();
        if ($retryAfter !== null) {
            $headers['X-RateLimit-Retry-After'] = $retryAfter->getTimestamp();
        }

        if (!$limit->isAccepted()) {
            $response = new JsonResponse([
                '@context' => '/api/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'Too Many Requests',
                'hydra:description' => 'Rate limit exceeded. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS, $headers);

            $event->setResponse($response);

            return;
        }

        $request->attributes->set('_rate_limit_headers', $headers);
    }

    private function selectLimiter(string $path, string $method, string $clientIp): \Symfony\Component\RateLimiter\LimiterInterface
    {
        // Login endpoint - strictest limits
        if (str_contains($path, '/login') || str_contains($path, '/token')) {
            return $this->apiLoginLimiter->create($clientIp . '_login');
        }

        // Image operations (crop, thumbnails) - higher limits for batch operations
        if (str_contains($path, '/images') && (str_contains($path, '/crop') || str_contains($path, '/thumbnails'))) {
            return $this->apiImageOperationsLimiter->create($clientIp . '_image_ops');
        }

        // Write operations
        if (\in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $this->apiWriteLimiter->create($clientIp . '_write');
        }

        // General API
        return $this->apiGeneralLimiter->create($clientIp . '_general');
    }
}
