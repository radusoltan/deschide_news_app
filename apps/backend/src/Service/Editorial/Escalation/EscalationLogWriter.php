<?php

declare(strict_types=1);

namespace App\Service\Editorial\Escalation;

use App\Entity\Editorial\EditorialEscalationLog;
use App\Enum\Editorial\EscalationCategory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Persists a new {@see EditorialEscalationLog} row + publishes a Mercure
 * event on `deschide_news/admin_escalations` so the admin UI queue updates
 * in real time (Sprint 55 T55.8, audit D12).
 *
 * Mercure publish is fail-open — if the hub is unreachable the persistence
 * still commits. The SSE update would be reconstructed on the next client-side
 * poll / page refresh.
 */
class EscalationLogWriter
{
    public const MERCURE_TOPIC = 'deschide_news/admin_escalations';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EscalationSlaCalculator $slaCalculator,
        private readonly HubInterface $mercureHub,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $articleSnapshot
     * @param array<string, mixed> $originGraphSnapshot
     */
    public function write(
        EscalationCategory $category,
        array $articleSnapshot,
        array $originGraphSnapshot,
        ?\DateTimeImmutable $now = null,
    ): EditorialEscalationLog {
        $now ??= new \DateTimeImmutable();

        $log = new EditorialEscalationLog(
            articleSnapshot: $articleSnapshot,
            category: $category,
            originGraphSnapshot: $originGraphSnapshot,
            createdAt: $now,
        );
        $log->setExpiresAt($this->slaCalculator->computeExpiresAt($now));

        $this->em->persist($log);
        $this->em->flush();

        $this->publishMercure($log);

        $this->logger->info('escalation_logged', [
            'log_id' => $log->getId(),
            'category' => $category->value,
            'category_name' => $category->name,
            'expires_at' => $log->getExpiresAt()?->format(\DateTimeInterface::ATOM),
        ]);

        return $log;
    }

    private function publishMercure(EditorialEscalationLog $log): void
    {
        try {
            $payload = json_encode([
                'event' => 'new',
                'id' => $log->getId(),
                'category' => $log->getCategory()->value,
                'category_name' => $log->getCategory()->name,
                'created_at' => $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'expires_at' => $log->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            ], JSON_THROW_ON_ERROR);

            $this->mercureHub->publish(new Update(self::MERCURE_TOPIC, $payload));
        } catch (\Throwable $e) {
            $this->logger->warning('escalation_mercure_publish_failed', [
                'log_id' => $log->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
