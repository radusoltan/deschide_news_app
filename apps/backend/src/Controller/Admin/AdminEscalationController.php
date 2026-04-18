<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Editorial\EditorialEscalationLog;
use App\Entity\User;
use App\Enum\Editorial\EscalationCategory;
use App\Enum\EscalationDecision;
use App\Message\Editorial\WriteFlashMessage;
use App\Repository\Editorial\EditorialEscalationLogRepository;
use App\Service\Editorial\Escalation\EscalationLogWriter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin REST API for the editorial escalations queue (Sprint 55 T55.12).
 *
 * Endpoints:
 *   GET  /api/admin/escalations              — paginated list (status + category filters)
 *   GET  /api/admin/escalations/stats        — pending counts, overall + per-category
 *   POST /api/admin/escalations/{id}/approve — decision=APPROVED + optional publish-as-Article
 *   POST /api/admin/escalations/{id}/reject  — decision=REJECTED with reason
 *   POST /api/admin/escalations/{id}/extend-sla — push expires_at forward (max 3600s per call, 5/h per editor)
 *
 * Response envelope always:
 *   { success: bool, status: string, error?: string, data?: mixed, violations?: list<...> }
 *
 * Authorization: ROLE_EDITOR (not ROLE_ADMIN — editors must action their own queue).
 * Rate limiting: `escalation` (100/h/editor) for list+approve+reject+stats;
 *                `escalation_extend` (5/h/editor) for extend-sla — audit D8 ceiling.
 */
#[Route('/api/admin/escalations')]
#[IsGranted('ROLE_EDITOR')]
class AdminEscalationController extends AbstractController
{
    public const MAX_EXTEND_SECONDS = 3600;
    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE = 100;
    public const REASON_MIN_LENGTH = 5;
    public const REASON_MAX_LENGTH = 500;

    public function __construct(
        private readonly EditorialEscalationLogRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
        private readonly HubInterface $mercureHub,
        private readonly RateLimiterFactoryInterface $escalationLimiter,
        private readonly RateLimiterFactoryInterface $escalationExtendLimiter,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('', name: 'admin_escalations_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (($rateResponse = $this->enforceRate($request, $this->escalationLimiter)) !== null) {
            return $rateResponse;
        }

        $status = (string) $request->query->get('status', 'pending');
        $categoryRaw = $request->query->get('category');
        $page = max(1, (int) $request->query->get('page', '1'));
        $limit = min(self::MAX_PAGE_SIZE, max(1, (int) $request->query->get('limit', (string) self::DEFAULT_PAGE_SIZE)));
        $offset = ($page - 1) * $limit;

        if ($categoryRaw !== null && $categoryRaw !== '') {
            $category = EscalationCategory::tryFrom((string) $categoryRaw);
            if ($category === null) {
                return $this->envelope(false, 'validation', 'Unknown category code.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                    'violations' => [['field' => 'category', 'message' => 'Not a valid EscalationCategory value.']],
                ]);
            }
            $rows = $this->repository->findByCategory($category, $limit, $offset);
        } elseif ($status === 'pending') {
            $rows = $this->repository->findPending($limit, $offset);
        } else {
            $rows = $this->repository->findByStatus($status, $limit, $offset);
        }

        return $this->envelope(true, 'ok', null, Response::HTTP_OK, [
            'data' => [
                'page' => $page,
                'limit' => $limit,
                'items' => array_map($this->serializeRow(...), $rows),
            ],
        ]);
    }

    #[Route('/stats', name: 'admin_escalations_stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        if (($rateResponse = $this->enforceRate($request, $this->escalationLimiter)) !== null) {
            return $rateResponse;
        }

        $total = $this->repository->countPending();
        $byCategoryCode = $this->repository->countPendingByCategory();

        // Remap short codes to verbose enum names so the frontend can key the
        // UI off a stable constant (admin UI uses the enum name in the URL).
        $byCategory = [];
        foreach ($byCategoryCode as $code => $count) {
            $category = EscalationCategory::tryFrom($code);
            if ($category !== null) {
                $byCategory[$category->name] = $count;
            }
        }

        return $this->envelope(true, 'ok', null, Response::HTTP_OK, [
            'data' => [
                'pending_total' => $total,
                'pending_by_category' => $byCategory,
            ],
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_escalation_approve', methods: ['POST'])]
    public function approve(int $id, Request $request): JsonResponse
    {
        if (($rateResponse = $this->enforceRate($request, $this->escalationLimiter)) !== null) {
            return $rateResponse;
        }

        $log = $this->repository->find($id);
        if ($log === null) {
            return $this->envelope(false, 'not_found', 'Escalation not found.', Response::HTTP_NOT_FOUND);
        }

        if ($log->getDecision() !== null && $log->getDecision() !== EscalationDecision::EXPIRED) {
            return $this->envelope(false, 'already_decided', 'Escalation already has a decision.', Response::HTTP_CONFLICT);
        }

        $payload = $this->parseJsonBody($request);
        $publishAsArticle = (bool) ($payload['publishAsArticle'] ?? false);
        $editorialNotes = isset($payload['editorialNotes']) && \is_string($payload['editorialNotes'])
            ? trim($payload['editorialNotes'])
            : null;

        $user = $this->getCurrentUser();
        $now = new \DateTimeImmutable();

        $log->setDecision(EscalationDecision::APPROVED);
        $log->setDecidedAt($now);
        $log->setDecidedBy($user);

        $this->em->flush();

        $dispatchResult = null;
        if ($publishAsArticle) {
            $dispatchResult = $this->dispatchFromSnapshot($log);
            if ($dispatchResult['status'] !== 'dispatched') {
                // Decision recorded; dispatch-level failure is a WARNING not
                // a blocker. Editor can re-attempt publish manually.
                $this->logger->warning('escalation_approve_publish_blocked', [
                    'log_id' => $id,
                    'reason' => $dispatchResult['status'],
                ]);
            }
        }

        $this->publishMercureDecided($log, 'approved', $editorialNotes);

        $this->logger->info('escalation_approved', [
            'log_id' => $id,
            'decided_by' => $user?->getId(),
            'publish_as_article' => $publishAsArticle,
            'dispatch_status' => $dispatchResult['status'] ?? null,
            'editorial_notes' => $editorialNotes !== null && $editorialNotes !== '',
        ]);

        return $this->envelope(true, 'approved', null, Response::HTTP_OK, [
            'data' => [
                'id' => $log->getId(),
                'decision' => EscalationDecision::APPROVED->value,
                'decided_at' => $log->getDecidedAt()?->format(\DateTimeInterface::ATOM),
                'publish_dispatch' => $dispatchResult,
            ],
        ]);
    }

    #[Route('/{id}/reject', name: 'admin_escalation_reject', methods: ['POST'])]
    public function reject(int $id, Request $request): JsonResponse
    {
        if (($rateResponse = $this->enforceRate($request, $this->escalationLimiter)) !== null) {
            return $rateResponse;
        }

        $log = $this->repository->find($id);
        if ($log === null) {
            return $this->envelope(false, 'not_found', 'Escalation not found.', Response::HTTP_NOT_FOUND);
        }
        if ($log->getDecision() !== null && $log->getDecision() !== EscalationDecision::EXPIRED) {
            return $this->envelope(false, 'already_decided', 'Escalation already has a decision.', Response::HTTP_CONFLICT);
        }

        $payload = $this->parseJsonBody($request);
        $reason = isset($payload['reason']) && \is_string($payload['reason'])
            ? trim($payload['reason'])
            : '';

        $violations = $this->validateReason($reason);
        if ($violations !== []) {
            return $this->envelope(false, 'validation', 'Invalid payload.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'violations' => $violations,
            ]);
        }

        $user = $this->getCurrentUser();
        $now = new \DateTimeImmutable();

        $log->setDecision(EscalationDecision::REJECTED);
        $log->setDecidedAt($now);
        $log->setDecidedBy($user);

        $this->em->flush();

        $this->publishMercureDecided($log, 'rejected', $reason);

        $this->logger->info('escalation_rejected', [
            'log_id' => $id,
            'decided_by' => $user?->getId(),
            'reason_length' => mb_strlen($reason),
        ]);

        return $this->envelope(true, 'rejected', null, Response::HTTP_OK, [
            'data' => [
                'id' => $log->getId(),
                'decision' => EscalationDecision::REJECTED->value,
                'decided_at' => $log->getDecidedAt()?->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }

    #[Route('/{id}/extend-sla', name: 'admin_escalation_extend', methods: ['POST'])]
    public function extendSla(int $id, Request $request): JsonResponse
    {
        if (($rateResponse = $this->enforceRate($request, $this->escalationExtendLimiter)) !== null) {
            return $rateResponse;
        }

        $log = $this->repository->find($id);
        if ($log === null) {
            return $this->envelope(false, 'not_found', 'Escalation not found.', Response::HTTP_NOT_FOUND);
        }
        if ($log->getDecision() !== null && $log->getDecision() !== EscalationDecision::EXPIRED) {
            return $this->envelope(false, 'already_decided', 'Cannot extend SLA on a decided escalation.', Response::HTTP_CONFLICT);
        }

        $payload = $this->parseJsonBody($request);
        $raw = $payload['additionalSeconds'] ?? null;
        if (!\is_int($raw) && !\is_string($raw)) {
            return $this->envelope(false, 'validation', 'additionalSeconds must be an integer.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'violations' => [['field' => 'additionalSeconds', 'message' => 'Must be an integer.']],
            ]);
        }
        $additionalSeconds = (int) $raw;
        if ($additionalSeconds < 1) {
            return $this->envelope(false, 'validation', 'additionalSeconds must be ≥ 1.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'violations' => [['field' => 'additionalSeconds', 'message' => 'Must be at least 1 second.']],
            ]);
        }
        if ($additionalSeconds > self::MAX_EXTEND_SECONDS) {
            return $this->envelope(false, 'validation', 'additionalSeconds exceeds maximum.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'violations' => [['field' => 'additionalSeconds', 'message' => sprintf('Max per extension: %d seconds (1 hour).', self::MAX_EXTEND_SECONDS)]],
            ]);
        }

        $currentExpiry = $log->getExpiresAt() ?? new \DateTimeImmutable();
        $newExpiry = $currentExpiry->modify(sprintf('+%d seconds', $additionalSeconds));
        $log->setExpiresAt($newExpiry);

        // If the row had been auto-EXPIRED, extending SLA re-opens it for review.
        if ($log->getDecision() === EscalationDecision::EXPIRED) {
            $log->setDecision(null);
            $log->setDecidedAt(null);
            $log->setDecidedBy(null);
        }

        $this->em->flush();

        $this->publishMercureExtended($log, $additionalSeconds);

        $this->logger->info('escalation_sla_extended', [
            'log_id' => $id,
            'decided_by' => $this->getCurrentUser()?->getId(),
            'added_seconds' => $additionalSeconds,
            'new_expires_at' => $newExpiry->format(\DateTimeInterface::ATOM),
        ]);

        return $this->envelope(true, 'extended', null, Response::HTTP_OK, [
            'data' => [
                'id' => $log->getId(),
                'expires_at' => $newExpiry->format(\DateTimeInterface::ATOM),
                'added_seconds' => $additionalSeconds,
            ],
        ]);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function enforceRate(Request $request, RateLimiterFactoryInterface $factory): ?JsonResponse
    {
        $user = $this->getCurrentUser();
        $key = $user?->getUserIdentifier() ?? (string) $request->getClientIp();
        $limiter = $factory->create($key);
        if (!$limiter->consume()->isAccepted()) {
            return $this->envelope(false, 'rate_limited', 'Too many requests. Try again later.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        return null;
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonBody(Request $request): array
    {
        $raw = $request->getContent();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array{data?: mixed, violations?: list<array{field: string, message: string}>} $extra
     */
    private function envelope(bool $success, string $status, ?string $error, int $httpCode, array $extra = []): JsonResponse
    {
        $body = ['success' => $success, 'status' => $status];
        if ($error !== null) {
            $body['error'] = $error;
        }
        if (array_key_exists('violations', $extra)) {
            $body['violations'] = $extra['violations'];
        }
        if (array_key_exists('data', $extra)) {
            $body['data'] = $extra['data'];
        }

        return $this->json($body, $httpCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(EditorialEscalationLog $log): array
    {
        return [
            'id' => $log->getId(),
            'category' => $log->getCategory()->value,
            'category_name' => $log->getCategory()->name,
            'article_snapshot' => $log->getArticleSnapshot(),
            'origin_graph_snapshot' => $log->getOriginGraphSnapshot(),
            'decision' => $log->getDecision()?->value,
            'decided_by' => $log->getDecidedBy()?->getId(),
            'decided_at' => $log->getDecidedAt()?->format(\DateTimeInterface::ATOM),
            'created_at' => $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'expires_at' => $log->getExpiresAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function validateReason(string $reason): array
    {
        $violations = [];
        $length = mb_strlen($reason);
        if ($length < self::REASON_MIN_LENGTH) {
            $violations[] = [
                'field' => 'reason',
                'message' => sprintf('Reason must be at least %d characters.', self::REASON_MIN_LENGTH),
            ];
        }
        if ($length > self::REASON_MAX_LENGTH) {
            $violations[] = [
                'field' => 'reason',
                'message' => sprintf('Reason must be at most %d characters.', self::REASON_MAX_LENGTH),
            ];
        }

        return $violations;
    }

    /**
     * Best-effort writer dispatch when the editor chooses publishAsArticle=true.
     * Relies on the escalation row's articleSnapshot carrying `primary_signal_id`
     * + optional `supporting_signal_ids` — populated by the pipeline wiring in T55.9.
     *
     * @return array{status: string, message_id?: int, reason?: string}
     */
    private function dispatchFromSnapshot(EditorialEscalationLog $log): array
    {
        $snapshot = $log->getArticleSnapshot();
        $primaryId = $snapshot['primary_signal_id'] ?? null;
        if (!\is_int($primaryId)) {
            return [
                'status' => 'missing_signal_id',
                'reason' => 'escalation snapshot does not carry primary_signal_id; cannot auto-publish',
            ];
        }

        $supportingRaw = $snapshot['supporting_signal_ids'] ?? [];
        $supporting = [];
        if (\is_array($supportingRaw)) {
            foreach ($supportingRaw as $id) {
                if (\is_int($id)) {
                    $supporting[] = $id;
                }
            }
        }

        $verdictType = (string) ($snapshot['verdict_type'] ?? 'full_flash');

        $this->messageBus->dispatch(new WriteFlashMessage(
            primarySignalId: $primaryId,
            supportingSignalIds: $supporting,
            verdictType: $verdictType,
            topicId: isset($snapshot['topic_id']) && \is_int($snapshot['topic_id']) ? $snapshot['topic_id'] : null,
        ));

        return ['status' => 'dispatched'];
    }

    private function publishMercureDecided(EditorialEscalationLog $log, string $decision, ?string $comment): void
    {
        $this->publishMercureEvent([
            'event' => 'decided',
            'decision' => $decision,
            'id' => $log->getId(),
            'category' => $log->getCategory()->value,
            'category_name' => $log->getCategory()->name,
            'decided_at' => $log->getDecidedAt()?->format(\DateTimeInterface::ATOM),
            'decided_by' => $log->getDecidedBy()?->getId(),
            'comment' => $comment !== null && $comment !== '' ? $comment : null,
        ]);
    }

    private function publishMercureExtended(EditorialEscalationLog $log, int $addedSeconds): void
    {
        $this->publishMercureEvent([
            'event' => 'extended',
            'id' => $log->getId(),
            'category' => $log->getCategory()->value,
            'category_name' => $log->getCategory()->name,
            'expires_at' => $log->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            'added_seconds' => $addedSeconds,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function publishMercureEvent(array $payload): void
    {
        try {
            $this->mercureHub->publish(new Update(
                EscalationLogWriter::MERCURE_TOPIC,
                json_encode($payload, JSON_THROW_ON_ERROR),
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('escalation_admin_mercure_publish_failed', [
                'log_id' => $payload['id'] ?? null,
                'event' => $payload['event'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
