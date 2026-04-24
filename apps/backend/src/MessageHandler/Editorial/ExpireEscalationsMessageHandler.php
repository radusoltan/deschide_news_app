<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Entity\Editorial\EditorialEscalationLog;
use App\Enum\EscalationDecision;
use App\Message\Editorial\ExpireEscalationsMessage;
use App\Repository\AppSettingRepository;
use App\Repository\Editorial\EditorialEscalationLogRepository;
use App\Service\Editorial\Escalation\EscalationLogWriter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Scheduler tick handler (Sprint 55 T55.11). Called every 60s while
 * `editorial.pipeline.enabled=true`: scans for escalations past their
 * `expires_at` SLA deadline and marks them as timed-out.
 *
 * Decision semantics (audit D9 / D12):
 *   - sets `decision = EscalationDecision::EXPIRED` (distinct terminal state,
 *     NOT an editorial approve/reject outcome — row requires senior review)
 *   - sets `decidedAt = now`
 *   - leaves `decidedBy = null` on purpose — there was no human adjudicator
 *   - publishes Mercure `event=expired` on `deschide_news/admin_escalations`
 *     so the admin UI removes the row from the pending queue without
 *     conflating it with human decisions
 *
 * Failure contract matches the other scheduler handlers (Sprint 53+): never
 * rethrow; log + return so the scheduler transport does not retry storm.
 */
#[AsMessageHandler]
final class ExpireEscalationsMessageHandler
{
    public function __construct(
        private readonly EditorialEscalationLogRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly HubInterface $mercureHub,
        private readonly AppSettingRepository $appSettings,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ExpireEscalationsMessage $message): void
    {
        // Double-gate: the scheduler provider already checks pipeline.enabled
        // before emitting the tick, but the flag can flip mid-tick — re-check
        // so a late tick doesn't mutate state after kill-switch.
        if (!$this->appSettings->getBool('editorial.pipeline.enabled', false)) {
            return;
        }

        $now = new \DateTimeImmutable();

        try {
            $expired = $this->repository->findSlaExpired($now);

            if ($expired === []) {
                return;
            }

            foreach ($expired as $log) {
                $log->setDecision(EscalationDecision::EXPIRED);
                $log->setDecidedAt($now);
                // decidedBy stays null — EXPIRED is not a human decision.
            }

            $this->em->flush();

            foreach ($expired as $log) {
                $this->publishExpired($log);
            }

            $this->logger->info('escalation_sla_expired_batch', [
                'count' => \count($expired),
                'ids' => array_map(static fn (EditorialEscalationLog $l): ?int => $l->getId(), $expired),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('ExpireEscalationsMessageHandler: sweep threw', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function publishExpired(EditorialEscalationLog $log): void
    {
        try {
            $payload = json_encode([
                'event' => 'expired',
                'id' => $log->getId(),
                'category' => $log->getCategory()->value,
                'category_name' => $log->getCategory()->name,
                'expires_at' => $log->getExpiresAt()?->format(\DateTimeInterface::ATOM),
                'decided_at' => $log->getDecidedAt()?->format(\DateTimeInterface::ATOM),
            ], JSON_THROW_ON_ERROR);

            $this->mercureHub->publish(new Update(
                EscalationLogWriter::MERCURE_TOPIC,
                $payload,
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('escalation_expired_mercure_publish_failed', [
                'log_id' => $log->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
