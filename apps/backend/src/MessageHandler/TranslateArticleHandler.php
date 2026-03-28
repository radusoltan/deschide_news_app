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
    private const TIMEOUT = 120;
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

        $start = microtime(true);

        try {
            $promptJson = $this->buildPrompt($article, $message->locales);
            $output = $this->runGemini($promptJson);
            $this->resultProcessor->process($article, $output, $message->locales);

            if ($message->forceRetranslate) {
                $article->setRequestTranslation(false);
                $this->em->flush();
            }

            $this->logger->info('TranslateArticleHandler: completed', [
                'articleId' => $message->articleId,
                'locales' => $message->locales,
                'duration' => round(microtime(true) - $start, 2) . 's',
            ]);
        } catch (ProcessTimedOutException $e) {
            $article->setTranslationStatus('failed');
            $this->em->flush();
            $this->logger->error('TranslateArticleHandler: Gemini timeout', [
                'articleId' => $message->articleId,
                'timeout' => self::TIMEOUT,
            ]);

            throw $e;
        } catch (\Throwable $e) {
            $article->setTranslationStatus('failed');
            $this->em->flush();
            $this->logger->error('TranslateArticleHandler: failed', [
                'articleId' => $message->articleId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
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
            'lead' => $article->getLead() ?? '',
            'content' => $article->getContent(),
            'category' => $article->getCategory()?->getName() ?? '',
            'authorName' => $article->getAuthors()->first()?->getName() ?? '',
        ];

        return json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    private function runGemini(string $promptJson): string
    {
        // Read agent system prompt to pipe via stdin
        $agentFile = $this->projectDir . '/' . self::AGENT_FILE;
        $systemPrompt = '';
        if (is_file($agentFile)) {
            $systemPrompt = file_get_contents($agentFile);
        }

        // Gemini CLI v0.34+: use -p for non-interactive mode, -o json for JSON output
        // System prompt piped via stdin, article JSON passed as -p argument
        $process = new Process(
            command: [
                $this->geminiCliPath,
                '-p', $promptJson,
                '-o', 'json',
            ],
            cwd: $this->projectDir,
            timeout: self::TIMEOUT,
        );

        if (!empty($systemPrompt)) {
            $process->setInput($systemPrompt);
        }

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
}
