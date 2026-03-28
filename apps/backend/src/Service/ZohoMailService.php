<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Zoho Mail API client with OAuth2 token refresh.
 */
class ZohoMailService
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $zohoClientId,
        private readonly string $zohoClientSecret,
        private readonly string $zohoRefreshToken,
        private readonly string $zohoAccountId,
        private readonly string $zohoInboxFolderId,
        private readonly string $zohoAccountsUrl = 'https://accounts.zoho.com',
    ) {
    }

    /**
     * List emails from a Zoho Mail folder.
     *
     * @return array<int, array{messageId: string, folderId: string, subject: string, fromAddress: string, receivedTime: string, summary: string, hasAttachment: bool, hasInline: bool}>
     */
    public function listEmails(int $limit = 20, int $start = 0, ?string $folderId = null): array
    {
        $folder = $folderId ?? $this->zohoInboxFolderId;

        $data = $this->apiRequest('GET', "/messages/view?folderId={$folder}&limit={$limit}&start={$start}");

        return array_map(fn(array $msg) => [
            'messageId' => (string) $msg['messageId'],
            'folderId' => (string) ($msg['folderId'] ?? $folder),
            'subject' => $msg['subject'] ?? '',
            'fromAddress' => $msg['fromAddress'] ?? '',
            'receivedTime' => $msg['receivedTime'] ?? '',
            'summary' => $msg['summary'] ?? '',
            'hasAttachment' => ($msg['hasAttachment'] ?? '0') === '1',
            'hasInline' => ($msg['hasInline'] ?? '0') === '1',
            'status' => $msg['status'] ?? '',
        ], $data['data'] ?? []);
    }

    /**
     * Get the full HTML content of an email.
     */
    public function getEmailContent(string $messageId, ?string $folderId = null): string
    {
        $folder = $folderId ?? $this->zohoInboxFolderId;

        $data = $this->apiRequest('GET', "/folders/{$folder}/messages/{$messageId}/content");

        return $data['data']['content'] ?? '';
    }

    /**
     * Mark emails as read.
     *
     * @param string[] $messageIds
     */
    public function markAsRead(array $messageIds): bool
    {
        $data = $this->apiRequest('PUT', '/messages', [
            'json' => [
                'mode' => 'markAsRead',
                'messageId' => $messageIds,
            ],
        ]);

        return ($data['status']['code'] ?? 0) === 200;
    }

    /**
     * @return array<string, mixed>
     */
    private function apiRequest(string $method, string $path, array $options = []): array
    {
        $token = $this->getAccessToken();
        $url = "https://mail.zoho.com/api/accounts/{$this->zohoAccountId}{$path}";

        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => "Zoho-oauthtoken {$token}",
        ]);

        $response = $this->httpClient->request($method, $url, $options);
        $statusCode = $response->getStatusCode();

        // Auto-refresh on 401
        if ($statusCode === 401) {
            $this->accessToken = null;
            $token = $this->getAccessToken();
            $options['headers']['Authorization'] = "Zoho-oauthtoken {$token}";

            $response = $this->httpClient->request($method, $url, $options);
        }

        $content = $response->getContent(false);

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->logger->error('Zoho API: invalid JSON response', ['status' => $response->getStatusCode(), 'body' => substr($content, 0, 200)]);
            return [];
        }
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $response = $this->httpClient->request('POST', "{$this->zohoAccountsUrl}/oauth/v2/token", [
            'body' => [
                'refresh_token' => $this->zohoRefreshToken,
                'client_id' => $this->zohoClientId,
                'client_secret' => $this->zohoClientSecret,
                'grant_type' => 'refresh_token',
            ],
        ]);

        $data = $response->toArray(false);

        if (!isset($data['access_token'])) {
            $this->logger->error('Zoho OAuth: token refresh failed', ['response' => $data]);
            throw new \RuntimeException('Failed to refresh Zoho access token');
        }

        $this->accessToken = $data['access_token'];
        $this->logger->debug('Zoho OAuth: token refreshed successfully');

        return $this->accessToken;
    }
}
