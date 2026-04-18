<?php

declare(strict_types=1);

namespace App\Service\Ai;

interface AnthropicClientInterface
{
    /**
     * Send a chat request and return the full response text.
     *
     * @param list<array{role: string, content: string}> $messages
     */
    public function chat(array $messages, string $model, ?string $system = null): string;

    /**
     * Send a streaming chat request, yielding text deltas.
     *
     * @param list<array{role: string, content: string}> $messages
     *
     * @return \Generator<int, string, void, void>
     */
    public function chatStream(array $messages, string $model, ?string $system = null): \Generator;
}
