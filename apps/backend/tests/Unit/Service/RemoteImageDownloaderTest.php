<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Image;
use App\Service\RemoteImageDownloader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RemoteImageDownloaderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/remote_img_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/public/uploads/images/originals', 0755, true);
    }

    protected function tearDown(): void
    {
        // Cleanup temp files
        $files = glob($this->tmpDir . '/public/uploads/images/originals/*');
        if ($files) {
            array_map('unlink', $files);
        }
        @rmdir($this->tmpDir . '/public/uploads/images/originals');
        @rmdir($this->tmpDir . '/public/uploads/images');
        @rmdir($this->tmpDir . '/public/uploads');
        @rmdir($this->tmpDir . '/public');
        @rmdir($this->tmpDir);
    }

    #[Test]
    public function downloadsValidJpegImage(): void
    {
        // 1x1 JPEG
        $jpegContent = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAFBABAAAAAAAAAAAAAAAAAAAAf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhEDEQA/AKYH/9k=');

        $httpClient = new MockHttpClient(new MockResponse($jpegContent, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'image/jpeg'],
        ]));

        $downloader = new RemoteImageDownloader($httpClient, new NullLogger(), $this->tmpDir);
        $image = $downloader->download('https://example.com/photo.jpg');

        $this->assertInstanceOf(Image::class, $image);
        $this->assertSame('image/jpeg', $image->getMimeType());
        $this->assertStringEndsWith('.jpg', $image->getFilename());
        $this->assertGreaterThan(0, $image->getSize());
        $this->assertStringStartsWith('images/originals/', $image->getPath());
    }

    #[Test]
    public function returnsNullOn404(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('Not Found', [
            'http_code' => 404,
        ]));

        $downloader = new RemoteImageDownloader($httpClient, new NullLogger(), $this->tmpDir);
        $result = $downloader->download('https://example.com/missing.jpg');

        $this->assertNull($result);
    }

    #[Test]
    public function returnsNullForInvalidContentType(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('<html></html>', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'text/html'],
        ]));

        $downloader = new RemoteImageDownloader($httpClient, new NullLogger(), $this->tmpDir);
        $result = $downloader->download('https://example.com/page.html');

        $this->assertNull($result);
    }

    #[Test]
    public function returnsNullOnEmptyResponse(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'image/png'],
        ]));

        $downloader = new RemoteImageDownloader($httpClient, new NullLogger(), $this->tmpDir);
        $result = $downloader->download('https://example.com/empty.png');

        $this->assertNull($result);
    }

    #[Test]
    public function returnsNullOnNetworkError(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', [
            'error' => 'Connection timed out',
        ]));

        $downloader = new RemoteImageDownloader($httpClient, new NullLogger(), $this->tmpDir);
        $result = $downloader->download('https://unreachable.example.com/img.jpg');

        $this->assertNull($result);
    }
}
