<?php

declare(strict_types=1);

namespace App\Service\Ai\Provider;

use App\Enum\AiAgentType;

interface AiProviderInterface
{
    /**
     * Provider name identifier: 'anthropic' | 'gemini'.
     */
    public function getName(): string;

    /**
     * Send a prompt and get a response.
     */
    public function chat(string $prompt, ?string $systemPrompt = null): string;

    /**
     * Whether this provider handles the given agent type.
     */
    public function supports(AiAgentType $agentType): bool;

    /**
     * Get the model identifier used for this agent type.
     */
    public function getModelForAgent(AiAgentType $agentType): string;
}
