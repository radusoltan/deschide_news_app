<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TelegramSessionManager
{
    private ?API $api = null;

    public function __construct(
        #[Autowire(env: 'TELEGRAM_API_ID')]
        private readonly string $apiId,
        #[Autowire(env: 'TELEGRAM_API_HASH')]
        private readonly string $apiHash,
        #[Autowire('%kernel.project_dir%/var/telegram/session.madeline')]
        private readonly string $sessionPath,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Fetch message history from a Telegram channel.
     *
     * @return array{messages: list<array<string, mixed>>}
     */
    public function getChannelHistory(string $username, int $offsetId, int $limit): array
    {
        $api = $this->getApi();

        return $api->messages->getHistory(
            peer: $username,
            offset_id: $offsetId,
            limit: $limit,
        );
    }

    public function isConfigured(): bool
    {
        return $this->apiId !== '' && $this->apiHash !== '';
    }

    private function getApi(): API
    {
        if ($this->api === null) {
            $this->logger->debug('TelegramSessionManager: initializing MadelineProto session', [
                'sessionPath' => $this->sessionPath,
            ]);

            $settings = new Settings();
            $settings->getAppInfo()
                ->setApiId((int) $this->apiId)
                ->setApiHash($this->apiHash);

            $this->api = new API($this->sessionPath, $settings);
        }

        return $this->api;
    }
}
