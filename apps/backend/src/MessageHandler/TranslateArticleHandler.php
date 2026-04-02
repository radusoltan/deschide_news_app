<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TranslateArticleMessage;
use App\Repository\ArticleRepository;
use App\Service\TranslationResultProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final readonly class TranslateArticleHandler
{
    private const TIMEOUT = 300;
    private const AGENT_FILE = '.gemini/agents/journalistic-translator.md';

    public function __construct(
        private ArticleRepository $articleRepository,
        private TranslationResultProcessor $resultProcessor,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private string $geminiCliPath,
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

        // Translate one language at a time to keep Gemini output well under 64KB
        $successes = [];
        $failures = [];
        $needsReview = false;

        foreach ($message->locales as $locale) {
            $start = microtime(true);

            try {
                $promptJson = $this->buildPrompt($article, [$locale]);
                $output = $this->runGemini($promptJson, [$locale]);
                $result = $this->resultProcessor->process($article, $output, [$locale]);

                $successes = [...$successes, ...$result->savedLocales];
                $needsReview = $needsReview || $result->needsReview;

                $this->logger->info('TranslateArticleHandler: locale completed', [
                    'articleId' => $message->articleId,
                    'locale' => $locale,
                    'duration' => round(microtime(true) - $start, 2) . 's',
                ]);
            } catch (ProcessTimedOutException $e) {
                $failures[] = $locale;
                $this->logger->error('TranslateArticleHandler: Gemini timeout', [
                    'articleId' => $message->articleId,
                    'locale' => $locale,
                    'timeout' => self::TIMEOUT,
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

            throw new \RuntimeException(\sprintf(
                'All translations failed for article %d: %s',
                $message->articleId,
                implode(', ', $failures),
            ));
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
        ]);
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
            'authorName' => $article->getAuthors()->first()?->getFullName() ?? '',
        ];

        if ($article->getMetaTitle() !== null) {
            $data['metaTitle'] = $article->getMetaTitle();
        }
        if ($article->getMetaDescription() !== null) {
            $data['metaDescription'] = $article->getMetaDescription();
        }

        return json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    /**
     * @param string[] $locales Locales requested in this call (typically one)
     */
    private function runGemini(string $promptJson, array $locales = []): string
    {
        // Read agent system prompt
        $agentFile = $this->projectDir . '/' . self::AGENT_FILE;
        $systemPrompt = '';
        if (is_file($agentFile)) {
            $systemPrompt = file_get_contents($agentFile);
        }

        // Write prompt to temp file to avoid CLI argument length limits
        $tmpFile = tempnam(sys_get_temp_dir(), 'gemini_prompt_');
        file_put_contents($tmpFile, $promptJson);

        // Combine system prompt + article JSON via stdin
        $stdinContent = $systemPrompt . "\n\n---\n\nArticle JSON to translate:\n" . $promptJson;

        // Gemini CLI: use -p with short instruction, full content via stdin
        $process = new Process(
            command: [
                $this->geminiCliPath,
                '-p', 'Translate the article from the input below. Return JSON with translations key containing ' . implode(' and ', $locales) . '.',
                '-o', 'json',
            ],
            cwd: $this->projectDir,
            env: ['HOME' => '/home/radu', 'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'],
            timeout: self::TIMEOUT,
        );

        $process->setInput($stdinContent);
        $process->run();

        @unlink($tmpFile);

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                'Gemini CLI failed (exit ' . $process->getExitCode() . '): '
                . $process->getErrorOutput()
            );
        }

        $output = trim($process->getOutput());

        if (empty($output)) {
            throw new \RuntimeException('Gemini CLI returned empty output');
        }

        return $output;
    }
}
