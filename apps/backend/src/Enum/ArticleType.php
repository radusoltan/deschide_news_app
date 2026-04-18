<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Classifies Articles emitted by the editorial pipeline (Sprint 55, ADR-020).
 *
 * - FLASH: first-pass short article produced by {@see \App\Service\Editorial\Writer\FlashWriter}
 *   from a verified signal cluster. 80-120 words, single snapshot.
 * - DEVELOPING_STORY: long-lived article that receives in-place revisions as
 *   new verified signals land against the same Topic. Maintains a
 *   `revision_history` trail capped at 100 entries.
 * - LONGFORM: synthesised piece produced by the longform agent when a
 *   developing story stabilises or when an editor promotes it. Seed disabled
 *   in S55; wiring arrives in S56 (agent.longform_synthesizer.enabled).
 * - FULL_FLASH: the terminal state of a flash that was upgraded after the
 *   VerificationGate later returned FULL_FLASH for the same claim cluster.
 */
enum ArticleType: string
{
    case FLASH = 'flash';
    case DEVELOPING_STORY = 'developing_story';
    case LONGFORM = 'longform';
    case FULL_FLASH = 'full_flash';
}
