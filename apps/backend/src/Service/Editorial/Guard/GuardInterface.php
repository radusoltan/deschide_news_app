<?php

declare(strict_types=1);

namespace App\Service\Editorial\Guard;

use App\Entity\Article;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contract for a single editorial guard (Sprint 55 T55.6, ADR-020 L4 layer).
 *
 * Implementations are auto-tagged `app.editorial_guard` via the
 * {@see AutoconfigureTag} attribute. The {@see GuardPipeline} consumes the
 * tagged iterator and invokes each guard in service-definition order.
 *
 * Guards are synchronous and MUST not throw on normal LLM-unavailability —
 * return a pass-with-warning part instead so the pipeline keeps flowing. The
 * only exception is LegalGuard under Category 6, which fails-closed with an
 * `escalationCode` per audit D8.
 */
#[AutoconfigureTag('app.editorial_guard')]
interface GuardInterface
{
    /**
     * @param array<string, mixed> $context arbitrary writer-supplied context (verdict type, signal ids, etc.)
     */
    public function validate(Article $article, array $context = []): GuardVerdictPart;
}
