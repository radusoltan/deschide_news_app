<?php

declare(strict_types=1);

namespace App\Service\Ai\Provider;

use App\Enum\AiAgentType;
use App\Service\Ai\AnthropicClientInterface;
use Psr\Log\LoggerInterface;

final class AnthropicProvider implements AiProviderInterface
{
    public function __construct(
        private readonly AnthropicClientInterface $client,
        private readonly LoggerInterface $logger,
    ) {}

    public function getName(): string
    {
        return 'anthropic';
    }

    public function chat(string $prompt, ?string $systemPrompt = null): string
    {
        $startTime = microtime(true);

        $messages = [['role' => 'user', 'content' => $prompt]];
        $model = $this->getModelForAgent(AiAgentType::RESEARCH);

        $result = $this->client->chat($messages, $model, $systemPrompt);

        $duration = (int) round((microtime(true) - $startTime) * 1000);

        $this->logger->info('AnthropicProvider: request completed', [
            'duration_ms' => $duration,
            'outputLength' => mb_strlen($result),
        ]);

        return $result;
    }

    public function supports(AiAgentType $agentType): bool
    {
        return \in_array($agentType, [
            AiAgentType::RESEARCH,
            AiAgentType::CONTENT,
            AiAgentType::BRIEFING,
        ], true);
    }

    public function getModelForAgent(AiAgentType $agentType): string
    {
        return match ($agentType) {
            AiAgentType::CONTENT => 'claude-sonnet-4-20250514',
            AiAgentType::RESEARCH, AiAgentType::BRIEFING => 'claude-haiku-4-5-20251001',
            default => 'claude-haiku-4-5-20251001',
        };
    }
}
