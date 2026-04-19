<?php

declare(strict_types=1);

namespace App\Service\NotebookLM;

use App\Dto\NotebookLM\FactCheckResult;
use App\Entity\Article;
use App\Entity\Topic;
use App\Repository\AppSettingRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class NotebookLmFactCheckService implements NotebookLmFactCheckServiceInterface
{
    private const DEFAULT_CACHE_TTL = 3600;
    private const MAX_QUESTION_LENGTH = 500;

    public function __construct(
        private readonly NotebookLMService $notebookLM,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly int $cacheTtl = self::DEFAULT_CACHE_TTL,
        private readonly ?AppSettingRepository $settings = null,
    ) {}

    /**
     * Fact-check article claims against topic's NotebookLM notebook.
     *
     * Delegates to {@see self::factCheckClaim} with a title+lead composition
     * as the claim text — single code path for both call sites (admin article
     * review + L2 verification-pipeline claim check).
     */
    public function factCheck(Article $article, Topic $topic, ?string $question = null): ?FactCheckResult
    {
        $claimText = sprintf(
            '%s. %s',
            $article->getTitle() ?? 'Untitled',
            mb_substr($article->getLead() ?? $article->getContent() ?? '', 0, 200),
        );

        return $this->factCheckClaim($claimText, $topic, $question);
    }

    /**
     * Sprint 55 T55.10 — claim-level fact-check, no Article required.
     *
     * Cache key prefix is `factcheck.claim.*` so claim-level answers never
     * poison the `factcheck.topic_*` space used by the Article path.
     */
    public function factCheckClaim(string $claimText, Topic $topic, ?string $question = null): ?FactCheckResult
    {
        // Hard gate: if the feature flag is off we return null BEFORE any
        // subprocess work — zero NotebookLM CLI cost when disabled. Matches
        // audit hard-rule "no cost for disabled services".
        if ($this->settings !== null && !$this->settings->getBool('notebooklm.factcheck.enabled', false)) {
            $this->logger->debug('FactCheck: disabled via AppSettings (notebooklm.factcheck.enabled=false)');

            return null;
        }

        $notebookId = $this->notebookLM->resolveNotebookId($topic);
        if ($notebookId === null) {
            $this->logger->debug('FactCheck: topic has no notebook', [
                'topicId' => $topic->getId(),
            ]);

            return null;
        }

        $trimmedClaim = trim($claimText);
        if ($trimmedClaim === '') {
            $this->logger->debug('FactCheck: empty claimText, skipping');

            return null;
        }

        $question ??= $this->buildDefaultClaimQuestion($trimmedClaim);
        $question = mb_substr($question, 0, self::MAX_QUESTION_LENGTH);
        $topicId = $topic->getId() ?? 0;

        $cacheKey = $this->buildClaimCacheKey($trimmedClaim, $topicId);
        $effectiveTtl = $this->settings?->getInt('notebooklm.factcheck.cache_ttl', $this->cacheTtl) ?? $this->cacheTtl;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($notebookId, $question, $topicId, $effectiveTtl): ?FactCheckResult {
            $item->expiresAfter($effectiveTtl);

            $this->logger->info('FactCheck: querying NotebookLM', [
                'topicId' => $topicId,
                'question' => mb_substr($question, 0, 80),
                'variant' => 'claim',
            ]);

            $answer = $this->notebookLM->ask($notebookId, $question);
            if ($answer === null) {
                // Don't cache failures — let next request retry
                $item->expiresAfter(0);

                return null;
            }

            return new FactCheckResult(
                answer: $answer,
                question: $question,
                topicId: $topicId,
                notebookId: $notebookId,
                cached: false,
                checkedAt: new \DateTimeImmutable(),
            );
        });
    }

    /**
     * Check if fact-checking is available for a given topic.
     */
    public function isAvailableForTopic(Topic $topic): bool
    {
        if ($this->settings !== null && !$this->settings->getBool('notebooklm.factcheck.enabled', false)) {
            return false;
        }

        return $this->notebookLM->isAvailable()
            && $topic->getNotebookLmId() !== null;
    }

    private function buildDefaultClaimQuestion(string $claimText): string
    {
        $excerpt = mb_substr($claimText, 0, 300);

        return sprintf(
            'Verifică următoarea afirmație folosind sursele din notebook. Dacă este contrazisă de surse, spune explicit „contrazice"; dacă este susținută, spune „este susținut". Afirmație: %s',
            $excerpt,
        );
    }

    private function buildClaimCacheKey(string $claimText, int $topicId): string
    {
        $claimHash = substr(md5($claimText), 0, 16);

        return sprintf('factcheck.claim.topic_%d.c_%s', $topicId, $claimHash);
    }
}
