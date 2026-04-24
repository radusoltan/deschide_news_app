<?php

declare(strict_types=1);

namespace App\Message\Editorial;

/**
 * Dispatched by {@see \App\MessageHandler\Editorial\VerifyClaimMessageHandler}
 * (Sprint 55 T55.9) when a verified signal cluster earns a publishable
 * verdict and no active developing-story Article already covers the topic.
 *
 * Handled by {@see \App\MessageHandler\Editorial\WriteFlashMessageHandler}
 * running on the `editorial_flash` transport (Sprint 55 T55.3, ADR-020 D5).
 *
 * @internal
 */
final readonly class WriteFlashMessage
{
    /**
     * @param int       $primarySignalId         `source_signals.id` of the lead signal in the cluster (first row of the ordered array)
     * @param list<int> $supportingSignalIds     Other `source_signals.id` rows in the cluster — may be empty for single-source flashes
     * @param string    $verdictType             {@see \App\Enum\Editorial\VerdictType}->value ('full_flash', 'flash_with_attribution', 'flash_with_assertion_yellow')
     * @param int|null  $topicId                 Optional Topic association when the verification gate already resolved one; null otherwise
     * @param int|null  $approvedEscalationLogId Sprint 56 T56.05 editor override: when set, the handler skips {@see \App\Service\Editorial\Guard\GuardPipelineInterface::check()} and
     *                                           publishes the Article directly. Carries the {@see \App\Entity\Editorial\EditorialEscalationLog}.id that the editor approved, for audit trail.
     *                                           Only `AdminEscalationController::approve()` sets this; pipeline-emitted messages leave it null (so the guard chain still runs on normal traffic).
     */
    public function __construct(
        public int $primarySignalId,
        public array $supportingSignalIds,
        public string $verdictType,
        public ?int $topicId = null,
        public ?int $approvedEscalationLogId = null,
    ) {}
}
