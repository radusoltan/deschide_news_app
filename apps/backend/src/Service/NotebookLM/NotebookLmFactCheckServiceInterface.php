<?php

declare(strict_types=1);

namespace App\Service\NotebookLM;

use App\Dto\NotebookLM\FactCheckResult;
use App\Entity\Article;
use App\Entity\Topic;

/**
 * Contract for fact-checking an article's claims against a Topic's
 * NotebookLM notebook. Exposed as an interface so consumers (e.g.
 * ArticleFactCheckController) can be stubbed at the seam in functional tests
 * while the concrete implementation stays `final`.
 */
interface NotebookLmFactCheckServiceInterface
{
    /**
     * Run a fact-check query for the given article against the Topic's notebook.
     * Returns null when the topic has no notebook assigned or NotebookLM fails
     * to produce an answer. Results are cached per (topicId, question) hash.
     */
    public function factCheck(Article $article, Topic $topic, ?string $question = null): ?FactCheckResult;

    /**
     * Fact-check a single raw claim (not a full Article) against a Topic's
     * NotebookLM notebook (Sprint 55 T55.10). Called by the verification
     * pipeline gate where no Article exists yet — the claim text is derived
     * from the primary {@see \App\Entity\Editorial\SourceSignal}.
     *
     * Contract mirrors {@see self::factCheck}:
     *  - Returns null when `notebooklm.factcheck.enabled=false`, when the
     *    topic has no notebook, when the CLI subprocess times out, or when
     *    the answer can't be produced.
     *  - Results cached per (topicId, question) with the same TTL as
     *    factCheck, but under a distinct cache-key prefix so claim-level
     *    checks do not poison article-level checks.
     *
     * @param string      $claimText the raw claim statement (~1-3 sentences)
     * @param Topic       $topic     the topic whose notebook holds the baseline sources
     * @param string|null $question  override; otherwise a default RO question is synthesised
     */
    public function factCheckClaim(string $claimText, Topic $topic, ?string $question = null): ?FactCheckResult;

    /**
     * Reports whether fact-checking is currently usable for this Topic
     * (feature flag on, NotebookLM reachable, topic has notebookLmId).
     */
    public function isAvailableForTopic(Topic $topic): bool;
}
