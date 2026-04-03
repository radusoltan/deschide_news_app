<?php

declare(strict_types=1);

namespace App\Service\Ai\Provider;

use App\Enum\AiAgentType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * AI provider that executes Gemini CLI subprocess.
 *
 * Follows the same pattern as TranslateArticleHandler::runGemini() — uses stdin
 * for content delivery and `-p` for short instruction prompts.
 */
final class GeminiCliProvider implements AiProviderInterface
{
    private const TIMEOUT = 120;

    public function __construct(
        private readonly string $geminiCliPath,
        private readonly LoggerInterface $logger,
    ) {}

    public function getName(): string
    {
        return 'gemini';
    }

    public function chat(string $prompt, ?string $systemPrompt = null): string
    {
        $startTime = microtime(true);

        // Build stdin content: system prompt + user prompt
        $stdinContent = '';
        if ($systemPrompt !== null && $systemPrompt !== '') {
            $stdinContent = $systemPrompt . "\n\n---\n\n";
        }
        $stdinContent .= $prompt;

        // Short instruction via -p, full content via stdin
        $process = new Process(
            command: [
                $this->geminiCliPath,
                '-p', 'Process the request from the input below. Respond in the language of the request.',
            ],
            env: ['HOME' => '/home/radu', 'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'],
            timeout: self::TIMEOUT,
        );

        $process->setInput($stdinContent);
        $process->run();

        $duration = (int) round((microtime(true) - $startTime) * 1000);

        if (!$process->isSuccessful()) {
            $this->logger->error('GeminiCliProvider: CLI failed', [
                'exitCode' => $process->getExitCode(),
                'stderr' => mb_substr($process->getErrorOutput(), 0, 500),
                'duration_ms' => $duration,
            ]);

            throw new \RuntimeException(sprintf(
                'Gemini CLI failed (exit %d): %s',
                $process->getExitCode(),
                mb_substr($process->getErrorOutput(), 0, 200),
            ));
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            throw new \RuntimeException('Gemini CLI returned empty output');
        }

        $this->logger->info('GeminiCliProvider: request completed', [
            'duration_ms' => $duration,
            'outputLength' => mb_strlen($output),
        ]);

        return $output;
    }

    public function supports(AiAgentType $agentType): bool
    {
        return \in_array($agentType, [
            AiAgentType::TRANSLATION,
            AiAgentType::SEO,
        ], true);
    }

    public function getModelForAgent(AiAgentType $agentType): string
    {
        return 'gemini-2.5-flash';
    }
}
