<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * LLM model tier used by per-agent AppSettings (`agent.{id}.model_tier`).
 *
 * Tiers are transport-agnostic symbolic names; the concrete model string is
 * resolved via {@see self::toModelString()}. Sprint 54 introduces four tiers:
 *
 * - HAIKU        — Claude Haiku 4.5 (cheap, fast, default for extraction/gating)
 * - SONNET       — Claude Sonnet 4.6 (narrative synthesis, conflict reasoning)
 * - OPUS         — Claude Opus 4.7 (reserved; not invoked in S54, kept for future)
 * - GEMINI_FLASH — Gemini 2.5 Flash (fallback transport for bulk work)
 *
 * Per ADR-020 D5, fallback wiring at the service layer is Tier A/B:
 * Haiku/Sonnet calls may fall back to GEMINI_FLASH on exhaustion; Opus has
 * no fallback.
 */
enum LlmModelTier: string
{
    case HAIKU = 'haiku';
    case SONNET = 'sonnet';
    case OPUS = 'opus';
    case GEMINI_FLASH = 'gemini_flash';

    public function toModelString(): string
    {
        return match ($this) {
            self::HAIKU => 'claude-haiku-4-5-20251001',
            self::SONNET => 'claude-sonnet-4-6',
            self::OPUS => 'claude-opus-4-7',
            self::GEMINI_FLASH => 'gemini-2.5-flash',
        };
    }

    /**
     * Transport family used by the tier ('claude_cli' or 'gemini_cli').
     * Used by downstream logging to distinguish CLI subprocess paths.
     */
    public function transport(): string
    {
        return match ($this) {
            self::HAIKU, self::SONNET, self::OPUS => 'claude_cli',
            self::GEMINI_FLASH => 'gemini_cli',
        };
    }
}
