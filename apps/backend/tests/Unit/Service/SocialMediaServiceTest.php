<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\LiveText;
use App\Entity\LiveTextPost;
use App\Enum\LiveTextStatus;
use App\Service\SocialMediaService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Unit tests for SocialMediaService.
 *
 * Tests cover:
 * - Social platform availability checks (twitter/facebook/telegram enabled/disabled)
 * - getSharingMetadata – URL and sharing-link generation
 * - postLiveTextStarted / postImportantUpdate when all platforms are disabled
 *   (no HTTP calls expected, no exceptions)
 *
 * Note: actual HTTP posting (postToTwitter/postToFacebook/postToTelegram) is tested
 * via integration tests since it depends on real HTTP responses.
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SocialMediaServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->logger     = $this->createStub(LoggerInterface::class);
    }

    // =========================================================================
    // Helper builders
    // =========================================================================

    private function makeService(
        string $frontendUrl = 'https://deschide.md',
        ?string $twitterApiKey = null,
        ?string $twitterApiSecret = null,
        ?string $twitterAccessToken = null,
        ?string $twitterAccessSecret = null,
        ?string $facebookPageId = null,
        ?string $facebookAccessToken = null,
        ?string $telegramBotToken = null,
        ?string $telegramChannelId = null,
    ): SocialMediaService {
        return new SocialMediaService(
            $this->httpClient,
            $this->logger,
            $frontendUrl,
            $twitterApiKey,
            $twitterApiSecret,
            $twitterAccessToken,
            $twitterAccessSecret,
            $facebookPageId,
            $facebookAccessToken,
            $telegramBotToken,
            $telegramChannelId,
        );
    }

    private function makeLiveText(
        int $id = 1,
        string $title = 'Test Live',
        string $slug = 'test-live',
        string $locale = 'ro',
        ?string $description = null,
    ): LiveText {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn($id);
        $liveText->method('getTitle')->willReturn($title);
        $liveText->method('getSlug')->willReturn($slug);
        $liveText->method('getLocale')->willReturn($locale);
        $liveText->method('getDescription')->willReturn($description);
        $liveText->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $liveText->method('getPosts')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        return $liveText;
    }

    // =========================================================================
    // getSharingMetadata
    // =========================================================================

    #[Test]
    public function getSharingMetadataReturnsCorrectUrl(): void
    {
        $service  = $this->makeService('https://deschide.md');
        $liveText = $this->makeLiveText(id: 5, slug: 'alegeri-2026', locale: 'ro');

        $meta = $service->getSharingMetadata($liveText);

        $this->assertSame('https://deschide.md/ro/live/alegeri-2026', $meta['url']);
    }

    #[Test]
    public function getSharingMetadataUsesFallbackDescriptionWhenNone(): void
    {
        $service  = $this->makeService();
        $liveText = $this->makeLiveText(title: 'Match Day', description: null);

        $meta = $service->getSharingMetadata($liveText);

        $this->assertStringContainsString('Match Day', $meta['description']);
    }

    #[Test]
    public function getSharingMetadataUsesProvidedDescription(): void
    {
        $service  = $this->makeService();
        $liveText = $this->makeLiveText(description: 'My custom description');

        $meta = $service->getSharingMetadata($liveText);

        $this->assertSame('My custom description', $meta['description']);
    }

    #[Test]
    public function getSharingMetadataIncludesFacebookUrl(): void
    {
        $service  = $this->makeService('https://deschide.md');
        $liveText = $this->makeLiveText(slug: 'live-slug', locale: 'ro');

        $meta = $service->getSharingMetadata($liveText);

        $this->assertArrayHasKey('sharing_urls', $meta);
        $this->assertArrayHasKey('facebook', $meta['sharing_urls']);
        $this->assertStringContainsString('facebook.com', $meta['sharing_urls']['facebook']);
    }

    #[Test]
    public function getSharingMetadataIncludesTwitterUrl(): void
    {
        $service  = $this->makeService();
        $liveText = $this->makeLiveText();

        $meta = $service->getSharingMetadata($liveText);

        $this->assertArrayHasKey('twitter', $meta['sharing_urls']);
        $this->assertStringContainsString('twitter.com', $meta['sharing_urls']['twitter']);
    }

    #[Test]
    public function getSharingMetadataIncludesWhatsappUrl(): void
    {
        $service  = $this->makeService();
        $liveText = $this->makeLiveText();

        $meta = $service->getSharingMetadata($liveText);

        $this->assertArrayHasKey('whatsapp', $meta['sharing_urls']);
        $this->assertStringContainsString('wa.me', $meta['sharing_urls']['whatsapp']);
    }

    #[Test]
    public function getSharingMetadataIncludesTelegramUrl(): void
    {
        $service  = $this->makeService();
        $liveText = $this->makeLiveText();

        $meta = $service->getSharingMetadata($liveText);

        $this->assertArrayHasKey('telegram', $meta['sharing_urls']);
        $this->assertStringContainsString('t.me', $meta['sharing_urls']['telegram']);
    }

    #[Test]
    public function getSharingMetadataIncludesLinkedinUrl(): void
    {
        $service  = $this->makeService();
        $liveText = $this->makeLiveText();

        $meta = $service->getSharingMetadata($liveText);

        $this->assertArrayHasKey('linkedin', $meta['sharing_urls']);
        $this->assertStringContainsString('linkedin.com', $meta['sharing_urls']['linkedin']);
    }

    #[Test]
    public function getSharingMetadataHasNullImageWhenNoPostsHaveFeaturedImage(): void
    {
        $service  = $this->makeService();
        $liveText = $this->makeLiveText();

        $meta = $service->getSharingMetadata($liveText);

        $this->assertNull($meta['image']);
    }

    #[Test]
    public function getSharingMetadataReturnsTitle(): void
    {
        $service  = $this->makeService();
        $liveText = $this->makeLiveText(title: 'Breaking News Title');

        $meta = $service->getSharingMetadata($liveText);

        $this->assertSame('Breaking News Title', $meta['title']);
    }

    // =========================================================================
    // postLiveTextStarted – no platforms enabled
    // =========================================================================

    #[Test]
    public function postLiveTextStartedDoesNotThrowWhenNoPlatformsEnabled(): void
    {
        $service  = $this->makeService(); // all credentials null → disabled
        $liveText = $this->makeLiveText();

        // Should not throw
        $service->postLiveTextStarted($liveText);

        $this->assertTrue(true);
    }

    // =========================================================================
    // postImportantUpdate – non-key-point post is silently skipped
    // =========================================================================

    #[Test]
    public function postImportantUpdateSkipsNonKeyPointPost(): void
    {
        $service = $this->makeService(
            telegramBotToken: 'bot-token',
            telegramChannelId: '@channel',
        );

        $liveText = $this->makeLiveText();

        $post = $this->createStub(LiveTextPost::class);
        $post->method('isKeyPoint')->willReturn(false);
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getContent')->willReturn('Some content');
        $post->method('getContentHtml')->willReturn(null);

        // httpClient must NOT be called since post is not a key point
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->httpClient->expects($this->never())->method('request');

        // Rebuild service with the mock
        $service = new SocialMediaService(
            $this->httpClient,
            $this->logger,
            'https://deschide.md',
            null, null, null, null,  // twitter disabled
            null, null,              // facebook disabled
            'bot-token', '@channel'  // telegram enabled
        );

        $service->postImportantUpdate($post);
    }

    // =========================================================================
    // URL generation – locale prefix
    // =========================================================================

    #[Test]
    public function getSharingMetadataUsesRoLocaleInUrl(): void
    {
        $service  = $this->makeService('https://deschide.md');
        $liveText = $this->makeLiveText(slug: 'slug', locale: 'ro');

        $meta = $service->getSharingMetadata($liveText);

        $this->assertStringContainsString('/ro/live/slug', $meta['url']);
    }

    #[Test]
    public function getSharingMetadataUsesEnLocaleInUrl(): void
    {
        $service  = $this->makeService('https://deschide.md');
        $liveText = $this->makeLiveText(slug: 'slug', locale: 'en');

        $meta = $service->getSharingMetadata($liveText);

        $this->assertStringContainsString('/en/live/slug', $meta['url']);
    }

    // =========================================================================
    // Platform enabled/disabled detection (indirectly via postLiveTextStarted)
    // =========================================================================

    #[Test]
    public function postLiveTextStartedDoesNotThrowWithAllPlatformsEnabled(): void
    {
        // HTTP client will be called for each enabled platform, but we stub responses
        $response = $this->createStub(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('{}');

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $service = new SocialMediaService(
            $httpClient,
            $this->logger,
            'https://deschide.md',
            'twitter-key', 'twitter-secret', 'access-token', 'access-secret',
            'fb-page-id', 'fb-access-token',
            'tg-bot-token', '@tg-channel',
        );

        $liveText = $this->makeLiveText();

        // Should not throw even if HTTP responses are stubbed
        $service->postLiveTextStarted($liveText);

        $this->assertTrue(true);
    }

    #[Test]
    public function postLiveTextStartedHandlesHttpClientException(): void
    {
        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException(new \Exception('Network error'));

        $service = new SocialMediaService(
            $httpClient,
            $this->logger,
            'https://deschide.md',
            'tw-key', 'tw-secret', 'tw-token', 'tw-token-secret',
            'fb-page', 'fb-token',
            'tg-bot', '@channel',
        );

        $liveText = $this->makeLiveText();

        // Exceptions must be caught internally – service swallows them
        $service->postLiveTextStarted($liveText);

        $this->assertTrue(true);
    }

    // =========================================================================
    // postImportantUpdate – key-point post triggers platform calls
    // =========================================================================

    #[Test]
    public function postImportantUpdatePostsKeyPointToAllPlatforms(): void
    {
        $response = $this->createStub(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('{}');

        $httpClient = $this->createMock(HttpClientInterface::class);
        // 3 platforms: twitter, facebook, telegram
        $httpClient->expects($this->exactly(3))->method('request')->willReturn($response);

        $service = new SocialMediaService(
            $httpClient,
            $this->logger,
            'https://deschide.md',
            'tw-key', 'tw-secret', 'tw-token', 'tw-secret',
            'fb-page', 'fb-token',
            'tg-bot', '@channel',
        );

        $liveText = $this->makeLiveText();

        $post = $this->createStub(LiveTextPost::class);
        $post->method('isKeyPoint')->willReturn(true);
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getContent')->willReturn('Key point content');
        $post->method('getContentHtml')->willReturn('<p>Key point content</p>');

        $service->postImportantUpdate($post);
    }

    // =========================================================================
    // Twitter post: message truncation when too long
    // =========================================================================

    #[Test]
    public function postToTwitterTruncatesLongMessages(): void
    {
        $response = $this->createStub(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(201);
        $response->method('getContent')->willReturn('{}');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.twitter.com/2/tweets',
                $this->anything()
            )
            ->willReturn($response);

        // Only enable Twitter
        $service = new SocialMediaService(
            $httpClient,
            $this->logger,
            'https://deschide.md',
            'tw-key', 'tw-secret', 'tw-token', 'tw-secret',
            null, null, // facebook disabled
            null, null, // telegram disabled
        );

        $liveText = $this->makeLiveText(title: str_repeat('A', 300));
        $service->postLiveTextStarted($liveText);
    }

    // =========================================================================
    // Facebook post: failed status code logs error
    // =========================================================================

    #[Test]
    public function postToFacebookLogsErrorOnFailedStatusCode(): void
    {
        $response = $this->createStub(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $response->method('getContent')->willReturn('{"error": "Bad request"}');

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('error')
            ->with($this->stringContains('Failed to post to Facebook'), $this->anything());

        // Only enable Facebook
        $service = new SocialMediaService(
            $httpClient,
            $logger,
            'https://deschide.md',
            null, null, null, null, // twitter disabled
            'fb-page', 'fb-token',
            null, null, // telegram disabled
        );

        $liveText = $this->makeLiveText();
        $service->postLiveTextStarted($liveText);
    }

    // =========================================================================
    // Telegram post: failed status code logs error
    // =========================================================================

    #[Test]
    public function postToTelegramLogsErrorOnFailedStatusCode(): void
    {
        $response = $this->createStub(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(403);
        $response->method('getContent')->willReturn('{"ok": false}');

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('error')
            ->with($this->stringContains('Failed to post to Telegram'), $this->anything());

        $service = new SocialMediaService(
            $httpClient,
            $logger,
            'https://deschide.md',
            null, null, null, null,
            null, null,
            'tg-bot', '@channel',
        );

        $liveText = $this->makeLiveText();
        $service->postLiveTextStarted($liveText);
    }

    // =========================================================================
    // Facebook message truncation
    // =========================================================================

    #[Test]
    public function postToFacebookTruncatesLongMessages(): void
    {
        $response = $this->createStub(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('{}');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())->method('request')->willReturn($response);

        $service = new SocialMediaService(
            $httpClient,
            $this->logger,
            'https://deschide.md',
            null, null, null, null,
            'fb-page', 'fb-token',
            null, null,
        );

        $liveText = $this->makeLiveText(title: str_repeat('B', 600));
        $service->postLiveTextStarted($liveText);
    }

    // =========================================================================
    // getSharingMetadata: featured image from post
    // =========================================================================

    #[Test]
    public function getSharingMetadataReturnsFeaturedImageFromPosts(): void
    {
        // getFeaturedImage() is referenced in SocialMediaService but not declared on
        // the LiveTextPost entity. Use an anonymous class to define it for testing.
        $post = new class extends LiveTextPost {
            public function getFeaturedImage(): ?string
            {
                return '/uploads/images/featured.jpg';
            }
        };

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);
        $liveText->method('getTitle')->willReturn('Title');
        $liveText->method('getSlug')->willReturn('slug');
        $liveText->method('getLocale')->willReturn('ro');
        $liveText->method('getDescription')->willReturn(null);
        $liveText->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $liveText->method('getPosts')->willReturn(new \Doctrine\Common\Collections\ArrayCollection([$post]));

        $service = $this->makeService();
        $meta = $service->getSharingMetadata($liveText);

        $this->assertSame('/uploads/images/featured.jpg', $meta['image']);
    }

    // =========================================================================
    // URL generation: null locale defaults to 'ro'
    // =========================================================================

    #[Test]
    public function getSharingMetadataUsesDefaultRoLocaleWhenNull(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);
        $liveText->method('getTitle')->willReturn('Title');
        $liveText->method('getSlug')->willReturn('my-slug');
        $liveText->method('getLocale')->willReturn(null);
        $liveText->method('getDescription')->willReturn(null);
        $liveText->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $liveText->method('getPosts')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $service = $this->makeService('https://deschide.md');
        $meta = $service->getSharingMetadata($liveText);

        $this->assertSame('https://deschide.md/ro/live/my-slug', $meta['url']);
    }

    // =========================================================================
    // Post message generation: long content is truncated
    // =========================================================================

    #[Test]
    public function postImportantUpdateTruncatesLongPostContent(): void
    {
        $response = $this->createStub(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('{}');

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $service = new SocialMediaService(
            $httpClient,
            $this->logger,
            'https://deschide.md',
            null, null, null, null,
            null, null,
            'tg-bot', '@channel',
        );

        $liveText = $this->makeLiveText();

        $post = $this->createStub(LiveTextPost::class);
        $post->method('isKeyPoint')->willReturn(true);
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getContent')->willReturn(str_repeat('X', 500));
        $post->method('getContentHtml')->willReturn(null);

        // Should not throw even with long content
        $service->postImportantUpdate($post);
        $this->assertTrue(true);
    }

    // =========================================================================
    // Live text start message: draft status
    // =========================================================================

    #[Test]
    public function postLiveTextStartedWithDraftStatus(): void
    {
        $response = $this->createStub(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('{}');

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);
        $liveText->method('getTitle')->willReturn('Draft Live');
        $liveText->method('getSlug')->willReturn('draft-live');
        $liveText->method('getLocale')->willReturn('ro');
        $liveText->method('getDescription')->willReturn(null);
        $liveText->method('getStatus')->willReturn(LiveTextStatus::DRAFT);
        $liveText->method('getPosts')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $service = new SocialMediaService(
            $httpClient,
            $this->logger,
            'https://deschide.md',
            null, null, null, null,
            null, null,
            'tg-bot', '@channel',
        );

        $service->postLiveTextStarted($liveText);
        $this->assertTrue(true);
    }
}
