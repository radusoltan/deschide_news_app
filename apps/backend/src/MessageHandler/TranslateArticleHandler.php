<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Message\TranslateArticleMessage;
use App\Repository\ArticleRepository;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\TierResolver;
use App\Service\TranslationResultProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Article translator handler (ADR-024 D1 translation row — T57.P7.C2).
 *
 * Post-migration the translator is the only remaining Gemini-backed agent; it
 * routes through {@see AgentDispatcher} via the Gemini transport branch shipped
 * in T57.P7.C1. This closes the last `LlmAgentCallLog` observability gap in the
 * support layer and brings translator invocations under the uniform
 * `agent.emergency_halt` circuit breaker (ADR-022 D5 generalized by
 * ADR-024 D2).
 *
 * Per-locale isolation is preserved. One `dispatcher->dispatch()` per target
 * locale — semantically correct (fine-grained audit trail) and historically
 * required to keep Gemini CLI output well under 64KB.
 *
 * Halt semantics (keeper pattern #13 — non-fatal per-locale): a halt caught for
 * one locale does not abort the remaining locales. A halted locale is merged
 * into `$failures` per T5 (same operational effect on the article per
 * ADR-024 D1 "skip + manual flag"), with a distinct INFO log line instead of
 * ERROR. The aggregate final status follows T4:
 *   - all locales halted           → status = failed + RuntimeException (clean
 *                                    dead-letter signal for operator-driven
 *                                    halt windows)
 *   - any other 0-success aggregate → status = failed, no rethrow
 *   - at least one success + any fail/halt → status = needs_review
 *   - all successes                → status = completed
 */
#[AsMessageHandler]
final readonly class TranslateArticleHandler
{
    /**
     * ADR-024 D1 agent identifier. Backs `agent.journalistic_translator.*`
     * AppSettings namespace and `LlmAgentCallLog.agent_name` rows.
     */
    public const AGENT_ID = 'journalistic_translator';

    private const AGENT_FILE = '.gemini/agents/journalistic-translator.md';

    public function __construct(
        private ArticleRepository $articleRepository,
        private TranslationResultProcessor $resultProcessor,
        private EntityManagerInterface $em,
        private MessageBusInterface $messageBus,
        private AgentDispatcher $dispatcher,
        private TierResolver $tierResolver,
        private LoggerInterface $logger,
        private string $projectDir,
    ) {
    }

    public function __invoke(TranslateArticleMessage $message): void
    {
        $article = $this->articleRepository->find($message->articleId);

        if ($article === null) {
            $this->logger->warning('TranslateArticleHandler: article not found', [
                'articleId' => $message->articleId,
            ]);

            return;
        }

        // Verify Romanian content exists (default locale loaded by Gedmo)
        $title = $article->getTitle();
        $content = $article->getContent();

        if (empty($title) || empty($content)) {
            $this->logger->warning('TranslateArticleHandler: no RO content', [
                'articleId' => $message->articleId,
            ]);

            return;
        }

        $article->setTranslationStatus('in_progress');
        $this->em->flush();

        // Safe priority access: old messages in queue may lack the property
        try {
            $priorityLabel = $message->priority->label();
        } catch (\Error) {
            $priorityLabel = 'normal (legacy)';
        }

        $this->logger->info('TranslateArticleHandler: starting translation', [
            'articleId' => $message->articleId,
            'priority' => $priorityLabel,
            'locales' => $message->locales,
        ]);

        $tier = $this->tierResolver->resolve(self::AGENT_ID);
        $systemPrompt = $this->loadAgentSystemPrompt();

        // Translate one language at a time to keep Gemini output well under 64KB
        $successes = [];
        $failures = [];
        $halted = [];
        $needsReview = false;

        foreach ($message->locales as $locale) {
            $start = microtime(true);

            try {
                $userMessage = $this->buildPrompt($article, [$locale]);

                $response = $this->dispatcher->dispatch(new AgentRequest(
                    agentId: self::AGENT_ID,
                    messages: [['role' => 'user', 'content' => $userMessage]],
                    tier: $tier,
                    systemPrompt: $systemPrompt,
                ));

                $result = $this->resultProcessor->process($article, $response->content, [$locale]);

                $successes = [...$successes, ...$result->savedLocales];
                $needsReview = $needsReview || $result->needsReview;

                $this->logger->info('TranslateArticleHandler: locale completed', [
                    'articleId' => $message->articleId,
                    'locale' => $locale,
                    'duration' => round(microtime(true) - $start, 2) . 's',
                ]);
            } catch (EmergencyHaltException) {
                // Non-fatal halt per keeper #13. Halted locale tracked separately
                // for log discrimination but merged into $failures per T5 so the
                // finalize contract stays single-list.
                $halted[] = $locale;
                $failures[] = $locale;
                $this->logger->info('TranslateArticleHandler: locale halted by emergency_halt', [
                    'articleId' => $message->articleId,
                    'locale' => $locale,
                ]);
            } catch (GeminiCliException $e) {
                $failures[] = $locale;
                $this->logger->error('TranslateArticleHandler: Gemini ' . ($e->isTimeout() ? 'timeout' : 'failed'), [
                    'articleId' => $message->articleId,
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                $failures[] = $locale;
                $this->logger->error('TranslateArticleHandler: locale failed', [
                    'articleId' => $message->articleId,
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Determine final status
        if ($successes === []) {
            $this->resultProcessor->finalize($article, 'failed', [], false);

            // T4 rule: RuntimeException reserved for the clean all-halt aggregate.
            // Other 0-success outcomes (all-failed, mixed halt+fail) mark the
            // article failed without dead-lettering the message — the halt case
            // is the operator-driven signal worth surfacing in messenger:failed.
            if (\count($halted) === \count($message->locales)) {
                throw new \RuntimeException(\sprintf(
                    'All translations halted for article %d (emergency_halt active): %s',
                    $message->articleId,
                    implode(', ', $halted),
                ));
            }

            return;
        }

        $finalStatus = ($failures !== [] || $needsReview) ? 'needs_review' : 'completed';
        $this->resultProcessor->finalize($article, $finalStatus, $successes, $needsReview || $failures !== []);

        if ($message->forceRetranslate) {
            $article->setRequestTranslation(false);
            $this->em->flush();
        }

        $this->logger->info('TranslateArticleHandler: completed', [
            'articleId' => $message->articleId,
            'successes' => $successes,
            'failures' => $failures,
            'halted' => $halted,
        ]);
    }

    private function loadAgentSystemPrompt(): string
    {
        $agentFile = $this->projectDir . '/' . self::AGENT_FILE;
        if (is_file($agentFile)) {
            return (string) file_get_contents($agentFile);
        }

        return '';
    }

    /**
     * @param string[] $locales
     */
    private function buildPrompt(\App\Entity\Article $article, array $locales): string
    {
        $data = [
            'articleId' => $article->getId(),
            'sourceLocale' => 'ro',
            'locales' => $locales,
            'title' => $article->getTitle(),
            'slug' => $article->getSlug(),
            'lead' => $article->getLead() ?? '',
            'content' => $article->getContent(),
            'category' => $article->getCategory()?->getTitle() ?? '',
            'authorName' => ($article->getAuthors()->first() ?: null)?->getFullName() ?? '',
        ];

        if ($article->getMetaTitle() !== null) {
            $data['metaTitle'] = $article->getMetaTitle();
        }
        if ($article->getMetaDescription() !== null) {
            $data['metaDescription'] = $article->getMetaDescription();
        }

        return json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }
}
