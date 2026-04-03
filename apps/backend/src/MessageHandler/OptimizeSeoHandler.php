<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\OptimizeSeoMessage;
use App\Repository\ArticleRepository;
use App\Service\SeoPromptBuilder;
use App\Service\SeoResultProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final readonly class OptimizeSeoHandler
{
    private const TIMEOUT = 120;

    public function __construct(
        private ArticleRepository $articleRepository,
        private SeoPromptBuilder $promptBuilder,
        private SeoResultProcessor $resultProcessor,
        private LoggerInterface $logger,
        private string $geminiCliPath,
        private string $projectDir,
    ) {
    }

    /**
     * Handle the message (async via Messenger queue).
     */
    public function __invoke(OptimizeSeoMessage $message): void
    {
        $result = $this->handle($message);

        if ($result === null) {
            $this->logger->warning('OptimizeSeoHandler: skipped or failed', [
                'articleId' => $message->articleId,
            ]);
        }
    }

    /**
     * Execute SEO optimization synchronously and return result.
     * Used both by the message handler and by the API controller.
     *
     * @return array{metaTitle: ?string, metaDescription: ?string, tagsAdded: string[], tagsExisting: string[]}|null
     */
    public function handle(OptimizeSeoMessage $message): ?array
    {
        $article = $this->articleRepository->find($message->articleId);

        if ($article === null) {
            $this->logger->warning('OptimizeSeoHandler: article not found', [
                'articleId' => $message->articleId,
            ]);

            return null;
        }

        $title = $article->getTitle();
        $lead = $article->getLead();
        $content = $article->getContent();

        // Need at least title + (lead or content)
        if (empty($title) || (empty($lead) && empty($content))) {
            $this->logger->warning('OptimizeSeoHandler: insufficient content for SEO', [
                'articleId' => $message->articleId,
            ]);

            return null;
        }

        $options = [
            'generateMeta' => $message->generateMeta,
            'suggestTags' => $message->suggestTags,
            'force' => $message->force,
        ];

        // Skip if meta already set and has enough tags (unless force)
        if (!$message->force
            && $article->getMetaTitle() !== null
            && $article->getMetaDescription() !== null
            && $article->getTags()->count() >= 3
        ) {
            $this->logger->info('OptimizeSeoHandler: article already optimized, skipping', [
                'articleId' => $message->articleId,
            ]);

            return null;
        }

        $locale = $message->locale;

        // Set translatable locale so Gedmo loads/saves the correct translation
        $article->setTranslatableLocale($locale);
        $this->articleRepository->getEntityManager()->refresh($article);

        $this->logger->info('OptimizeSeoHandler: starting SEO optimization', [
            'articleId' => $message->articleId,
            'locale' => $locale,
            'force' => $message->force,
        ]);

        $start = microtime(true);

        try {
            $prompt = $this->promptBuilder->build($article, $options, $locale);
            $output = $this->runGemini($prompt);
            $data = $this->parseResponse($output);

            $result = $this->resultProcessor->process($article, $data, $options, $locale);

            $duration = round(microtime(true) - $start, 2);

            $this->logger->info('OptimizeSeoHandler: completed', [
                'articleId' => $message->articleId,
                'metaTitleLength' => $result['metaTitle'] !== null ? mb_strlen($result['metaTitle']) : null,
                'metaDescLength' => $result['metaDescription'] !== null ? mb_strlen($result['metaDescription']) : null,
                'tagsAdded' => \count($result['tagsAdded']),
                'tagsExisting' => \count($result['tagsExisting']),
                'duration' => $duration . 's',
            ]);

            return $result;
        } catch (ProcessTimedOutException) {
            $this->logger->error('OptimizeSeoHandler: Gemini timeout', [
                'articleId' => $message->articleId,
                'timeout' => self::TIMEOUT,
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->logger->error('OptimizeSeoHandler: failed', [
                'articleId' => $message->articleId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function runGemini(string $prompt): string
    {
        $process = new Process(
            command: [
                $this->geminiCliPath,
                '-p', 'Analyze the article and generate SEO metadata in JSON format as instructed.',
                '-o', 'json',
            ],
            cwd: $this->projectDir,
            env: ['HOME' => '/home/radu', 'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'],
            timeout: self::TIMEOUT,
        );

        $process->setInput($prompt);
        $process->run();

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

    /**
     * Parse Gemini CLI output, handling the envelope format.
     *
     * @return array<string, mixed>
     */
    private function parseResponse(string $rawOutput): array
    {
        $cleaned = trim($rawOutput);

        // Strip control characters
        $map = [];
        for ($i = 0; $i <= 0x1F; ++$i) {
            $map[\chr($i)] = ' ';
        }
        $map[\chr(0x7F)] = ' ';
        $cleaned = strtr($cleaned, $map);

        $decoded = json_decode($cleaned, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new \RuntimeException('Gemini SEO response did not decode to an array');
        }

        // Handle Gemini CLI envelope: { session_id, response, stats }
        if (isset($decoded['response']) && \is_string($decoded['response'])) {
            $responseStr = $decoded['response'];

            // Strip markdown fences
            $responseStr = preg_replace('/^```(?:json)?\s*/m', '', $responseStr);
            $responseStr = preg_replace('/\s*```\s*$/m', '', $responseStr ?? $decoded['response']);
            $responseStr = trim($responseStr ?? $decoded['response']);

            // Extract JSON object
            $jsonStart = strpos($responseStr, '{');
            $jsonEnd = strrpos($responseStr, '}');
            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                $responseStr = substr($responseStr, $jsonStart, $jsonEnd - $jsonStart + 1);
            }

            // Strip control characters again
            $responseStr = strtr($responseStr, $map);

            $inner = json_decode($responseStr, true, 512, \JSON_THROW_ON_ERROR);

            if (\is_array($inner)) {
                return $inner;
            }
        }

        // Direct JSON (no envelope)
        if (isset($decoded['metaTitle']) || isset($decoded['suggestedTags'])) {
            return $decoded;
        }

        throw new \RuntimeException('Unexpected Gemini SEO response format');
    }
}
