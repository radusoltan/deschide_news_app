<?php

declare(strict_types=1);

namespace App\Service\Ai\Provider;

use App\Enum\AiAgentType;

/**
 * AI provider that executes Gemini CLI subprocess.
 *
 * Delegates to GeminiCliService for process execution.
 */
final class GeminiCliProvider implements AiProviderInterface
{
    public function __construct(
        private readonly GeminiCliService $geminiCli,
    ) {}

    public function getName(): string
    {
        return 'gemini';
    }

    public function chat(string $prompt, ?string $systemPrompt = null): string
    {
        $fullPrompt = '';
        if ($systemPrompt !== null && $systemPrompt !== '') {
            $fullPrompt = $systemPrompt . "\n\n---\n\n";
        }
        $fullPrompt .= $prompt;

        $output = $this->geminiCli->execute($fullPrompt);

        if ($output === '') {
            throw new GeminiCliException('Gemini CLI returned empty output');
        }

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
