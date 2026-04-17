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
     * Reports whether fact-checking is currently usable for this Topic
     * (feature flag on, NotebookLM reachable, topic has notebookLmId).
     */
    public function isAvailableForTopic(Topic $topic): bool;
}
