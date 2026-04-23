<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AppSetting;
use App\Entity\AppSettingAuditLog;
use App\Service\Editorial\AppSettingChangeAuditContext;
use App\Service\Editorial\ChangedByResolver;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Audits every UPDATE on {@see AppSetting} and broadcasts the change on Mercure
 * (Sprint 57 T57.P3, ADR-024 D5).
 *
 * Scope discipline (Phase 2 refinement):
 *   - ONLY updates are audited. `postPersist` is intentionally NOT bound —
 *     initial seeding of critical keys with known-safe defaults adds zero
 *     operational signal. The implementation uses `preUpdate` + `postFlush`
 *     (codebase precedent: {@see ArticleSlugChangeListener}) rather than a
 *     naive `postUpdate`, because direct `persist()` during `postUpdate`
 *     risks Doctrine UoW re-entry. The chosen two-stage handler keeps the
 *     scope identical (update-only, never creation).
 *
 *   - Every update is audited regardless of criticality. `isCritical` lives
 *     in the `context` JSONB so consumers filter downstream; producing a
 *     complete trail survives any later churn on {@see AppSetting::CRITICAL_KEYS}.
 *
 *   - Mercure broadcast is fail-open ({@see self::publishMercure()}): hub
 *     unreachable → warning logged, the audit row still commits. Subscribers
 *     reconcile on the next page refresh / API pull.
 *
 * Caller contract (read before adding a new write path to AppSetting):
 *   {@see AppSettingChangeAuditContext} is the ONLY carrier for the operator
 *   `--reason` on critical flips. Callers must populate it before invoking
 *   {@see \App\Repository\AppSettingRepository::set()} and wrap the call in
 *   `try { … } finally { $context->clear(); }`. A critical update reaching
 *   `postFlush` with `getReason() === null` is a contract violation — we log
 *   a warning but STILL persist the audit row with `reason = null` so the
 *   forensic trail stays intact. Non-critical updates may legitimately
 *   propagate a null reason.
 */
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class AppSettingAuditListener
{
    public const MERCURE_TOPIC = 'deschide_news/app_settings';

    /**
     * Buffered change snapshots captured during preUpdate, drained in postFlush.
     *
     * @var list<array{key: string, oldValue: ?string, newValue: string}>
     */
    private array $pendingAudits = [];

    public function __construct(
        private readonly AppSettingChangeAuditContext $auditContext,
        private readonly ChangedByResolver $changedByResolver,
        private readonly HubInterface $mercureHub,
        private readonly LoggerInterface $logger,
    ) {}

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $setting = $args->getObject();
        if (!$setting instanceof AppSetting) {
            return;
        }

        // Only the `value` column carries auditable signal — timestamp updates
        // are a derived effect and offer no information beyond the value change.
        if (!$args->hasChangedField('value')) {
            return;
        }

        /** @var string|null $oldValue */
        $oldValue = $args->getOldValue('value');
        /** @var string $newValue */
        $newValue = $args->getNewValue('value');

        $this->pendingAudits[] = [
            'key' => $setting->getKey(),
            'oldValue' => $oldValue,
            'newValue' => $newValue,
        ];
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pendingAudits === []) {
            return;
        }

        $em = $args->getObjectManager();
        $reason = $this->auditContext->getReason();
        $changedBy = $this->changedByResolver->resolve();
        $now = new \DateTimeImmutable();

        // Drain buffer BEFORE persist to avoid re-entry loops if the audit-log
        // flush itself triggers other listeners.
        $batch = $this->pendingAudits;
        $this->pendingAudits = [];

        $persistedLogs = [];
        foreach ($batch as $change) {
            $isCritical = AppSetting::isCriticalKey($change['key']);

            if ($isCritical && $reason === null) {
                $this->logger->warning('app_setting_critical_update_missing_reason', [
                    'key' => $change['key'],
                    'changed_by' => $changedBy,
                    'new_value' => $change['newValue'],
                    'note' => 'Caller bypassed AppSettingChangeAuditContext — '
                        . 'audit row persists with reason=null. See '
                        . 'AppSettingAuditListener docblock for the contract.',
                ]);
            }

            $context = [
                'isCritical' => $isCritical,
                'sapi' => \PHP_SAPI,
                'hostname' => gethostname() ?: null,
            ];

            $log = new AppSettingAuditLog(
                settingKey: $change['key'],
                oldValue: $change['oldValue'],
                newValue: $change['newValue'],
                changedBy: $changedBy,
                context: $context,
                reason: $reason,
                changedAt: $now,
            );

            $em->persist($log);
            $persistedLogs[] = [$log, $change];
        }

        // Single follow-up flush commits ALL audit rows for this transaction
        // together. Guard against no-op re-entry (hasPendingInsertions would
        // still be true from the audit persists; we rely on that).
        $em->flush();

        foreach ($persistedLogs as [$log, $change]) {
            $this->publishMercure($log, $change);
        }
    }

    /**
     * @param array{key: string, oldValue: ?string, newValue: string} $change
     */
    private function publishMercure(AppSettingAuditLog $log, array $change): void
    {
        try {
            $payload = json_encode([
                'event' => 'app_setting.updated',
                'key' => $change['key'],
                'old_value' => $change['oldValue'],
                'new_value' => $change['newValue'],
                'changed_by' => $log->getChangedBy(),
                'changed_at' => $log->getChangedAt()->format(\DateTimeInterface::ATOM),
                'is_critical' => $log->isCritical(),
                'reason' => $log->getReason(),
            ], \JSON_THROW_ON_ERROR);

            $this->mercureHub->publish(new Update(self::MERCURE_TOPIC, $payload));
        } catch (\Throwable $e) {
            $this->logger->warning('app_setting_mercure_publish_failed', [
                'key' => $change['key'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
