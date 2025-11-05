<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LiveText;
use App\Entity\LiveTextPost;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for social media integration.
 *
 * Features:
 * - Auto-post to social media platforms
 * - Twitter, Facebook, Telegram integration
 * - Queue-based posting (optional with Messenger)
 */
class SocialMediaService
{
    private const MAX_TWEET_LENGTH = 280;

    private const MAX_FACEBOOK_LENGTH = 500;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $frontendUrl,
        // Social media credentials from .env
        private readonly ?string $twitterApiKey = null,
        private readonly ?string $twitterApiSecret = null,
        private readonly ?string $twitterAccessToken = null,
        private readonly ?string $twitterAccessSecret = null,
        private readonly ?string $facebookPageId = null,
        private readonly ?string $facebookAccessToken = null,
        private readonly ?string $telegramBotToken = null,
        private readonly ?string $telegramChannelId = null
    ) {
    }

    /**
     * Post LiveText start to social media.
     */
    public function postLiveTextStarted(LiveText $liveText): void
    {
        $url = $this->generateLiveTextUrl($liveText);
        $message = $this->generateLiveTextStartMessage($liveText);

        // Post to enabled platforms
        if ($this->isTwitterEnabled()) {
            $this->postToTwitter($message, $url);
        }

        if ($this->isFacebookEnabled()) {
            $this->postToFacebook($message, $url);
        }

        if ($this->isTelegramEnabled()) {
            $this->postToTelegram($message, $url);
        }
    }

    /**
     * Post important LiveText post to social media.
     */
    public function postImportantUpdate(LiveTextPost $post): void
    {
        $liveText = $post->getLiveText();
        $url = $this->generateLiveTextUrl($liveText);
        $message = $this->generatePostMessage($post, $liveText);

        // Only post key points to social media
        if (!$post->isKeyPoint()) {
            return;
        }

        // Post to enabled platforms
        if ($this->isTwitterEnabled()) {
            $this->postToTwitter($message, $url);
        }

        if ($this->isFacebookEnabled()) {
            $this->postToFacebook($message, $url);
        }

        if ($this->isTelegramEnabled()) {
            $this->postToTelegram($message, $url);
        }
    }

    /**
     * Get social sharing metadata for frontend.
     */
    public function getSharingMetadata(LiveText $liveText): array
    {
        $url = $this->generateLiveTextUrl($liveText);
        $title = $liveText->getTitle();
        $description = $liveText->getDescription() ?? \sprintf('Live coverage: %s', $title);

        // Get featured image if available
        $image = null;
        $posts = $liveText->getPosts();
        foreach ($posts as $post) {
            if ($post->getFeaturedImage()) {
                $image = $post->getFeaturedImage();
                break;
            }
        }

        return [
            'url' => $url,
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'sharing_urls' => [
                'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($url),
                'twitter' => 'https://twitter.com/intent/tweet?text=' . urlencode($title . ' ' . $url),
                'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($url),
                'whatsapp' => 'https://wa.me/?text=' . urlencode($title . ' ' . $url),
                'telegram' => 'https://t.me/share/url?url=' . urlencode($url) . '&text=' . urlencode($title),
            ],
        ];
    }

    /**
     * Post to Twitter.
     */
    private function postToTwitter(string $message, string $url): void
    {
        try {
            // Truncate message if needed
            $maxLength = self::MAX_TWEET_LENGTH - \strlen($url) - 3; // 3 for " - "
            if (\strlen($message) > $maxLength) {
                $message = substr($message, 0, $maxLength - 3) . '...';
            }

            $tweet = $message . ' - ' . $url;

            // Twitter API v2 endpoint
            $response = $this->httpClient->request('POST', 'https://api.twitter.com/2/tweets', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getTwitterBearerToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'text' => $tweet,
                ],
            ]);

            if ($response->getStatusCode() === 201) {
                $this->logger->info('Posted to Twitter successfully', [
                    'tweet' => $tweet,
                ]);
            } else {
                $this->logger->error('Failed to post to Twitter', [
                    'status' => $response->getStatusCode(),
                    'response' => $response->getContent(false),
                ]);
            }
        } catch (Exception $e) {
            $this->logger->error('Exception posting to Twitter', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Post to Facebook Page.
     */
    private function postToFacebook(string $message, string $url): void
    {
        try {
            // Truncate message if needed
            if (\strlen($message) > self::MAX_FACEBOOK_LENGTH) {
                $message = substr($message, 0, self::MAX_FACEBOOK_LENGTH - 3) . '...';
            }

            $postMessage = $message . "\n\n" . $url;

            // Facebook Graph API
            $response = $this->httpClient->request('POST', "https://graph.facebook.com/{$this->facebookPageId}/feed", [
                'body' => [
                    'message' => $postMessage,
                    'link' => $url,
                    'access_token' => $this->facebookAccessToken,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $this->logger->info('Posted to Facebook successfully', [
                    'message' => $postMessage,
                ]);
            } else {
                $this->logger->error('Failed to post to Facebook', [
                    'status' => $response->getStatusCode(),
                    'response' => $response->getContent(false),
                ]);
            }
        } catch (Exception $e) {
            $this->logger->error('Exception posting to Facebook', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Post to Telegram Channel.
     */
    private function postToTelegram(string $message, string $url): void
    {
        try {
            $fullMessage = $message . "\n\n" . $url;

            // Telegram Bot API
            $response = $this->httpClient->request('POST', "https://api.telegram.org/bot{$this->telegramBotToken}/sendMessage", [
                'json' => [
                    'chat_id' => $this->telegramChannelId,
                    'text' => $fullMessage,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => false,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $this->logger->info('Posted to Telegram successfully', [
                    'message' => $fullMessage,
                ]);
            } else {
                $this->logger->error('Failed to post to Telegram', [
                    'status' => $response->getStatusCode(),
                    'response' => $response->getContent(false),
                ]);
            }
        } catch (Exception $e) {
            $this->logger->error('Exception posting to Telegram', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate LiveText URL.
     */
    private function generateLiveTextUrl(LiveText $liveText): string
    {
        $locale = $liveText->getLocale() ?? 'ro';

        return \sprintf('%s/%s/live/%s', $this->frontendUrl, $locale, $liveText->getSlug());
    }

    /**
     * Generate message for LiveText start.
     */
    private function generateLiveTextStartMessage(LiveText $liveText): string
    {
        $status = match ($liveText->getStatus()->value) {
            'live' => '🔴 LIVE',
            'draft' => '📝 Draft',
            default => '📰'
        };

        return \sprintf(
            '%s: %s',
            $status,
            $liveText->getTitle()
        );
    }

    /**
     * Generate message for LiveText post.
     */
    private function generatePostMessage(LiveTextPost $post, LiveText $liveText): string
    {
        $icon = $post->isKeyPoint() ? '⚡' : '📰';
        $content = strip_tags($post->getContentHtml() ?? $post->getContent());

        // Truncate content
        $maxLength = 200;
        if (\strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength - 3) . '...';
        }

        return \sprintf(
            '%s %s: %s',
            $icon,
            $liveText->getTitle(),
            $content
        );
    }

    /**
     * Get Twitter Bearer Token (using OAuth 2.0).
     */
    private function getTwitterBearerToken(): string
    {
        // Note: In production, you should cache this token
        // For simplicity, returning access token here
        // Proper implementation would use OAuth 1.0a or OAuth 2.0 flow
        return $this->twitterAccessToken ?? '';
    }

    /**
     * Check if Twitter integration is enabled.
     */
    private function isTwitterEnabled(): bool
    {
        return !empty($this->twitterApiKey)
            && !empty($this->twitterApiSecret)
            && !empty($this->twitterAccessToken)
            && !empty($this->twitterAccessSecret);
    }

    /**
     * Check if Facebook integration is enabled.
     */
    private function isFacebookEnabled(): bool
    {
        return !empty($this->facebookPageId) && !empty($this->facebookAccessToken);
    }

    /**
     * Check if Telegram integration is enabled.
     */
    private function isTelegramEnabled(): bool
    {
        return !empty($this->telegramBotToken) && !empty($this->telegramChannelId);
    }
}
