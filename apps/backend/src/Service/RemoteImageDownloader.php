<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Image;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RemoteImageDownloader
{
    private const TIMEOUT = 10;
    private const MAX_SIZE = 10 * 1024 * 1024; // 10 MB
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    /**
     * Download an image from a remote URL and create an Image entity.
     * Returns null on any failure (does NOT throw).
     */
    public function download(string $url): ?Image
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::TIMEOUT,
                'max_redirects' => 3,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->logger->warning('RemoteImageDownloader: HTTP {status}', [
                    'status' => $statusCode,
                    'url' => $url,
                ]);

                return null;
            }

            $headers = $response->getHeaders();
            $contentType = $headers['content-type'][0] ?? '';
            // Normalize content-type (strip charset etc.)
            $mimeType = trim(explode(';', $contentType)[0]);

            if (!isset(self::ALLOWED_TYPES[$mimeType])) {
                $this->logger->warning('RemoteImageDownloader: unsupported content type', [
                    'contentType' => $contentType,
                    'url' => $url,
                ]);

                return null;
            }

            $content = $response->getContent();
            $size = \strlen($content);

            if ($size === 0) {
                $this->logger->warning('RemoteImageDownloader: empty response', ['url' => $url]);

                return null;
            }

            if ($size > self::MAX_SIZE) {
                $this->logger->warning('RemoteImageDownloader: file too large', [
                    'size' => $size,
                    'url' => $url,
                ]);

                return null;
            }

            // Save to disk
            $extension = self::ALLOWED_TYPES[$mimeType];
            $filename = sprintf('remote_%d_%s.%s', time(), bin2hex(random_bytes(6)), $extension);

            $uploadDir = $this->projectDir . '/public/uploads/images/originals';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $destPath = $uploadDir . '/' . $filename;
            file_put_contents($destPath, $content);

            // Get dimensions
            $imageSize = @getimagesize($destPath);
            $width = $imageSize[0] ?? 0;
            $height = $imageSize[1] ?? 0;

            // Create Image entity (same pattern as PressReleaseApproveProcessor::attachImage)
            $image = new Image();
            $image->setFilename($filename);
            $image->setOriginalFilename(basename(parse_url($url, \PHP_URL_PATH) ?: $filename));
            $image->setPath('images/originals/' . $filename);
            $image->setMimeType($mimeType);
            $image->setSize($size);
            $image->setWidth($width);
            $image->setHeight($height);

            $this->logger->info('RemoteImageDownloader: image saved', [
                'filename' => $filename,
                'size' => $size,
                'dimensions' => "{$width}x{$height}",
                'url' => mb_substr($url, 0, 100),
            ]);

            return $image;
        } catch (\Throwable $e) {
            $this->logger->warning('RemoteImageDownloader: failed', [
                'url' => mb_substr($url, 0, 100),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
