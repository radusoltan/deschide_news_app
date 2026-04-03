<?php

declare(strict_types=1);

namespace App\Service\Ai\Provider;

use App\Enum\AiAgentType;
use App\Service\Ai\AnthropicClientInterface;
use Psr\Log\LoggerInterface;

final class AnthropicProvider implements AiProviderInterface
{
    public function __construct(
        private readonly AnthropicClientInterface $anthropicClient,
        private readonly LoggerInterface $logger,
    ) {}

    public function getName(): string
    {
        return 'anthropic';
    }

    public function chat(string $prompt, ?string $systemPrompt = null): string
    {
        $model = 'claude-sonnet-4-20250514';
        $messages = [['role' => 'user', 'content' => $prompt]];

        $this->logger->debug('AnthropicProvider: sending chat request', [
            'model' => $model,
            'promptLength' => mb_strlen($prompt),
        ]);

        return $this->anthropicClient->chat($messages, $model, $systemPrompt);
    }

    public function supports(AiAgentType $agentType): bool
    {
        return \in_array($agentType, [
            AiAgentType::VAULT,
            AiAgentType::CONTENT,
            AiAgentType::BRIEFING,
        ], true);
    }

    public function getModelForAgent(AiAgentType $agentType): string
    {
        return match ($agentType) {
            AiAgentType::CONTENT => 'claude-sonnet-4-20250514',
            AiAgentType::VAULT, AiAgentType::BRIEFING => 'claude-haiku-4-5-20251001',
            default => 'claude-sonnet-4-20250514',
        };
    }
}
