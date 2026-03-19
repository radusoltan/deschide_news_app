<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\HealthCheckService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/health', name: 'health_')]
class HealthController extends AbstractController
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService
    ) {
    }

    /**
     * Full health check - all services.
     */
    #[Route('', name: 'check_all', methods: ['GET'])]
    public function checkAll(): JsonResponse
    {
        $result = $this->healthCheckService->checkAll();

        $statusCode = ('healthy' === $result['status']) ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return $this->json($result, $statusCode);
    }

    /**
     * Database health check - PostgreSQL connection.
     */
    #[Route('/database', name: 'check_database', methods: ['GET'])]
    public function checkDatabase(): JsonResponse
    {
        $result = $this->healthCheckService->checkDatabase();

        $statusCode = ('healthy' === $result['status']) ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return $this->json([
            'status' => $result['status'],
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'duration_ms' => $result['duration_ms'],
            'check' => $result,
        ], $statusCode);
    }

    /**
     * Redis health check - cache connection.
     */
    #[Route('/redis', name: 'check_redis', methods: ['GET'])]
    public function checkRedis(): JsonResponse
    {
        $result = $this->healthCheckService->checkRedis();

        $statusCode = ('healthy' === $result['status']) ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return $this->json([
            'status' => $result['status'],
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'duration_ms' => $result['duration_ms'],
            'check' => $result,
        ], $statusCode);
    }

    /**
     * Elasticsearch health check - search cluster.
     */
    #[Route('/elasticsearch', name: 'check_elasticsearch', methods: ['GET'])]
    public function checkElasticsearch(): JsonResponse
    {
        $result = $this->healthCheckService->checkElasticsearch();

        $statusCode = ('healthy' === $result['status']) ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return $this->json([
            'status' => $result['status'],
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'duration_ms' => $result['duration_ms'],
            'check' => $result,
        ], $statusCode);
    }

    /**
     * Kubernetes liveness probe - is application alive?
     */
    #[Route('/live', name: 'liveness', methods: ['GET'])]
    public function liveness(): JsonResponse
    {
        $isAlive = $this->healthCheckService->checkLiveness();

        return $this->json([
            'status' => $isAlive ? 'alive' : 'dead',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $isAlive ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * Kubernetes readiness probe - is application ready to serve traffic?
     */
    #[Route('/ready', name: 'readiness', methods: ['GET'])]
    public function readiness(): JsonResponse
    {
        $isReady = $this->healthCheckService->checkReadiness();

        return $this->json([
            'status' => $isReady ? 'ready' : 'not_ready',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $isReady ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
