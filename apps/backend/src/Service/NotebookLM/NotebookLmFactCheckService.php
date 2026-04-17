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

class NotebookLmFactCheckService
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
     */
    public function factCheck(Article $article, Topic $topic, ?string $question = null): ?FactCheckResult
    {
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

        $question ??= $this->buildDefaultQuestion($article);
        $question = mb_substr($question, 0, self::MAX_QUESTION_LENGTH);
        $topicId = $topic->getId() ?? 0;

        $cacheKey = $this->buildCacheKey($topicId, $question);
        $effectiveTtl = $this->settings?->getInt('notebooklm.factcheck.cache_ttl', $this->cacheTtl) ?? $this->cacheTtl;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($notebookId, $question, $topicId, $effectiveTtl): ?FactCheckResult {
            $item->expiresAfter($effectiveTtl);

            $this->logger->info('FactCheck: querying NotebookLM', [
                'topicId' => $topicId,
                'question' => mb_substr($question, 0, 80),
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

    private function buildDefaultQuestion(Article $article): string
    {
        $title = $article->getTitle() ?? 'Untitled';
        $lead = mb_substr($article->getLead() ?? $article->getContent() ?? '', 0, 200);

        return sprintf(
            'Verifică afirmațiile din acest articol și identifică eventuale inexactități sau contradicții cu sursele disponibile: "%s". %s',
            $title,
            $lead,
        );
    }

    private function buildCacheKey(int $topicId, string $question): string
    {
        $questionHash = substr(md5($question), 0, 16);

        return sprintf('factcheck.topic_%d.q_%s', $topicId, $questionHash);
    }
}
