<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\VideoShow;
use App\Entity\YouTubeVideo;
use App\Repository\VideoShowRepository;
use App\Repository\YouTubeVideoRepository;
use App\Service\YouTubeSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class YouTubeSyncServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private EntityManagerInterface $entityManager;
    private VideoShowRepository $videoShowRepository;
    private YouTubeVideoRepository $youTubeVideoRepository;
    private LoggerInterface $logger;
    private YouTubeSyncService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->videoShowRepository = $this->createStub(VideoShowRepository::class);
        $this->youTubeVideoRepository = $this->createStub(YouTubeVideoRepository::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->service = new YouTubeSyncService(
            $this->httpClient,
            $this->entityManager,
            $this->videoShowRepository,
            $this->youTubeVideoRepository,
            $this->logger,
            'test-api-key'
        );
    }

    private function createJsonResponse(int $statusCode, array $data): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('toArray')->willReturn($data);

        return $response;
    }

    // --- syncFromChannel ---

    public function testSyncFromChannelReturnsZeroStatsOnFailedFetch(): void
    {
        $response = $this->createJsonResponse(500, []);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->syncFromChannel('UC123');

        $this->assertSame(0, $result['new']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['errors']);
    }

    public function testSyncFromChannelReturnsZeroStatsWhenNoUploadsPlaylist(): void
    {
        $response = $this->createJsonResponse(200, [
            'items' => [
                ['contentDetails' => ['relatedPlaylists' => []]],
            ],
        ]);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->syncFromChannel('UC123');

        $this->assertSame(0, $result['new']);
    }

    public function testSyncFromChannelHandlesException(): void
    {
        $this->httpClient->method('request')->willThrowException(new \Exception('API error'));

        $result = $this->service->syncFromChannel('UC123');

        $this->assertSame(0, $result['new']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['errors']);
    }

    // --- syncFromPlaylist ---

    public function testSyncFromPlaylistProcessesNewVideos(): void
    {
        // First call returns playlist items, second returns video details
        $playlistResponse = $this->createJsonResponse(200, [
            'items' => [
                ['contentDetails' => ['videoId' => 'abc123']],
            ],
        ]);

        $videoResponse = $this->createJsonResponse(200, [
            'items' => [
                [
                    'id' => 'abc123',
                    'snippet' => [
                        'title' => 'Test Video',
                        'description' => 'Description',
                        'publishedAt' => '2026-01-15T12:00:00Z',
                        'thumbnails' => [
                            'medium' => ['url' => 'https://img.youtube.com/vi/abc123/mqdefault.jpg'],
                        ],
                    ],
                    'contentDetails' => ['duration' => 'PT5M30S'],
                    'statistics' => ['viewCount' => '1000', 'likeCount' => '50'],
                ],
            ],
        ]);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($playlistResponse, $videoResponse);

        $this->youTubeVideoRepository->method('findByYoutubeId')->willReturn(null);

        $result = $this->service->syncFromPlaylist('PL123', null, 10);

        $this->assertSame(1, $result['new']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['errors']);
    }

    public function testSyncFromPlaylistUpdatesExistingVideos(): void
    {
        $playlistResponse = $this->createJsonResponse(200, [
            'items' => [
                ['contentDetails' => ['videoId' => 'existing123']],
            ],
        ]);

        $videoResponse = $this->createJsonResponse(200, [
            'items' => [
                [
                    'id' => 'existing123',
                    'snippet' => [
                        'title' => 'Updated Title',
                        'description' => 'Updated',
                        'publishedAt' => '2026-01-15T12:00:00Z',
                        'thumbnails' => [],
                    ],
                    'contentDetails' => ['duration' => 'PT10M'],
                    'statistics' => ['viewCount' => '5000', 'likeCount' => '200'],
                ],
            ],
        ]);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($playlistResponse, $videoResponse);

        $existingVideo = $this->createStub(YouTubeVideo::class);
        $this->youTubeVideoRepository->method('findByYoutubeId')->willReturn($existingVideo);

        $result = $this->service->syncFromPlaylist('PL123');

        $this->assertSame(0, $result['new']);
        $this->assertSame(1, $result['updated']);
    }

    public function testSyncFromPlaylistRespectsMaxResults(): void
    {
        $playlistResponse = $this->createJsonResponse(200, [
            'items' => [
                ['contentDetails' => ['videoId' => 'vid1']],
                ['contentDetails' => ['videoId' => 'vid2']],
                ['contentDetails' => ['videoId' => 'vid3']],
            ],
        ]);

        $videoResponse = $this->createJsonResponse(200, [
            'items' => [
                [
                    'id' => 'vid1',
                    'snippet' => ['title' => 'V1', 'description' => '', 'thumbnails' => []],
                    'contentDetails' => ['duration' => 'PT1M'],
                    'statistics' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($playlistResponse, $videoResponse);

        $this->youTubeVideoRepository->method('findByYoutubeId')->willReturn(null);

        $result = $this->service->syncFromPlaylist('PL123', null, 1);

        $this->assertSame(1, $result['new']);
    }

    public function testSyncFromPlaylistHandlesEmptyPlaylist(): void
    {
        $response = $this->createJsonResponse(200, ['items' => []]);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->syncFromPlaylist('PL_EMPTY');

        $this->assertSame(0, $result['new']);
        $this->assertSame(0, $result['updated']);
    }

    // --- updateVideoStats ---

    public function testUpdateVideoStatsReturnsZeroWhenNoVideosNeedUpdate(): void
    {
        $this->youTubeVideoRepository->method('findNeedingStatsUpdate')->willReturn([]);

        $result = $this->service->updateVideoStats();

        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['errors']);
    }

    public function testUpdateVideoStatsUpdatesExistingVideos(): void
    {
        $video = $this->createStub(YouTubeVideo::class);
        $video->method('getYoutubeId')->willReturn('abc123');

        $this->youTubeVideoRepository->method('findNeedingStatsUpdate')->willReturn([$video]);

        $response = $this->createJsonResponse(200, [
            'items' => [
                [
                    'id' => 'abc123',
                    'statistics' => ['viewCount' => '10000', 'likeCount' => '500'],
                ],
            ],
        ]);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->updateVideoStats();

        $this->assertSame(1, $result['updated']);
    }

    public function testUpdateVideoStatsHandlesApiFailure(): void
    {
        $video = $this->createStub(YouTubeVideo::class);
        $video->method('getYoutubeId')->willReturn('abc123');

        $this->youTubeVideoRepository->method('findNeedingStatsUpdate')->willReturn([$video]);

        $response = $this->createJsonResponse(500, []);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->updateVideoStats();

        $this->assertSame(0, $result['updated']);
    }

    // --- resolveChannelId ---

    public function testResolveChannelIdReturnsDirectChannelId(): void
    {
        $result = $this->service->resolveChannelId('UCabcdefgh12345');

        $this->assertSame('UCabcdefgh12345', $result);
    }

    public function testResolveChannelIdResolvesHandle(): void
    {
        $response = $this->createJsonResponse(200, [
            'items' => [['id' => 'UCresolved123']],
        ]);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->resolveChannelId('@deschide');

        $this->assertSame('UCresolved123', $result);
    }

    public function testResolveChannelIdResolvesChannelUrl(): void
    {
        $result = $this->service->resolveChannelId('https://www.youtube.com/channel/UCtest123');

        $this->assertSame('UCtest123', $result);
    }

    public function testResolveChannelIdResolvesHandleUrl(): void
    {
        $response = $this->createJsonResponse(200, [
            'items' => [['id' => 'UCfromhandle']],
        ]);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->resolveChannelId('https://www.youtube.com/@deschide');

        $this->assertSame('UCfromhandle', $result);
    }

    public function testResolveChannelIdReturnsNullForUnknownFormat(): void
    {
        $result = $this->service->resolveChannelId('some-random-string');

        $this->assertNull($result);
    }

    public function testResolveChannelIdReturnsNullWhenHandleNotFound(): void
    {
        $response = $this->createJsonResponse(200, ['items' => []]);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->resolveChannelId('@nonexistent');

        $this->assertNull($result);
    }

    public function testResolveChannelIdReturnsNullOnApiError(): void
    {
        $response = $this->createJsonResponse(403, []);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->resolveChannelId('@blocked');

        $this->assertNull($result);
    }

    // --- syncFromChannel successful flow ---

    public function testSyncFromChannelDelegatesToSyncFromPlaylist(): void
    {
        // Channel response with uploads playlist
        $channelResponse = $this->createJsonResponse(200, [
            'items' => [
                ['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU_uploads_123']]],
            ],
        ]);

        // Playlist response with one video
        $playlistResponse = $this->createJsonResponse(200, [
            'items' => [
                ['contentDetails' => ['videoId' => 'vid1']],
            ],
        ]);

        // Video details
        $videoResponse = $this->createJsonResponse(200, [
            'items' => [
                [
                    'id' => 'vid1',
                    'snippet' => ['title' => 'V1', 'description' => '', 'thumbnails' => [], 'publishedAt' => '2026-01-01T00:00:00Z'],
                    'contentDetails' => ['duration' => 'PT2M'],
                    'statistics' => ['viewCount' => '100'],
                ],
            ],
        ]);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($channelResponse, $playlistResponse, $videoResponse);

        $this->youTubeVideoRepository->method('findByYoutubeId')->willReturn(null);

        $result = $this->service->syncFromChannel('UC123');

        $this->assertSame(1, $result['new']);
    }

    public function testSyncFromChannelWithEmptyItems(): void
    {
        $response = $this->createJsonResponse(200, ['items' => []]);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->syncFromChannel('UC123');

        $this->assertSame(0, $result['new']);
    }

    // --- syncFromPlaylist edge cases ---

    public function testSyncFromPlaylistHandlesNullPlaylistItems(): void
    {
        $response = $this->createJsonResponse(500, []);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->syncFromPlaylist('PL_BROKEN');

        $this->assertSame(0, $result['new']);
        $this->assertSame(0, $result['updated']);
    }

    public function testSyncFromPlaylistHandlesNullVideoIds(): void
    {
        $playlistResponse = $this->createJsonResponse(200, [
            'items' => [
                ['contentDetails' => []], // missing videoId
            ],
        ]);

        $this->httpClient->method('request')->willReturn($playlistResponse);

        $result = $this->service->syncFromPlaylist('PL123');

        // empty videoIds = skip, no nextPageToken = stop
        $this->assertSame(0, $result['new']);
    }

    public function testSyncFromPlaylistHandlesNullVideosData(): void
    {
        $playlistResponse = $this->createJsonResponse(200, [
            'items' => [
                ['contentDetails' => ['videoId' => 'vid1']],
            ],
        ]);

        // First call returns playlist, second returns 500 (videos fetch fails)
        $failedResponse = $this->createJsonResponse(500, []);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($playlistResponse, $failedResponse);

        $result = $this->service->syncFromPlaylist('PL123');

        $this->assertSame(0, $result['new']);
    }

    public function testSyncFromPlaylistCatchesExceptionInProcessVideo(): void
    {
        $playlistResponse = $this->createJsonResponse(200, [
            'items' => [
                ['contentDetails' => ['videoId' => 'vid1']],
            ],
        ]);

        $videoResponse = $this->createJsonResponse(200, [
            'items' => [
                [
                    'id' => 'vid1',
                    'snippet' => ['title' => 'V1', 'publishedAt' => 'INVALID_DATE'],
                    'contentDetails' => [],
                    'statistics' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($playlistResponse, $videoResponse);

        $this->youTubeVideoRepository->method('findByYoutubeId')->willReturn(null);

        $result = $this->service->syncFromPlaylist('PL123');

        $this->assertSame(1, $result['errors']);
    }

    public function testSyncFromPlaylistWithVideoShow(): void
    {
        $playlistResponse = $this->createJsonResponse(200, [
            'items' => [
                ['contentDetails' => ['videoId' => 'vid1']],
            ],
        ]);

        $videoResponse = $this->createJsonResponse(200, [
            'items' => [
                [
                    'id' => 'vid1',
                    'snippet' => [
                        'title' => 'Show Episode',
                        'description' => 'Ep desc',
                        'thumbnails' => [
                            'high' => ['url' => 'https://img.youtube.com/high.jpg'],
                            'default' => ['url' => 'https://img.youtube.com/default.jpg'],
                        ],
                        'publishedAt' => '2026-03-15T10:00:00Z',
                    ],
                    'contentDetails' => ['duration' => 'PT1H30M45S'],
                    'statistics' => ['viewCount' => '5000', 'likeCount' => '200'],
                ],
            ],
        ]);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($playlistResponse, $videoResponse);

        $this->youTubeVideoRepository->method('findByYoutubeId')->willReturn(null);

        $videoShow = $this->createStub(VideoShow::class);
        $result = $this->service->syncFromPlaylist('PL123', $videoShow);

        $this->assertSame(1, $result['new']);
    }

    public function testSyncFromPlaylistHandlesGlobalException(): void
    {
        $this->httpClient->method('request')
            ->willThrowException(new \Exception('Network error'));

        $result = $this->service->syncFromPlaylist('PL123');

        $this->assertSame(0, $result['new']);
        $this->assertSame(0, $result['updated']);
    }

    // --- updateVideoStats edge cases ---

    public function testUpdateVideoStatsSkipsVideoNotInApiResponse(): void
    {
        $video1 = $this->createStub(YouTubeVideo::class);
        $video1->method('getYoutubeId')->willReturn('vid1');

        $video2 = $this->createStub(YouTubeVideo::class);
        $video2->method('getYoutubeId')->willReturn('vid2_missing');

        $this->youTubeVideoRepository->method('findNeedingStatsUpdate')->willReturn([$video1, $video2]);

        $response = $this->createJsonResponse(200, [
            'items' => [
                [
                    'id' => 'vid1',
                    'statistics' => ['viewCount' => '100', 'likeCount' => '10'],
                ],
                // vid2_missing not in response
            ],
        ]);
        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->updateVideoStats();

        $this->assertSame(1, $result['updated']);
    }

    public function testUpdateVideoStatsWithCustomParameters(): void
    {
        $this->youTubeVideoRepository->method('findNeedingStatsUpdate')->willReturn([]);

        $result = $this->service->updateVideoStats(100, 24);

        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['errors']);
    }

    // --- syncFromPlaylist pagination ---

    public function testSyncFromPlaylistHandlesPagination(): void
    {
        // Page 1 with nextPageToken
        $page1Response = $this->createJsonResponse(200, [
            'items' => [
                ['contentDetails' => ['videoId' => 'vid1']],
            ],
            'nextPageToken' => 'page2token',
        ]);

        $vid1Response = $this->createJsonResponse(200, [
            'items' => [
                [
                    'id' => 'vid1',
                    'snippet' => ['title' => 'V1', 'description' => '', 'thumbnails' => []],
                    'contentDetails' => ['duration' => 'PT1M'],
                    'statistics' => [],
                ],
            ],
        ]);

        // Page 2 with no nextPageToken
        $page2Response = $this->createJsonResponse(200, [
            'items' => [
                ['contentDetails' => ['videoId' => 'vid2']],
            ],
        ]);

        $vid2Response = $this->createJsonResponse(200, [
            'items' => [
                [
                    'id' => 'vid2',
                    'snippet' => ['title' => 'V2', 'description' => '', 'thumbnails' => []],
                    'contentDetails' => ['duration' => 'PT2M'],
                    'statistics' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($page1Response, $vid1Response, $page2Response, $vid2Response);

        $this->youTubeVideoRepository->method('findByYoutubeId')->willReturn(null);

        $result = $this->service->syncFromPlaylist('PL123', null, 10);

        $this->assertSame(2, $result['new']);
    }

    // --- resolveChannelId URL parsing ---

    public function testResolveChannelIdWithYoutubeUrlNoMatch(): void
    {
        $result = $this->service->resolveChannelId('https://www.youtube.com/watch?v=abc123');

        $this->assertNull($result);
    }
}
