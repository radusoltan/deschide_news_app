<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\VideoShow;
use App\Entity\YouTubeVideo;
use App\Repository\VideoShowRepository;
use App\Repository\YouTubeVideoRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for synchronizing videos from YouTube Data API v3
 */
class YouTubeSyncService
{
    private const API_BASE_URL = 'https://www.googleapis.com/youtube/v3';
    private const DEFAULT_MAX_RESULTS = 50;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly VideoShowRepository $videoShowRepository,
        private readonly YouTubeVideoRepository $youTubeVideoRepository,
        private readonly LoggerInterface $logger,
        private readonly string $youtubeApiKey,
    ) {
    }

    /**
     * Sync videos from a YouTube channel
     *
     * @return array{new: int, updated: int, errors: int}
     */
    public function syncFromChannel(
        string $channelId,
        ?VideoShow $videoShow = null,
        int $maxResults = self::DEFAULT_MAX_RESULTS,
    ): array {
        $stats = ['new' => 0, 'updated' => 0, 'errors' => 0];

        try {
            // Get uploads playlist ID from channel
            $channelData = $this->fetchChannelData($channelId);
            if (!$channelData) {
                $this->logger->error('Failed to fetch channel data', ['channelId' => $channelId]);
                return $stats;
            }

            $uploadsPlaylistId = $channelData['contentDetails']['relatedPlaylists']['uploads'] ?? null;
            if (!$uploadsPlaylistId) {
                $this->logger->error('Channel has no uploads playlist', ['channelId' => $channelId]);
                return $stats;
            }

            // Sync from the uploads playlist
            return $this->syncFromPlaylist($uploadsPlaylistId, $videoShow, $maxResults);
        } catch (\Exception $e) {
            $this->logger->error('Error syncing from channel', [
                'channelId' => $channelId,
                'error' => $e->getMessage(),
            ]);
            return $stats;
        }
    }

    /**
     * Sync videos from a YouTube playlist
     *
     * @return array{new: int, updated: int, errors: int}
     */
    public function syncFromPlaylist(
        string $playlistId,
        ?VideoShow $videoShow = null,
        int $maxResults = self::DEFAULT_MAX_RESULTS,
    ): array {
        $stats = ['new' => 0, 'updated' => 0, 'errors' => 0];
        $pageToken = null;
        $processedCount = 0;

        try {
            do {
                $playlistItems = $this->fetchPlaylistItems($playlistId, $pageToken);
                if (!$playlistItems) {
                    break;
                }

                $videoIds = [];
                foreach ($playlistItems['items'] ?? [] as $item) {
                    $videoIds[] = $item['contentDetails']['videoId'] ?? null;
                }
                $videoIds = array_filter($videoIds);

                if (empty($videoIds)) {
                    $pageToken = $playlistItems['nextPageToken'] ?? null;
                    continue;
                }

                // Fetch detailed video data
                $videosData = $this->fetchVideosData($videoIds);
                if (!$videosData) {
                    $pageToken = $playlistItems['nextPageToken'] ?? null;
                    continue;
                }

                // Process each video
                foreach ($videosData['items'] ?? [] as $videoData) {
                    try {
                        $result = $this->processVideo($videoData, $videoShow);
                        if ($result === 'new') {
                            $stats['new']++;
                        } elseif ($result === 'updated') {
                            $stats['updated']++;
                        }
                        $processedCount++;
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        $this->logger->error('Error processing video', [
                            'videoId' => $videoData['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }

                    if ($processedCount >= $maxResults) {
                        break 2;
                    }
                }

                $pageToken = $playlistItems['nextPageToken'] ?? null;
            } while ($pageToken && $processedCount < $maxResults);

            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Error syncing from playlist', [
                'playlistId' => $playlistId,
                'error' => $e->getMessage(),
            ]);
        }

        return $stats;
    }

    /**
     * Update video statistics (view count, like count) for existing videos
     *
     * @return array{updated: int, errors: int}
     */
    public function updateVideoStats(int $limit = 50, int $olderThanHours = 6): array
    {
        $stats = ['updated' => 0, 'errors' => 0];

        $olderThan = new DateTimeImmutable("-{$olderThanHours} hours");
        $videos = $this->youTubeVideoRepository->findNeedingStatsUpdate($olderThan, $limit);

        if (empty($videos)) {
            return $stats;
        }

        $videoIds = array_map(fn(YouTubeVideo $v) => $v->getYoutubeId(), $videos);
        $videosData = $this->fetchVideosData($videoIds);

        if (!$videosData) {
            return $stats;
        }

        $dataMap = [];
        foreach ($videosData['items'] ?? [] as $item) {
            $dataMap[$item['id']] = $item;
        }

        foreach ($videos as $video) {
            $data = $dataMap[$video->getYoutubeId()] ?? null;
            if (!$data) {
                continue;
            }

            try {
                $statistics = $data['statistics'] ?? [];
                $video->setViewCount((int) ($statistics['viewCount'] ?? 0));
                $video->setLikeCount((int) ($statistics['likeCount'] ?? 0));
                $video->setSyncedAt(new DateTimeImmutable());
                $stats['updated']++;
            } catch (\Exception $e) {
                $stats['errors']++;
                $this->logger->error('Error updating video stats', [
                    'videoId' => $video->getYoutubeId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->entityManager->flush();
        return $stats;
    }

    /**
     * Fetch channel data from YouTube API
     */
    private function fetchChannelData(string $channelId): ?array
    {
        $response = $this->httpClient->request('GET', self::API_BASE_URL . '/channels', [
            'query' => [
                'key' => $this->youtubeApiKey,
                'id' => $channelId,
                'part' => 'contentDetails,snippet',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $data = $response->toArray();
        return $data['items'][0] ?? null;
    }

    /**
     * Fetch playlist items from YouTube API
     */
    private function fetchPlaylistItems(string $playlistId, ?string $pageToken = null): ?array
    {
        $query = [
            'key' => $this->youtubeApiKey,
            'playlistId' => $playlistId,
            'part' => 'contentDetails,snippet',
            'maxResults' => 50,
        ];

        if ($pageToken) {
            $query['pageToken'] = $pageToken;
        }

        $response = $this->httpClient->request('GET', self::API_BASE_URL . '/playlistItems', [
            'query' => $query,
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        return $response->toArray();
    }

    /**
     * Fetch detailed video data from YouTube API
     *
     * @param string[] $videoIds
     */
    private function fetchVideosData(array $videoIds): ?array
    {
        if (empty($videoIds)) {
            return null;
        }

        $response = $this->httpClient->request('GET', self::API_BASE_URL . '/videos', [
            'query' => [
                'key' => $this->youtubeApiKey,
                'id' => implode(',', $videoIds),
                'part' => 'snippet,contentDetails,statistics',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        return $response->toArray();
    }

    /**
     * Process and save/update a video
     *
     * @return string|null 'new', 'updated', or null
     */
    private function processVideo(array $videoData, ?VideoShow $videoShow): ?string
    {
        $youtubeId = $videoData['id'];
        $snippet = $videoData['snippet'] ?? [];
        $contentDetails = $videoData['contentDetails'] ?? [];
        $statistics = $videoData['statistics'] ?? [];

        $existingVideo = $this->youTubeVideoRepository->findByYoutubeId($youtubeId);
        $isNew = $existingVideo === null;
        $video = $existingVideo ?? new YouTubeVideo();

        // Set basic info
        $video->setYoutubeId($youtubeId);
        $video->setTitle($snippet['title'] ?? '');
        $video->setDescription($snippet['description'] ?? null);

        // Set thumbnails
        $thumbnails = $snippet['thumbnails'] ?? [];
        $video->setThumbnailUrl(
            $thumbnails['maxres']['url']
            ?? $thumbnails['high']['url']
            ?? $thumbnails['medium']['url']
            ?? null
        );
        $video->setThumbnailMedium(
            $thumbnails['medium']['url']
            ?? $thumbnails['default']['url']
            ?? null
        );

        // Set published date
        if (!empty($snippet['publishedAt'])) {
            $video->setPublishedAt(new DateTimeImmutable($snippet['publishedAt']));
        }

        // Set duration
        if (!empty($contentDetails['duration'])) {
            $video->setDurationSeconds($this->parseDuration($contentDetails['duration']));
        }

        // Set statistics
        $video->setViewCount((int) ($statistics['viewCount'] ?? 0));
        $video->setLikeCount((int) ($statistics['likeCount'] ?? 0));

        // Set video show if provided
        if ($videoShow !== null) {
            $video->setVideoShow($videoShow);
        }

        // Mark as synced
        $video->setSyncedAt(new DateTimeImmutable());

        if ($isNew) {
            $this->entityManager->persist($video);
        }

        return $isNew ? 'new' : 'updated';
    }

    /**
     * Parse ISO 8601 duration to seconds
     *
     * @param string $duration Duration string like "PT1H30M45S" or "PT5M30S"
     */
    private function parseDuration(string $duration): int
    {
        $interval = new \DateInterval($duration);
        return ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }

    /**
     * Get channel ID from a channel URL or handle
     */
    public function resolveChannelId(string $channelInput): ?string
    {
        // If it's already a channel ID (starts with UC)
        if (str_starts_with($channelInput, 'UC')) {
            return $channelInput;
        }

        // If it's a handle (@username), we need to search for it
        if (str_starts_with($channelInput, '@')) {
            return $this->resolveHandleToChannelId(ltrim($channelInput, '@'));
        }

        // If it's a URL, extract the identifier
        if (str_contains($channelInput, 'youtube.com')) {
            if (preg_match('/youtube\.com\/@([^\/\?]+)/', $channelInput, $matches)) {
                return $this->resolveHandleToChannelId($matches[1]);
            }
            if (preg_match('/youtube\.com\/channel\/([^\/\?]+)/', $channelInput, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Resolve a YouTube handle to a channel ID
     */
    private function resolveHandleToChannelId(string $handle): ?string
    {
        $response = $this->httpClient->request('GET', self::API_BASE_URL . '/channels', [
            'query' => [
                'key' => $this->youtubeApiKey,
                'forHandle' => $handle,
                'part' => 'id',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $data = $response->toArray();
        return $data['items'][0]['id'] ?? null;
    }
}
