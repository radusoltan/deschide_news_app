<?php

declare(strict_types=1);

namespace App\Service\Ai;

final class MockAnthropicClient implements AnthropicClientInterface
{
    public function chat(array $messages, string $model, ?string $system = null): string
    {
        $lastMessage = end($messages);
        $userMessage = $lastMessage['content'] ?? '';

        // If it looks like a classification request, return JSON
        if ($system !== null && str_contains($system, 'Clasifici cererea')) {
            $agentType = $this->guessAgentType($userMessage);

            return json_encode([
                'agentType' => $agentType,
                'refinedPrompt' => $userMessage,
            ], JSON_THROW_ON_ERROR);
        }

        return sprintf(
            "[Mock %s] Răspuns simulat la: %.100s\n\nAcesta este un răspuns mock generat local. "
            . "Configurează `ANTHROPIC_API_KEY` pentru răspunsuri reale.",
            $model,
            $userMessage,
        );
    }

    public function chatStream(array $messages, string $model, ?string $system = null): \Generator
    {
        $response = $this->chat($messages, $model, $system);

        foreach (str_split($response, 20) as $chunk) {
            yield $chunk;
        }
    }

    private function guessAgentType(string $message): string
    {
        $lower = mb_strtolower($message);

        if (preg_match('/dosar|conexiun|vault|cercet|investig|entit[aă][țt]i|anali[zs]/u', $lower)) {
            return 'vault';
        }
        if (preg_match('/traduc|translat|evalueaz[aă].*traduc|limba/u', $lower)) {
            return 'translation';
        }
        if (preg_match('/briefing|rezumat|s[aă]pt[aă]m[aâ]nal|zilnic|tenden[țt]/u', $lower)) {
            return 'briefing';
        }
        if (preg_match('/seo|meta\s?tag|keyword|meta\s?description|optimiz/u', $lower)) {
            return 'seo';
        }

        return 'content';
    }
}
