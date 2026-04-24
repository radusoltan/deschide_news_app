<?php

declare(strict_types=1);

namespace App\Service\Ai;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publishes AI chat events (typing indicators, messages) to Mercure hub.
 */
class AiMercureService
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {}

    public function publishTyping(int $conversationId): void
    {
        try {
            $this->hub->publish(new Update(
                sprintf('deschide_news/ai/conversation/%d', $conversationId),
                json_encode(['type' => 'typing', 'conversationId' => $conversationId]),
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('AiMercureService: failed to publish typing event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function publishMessage(int $conversationId, string $content, string $role = 'assistant'): void
    {
        try {
            $this->hub->publish(new Update(
                sprintf('deschide_news/ai/conversation/%d', $conversationId),
                json_encode([
                    'type' => 'message',
                    'conversationId' => $conversationId,
                    'role' => $role,
                    'content' => $content,
                ]),
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('AiMercureService: failed to publish message event', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
