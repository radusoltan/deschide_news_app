<?php

declare(strict_types=1);

namespace App\Dto\Agent;

use App\Enum\LlmModelTier;

/**
 * Input DTO for {@see \App\Agent\AgentDispatcher::dispatch()}.
 *
 * Carries everything the dispatcher needs to route an agent call:
 *  - `agentId`:     the stable agent identifier (`source_attribution`,
 *                   `signal_aggregator`, `flash_writer`, ...). Used as the
 *                   LlmAgentCallLog key and as the `agent.{id}.*` AppSettings
 *                   namespace prefix.
 *  - `messages`:    the chat message list forwarded verbatim to the Claude
 *                   CLI. Each entry is `{role: 'user'|'assistant', content: string}`.
 *  - `tier`:        caller-resolved LLM tier (ADR-024 decision Q2 — the
 *                   dispatcher is a mechanical pipe, not a smart router).
 *                   Agents resolve via {@see \App\Service\Ai\TierResolver}
 *                   (or, for LegalGuard's Category-6 path, via direct
 *                   AppSettings read) and pass the result explicitly.
 *  - `systemPrompt`: optional system-role prompt. Preserved for Anthropic
 *                   prompt caching (ADR-022 D6).
 *  - `tierVariant`: optional tier variant key. Populated by
 *                   {@see \App\Service\Editorial\Verification\VerificationGate}
 *                   as either `model_tier_simple` or `model_tier_conflict`
 *                   so downstream logging / future analytics can distinguish
 *                   the two call shapes; the dispatcher itself treats the
 *                   field opaquely.
 *
 * Immutable by construction — instances are cheap to create and safe to
 * pass across async boundaries.
 */
final readonly class AgentRequest
{
    /**
     * @param list<array{role: string, content: string}> $messages
     */
    public function __construct(
        public string $agentId,
        public array $messages,
        public LlmModelTier $tier,
        public ?string $systemPrompt = null,
        public ?string $tierVariant = null,
    ) {}
}
