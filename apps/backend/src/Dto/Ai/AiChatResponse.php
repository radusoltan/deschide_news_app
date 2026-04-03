<?php

declare(strict_types=1);

namespace App\Dto\Ai;

final class AiChatResponse
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $messageId,
        public readonly string $content,
        public readonly string $agentType,
        public readonly ?string $model = null,
        public readonly ?int $tokensUsed = null,
    ) {}
}
