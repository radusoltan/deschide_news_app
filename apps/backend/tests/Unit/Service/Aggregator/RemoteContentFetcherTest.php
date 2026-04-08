<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Service\Aggregator\RemoteContentFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(RemoteContentFetcher::class)]
class RemoteContentFetcherTest extends TestCase
{
    public function testExtractsContentFromHtml(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head><title>Test Article Title</title></head>
<body>
<article>
<h1>Test Article Title</h1>
<p>This is the first paragraph of the article. It contains enough text to pass the minimum word count threshold that the readability extractor requires for valid content extraction.</p>
<p>This is the second paragraph with additional detail about the topic. The readability algorithm needs sufficient content to determine what is the main article body versus navigation, ads, and other page elements.</p>
<p>A third paragraph rounds out the article content nicely. It discusses conclusions and final thoughts on the matter at hand, providing closure to the reader.</p>
</article>
</body>
</html>
HTML;

        $mockResponse = new MockResponse($html, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'text/html; charset=utf-8'],
        ]);

        $client = new MockHttpClient($mockResponse);
        $fetcher = new RemoteContentFetcher($client, new NullLogger());

        $result = $fetcher->fetchAndExtract('https://example.com/article');

        self::assertNotNull($result);
        self::assertNotEmpty($result->htmlContent);
        self::assertNotEmpty($result->textContent);
        self::assertGreaterThan(20, $result->wordCount);
    }

    public function testReturnsNullOnHttpError(): void
    {
        $mockResponse = new MockResponse('Not Found', [
            'http_code' => 404,
            'response_headers' => ['content-type' => 'text/html'],
        ]);

        $client = new MockHttpClient($mockResponse);
        $fetcher = new RemoteContentFetcher($client, new NullLogger());

        $result = $fetcher->fetchAndExtract('https://example.com/missing');

        self::assertNull($result);
    }

    public function testReturnsNullOnNonHtmlResponse(): void
    {
        $mockResponse = new MockResponse('{"data": "json"}', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);

        $client = new MockHttpClient($mockResponse);
        $fetcher = new RemoteContentFetcher($client, new NullLogger());

        $result = $fetcher->fetchAndExtract('https://example.com/api');

        self::assertNull($result);
    }

    public function testReturnsNullOnNetworkError(): void
    {
        $client = new MockHttpClient(function () {
            throw new \Symfony\Component\HttpClient\Exception\TransportException('Connection refused');
        });

        $fetcher = new RemoteContentFetcher($client, new NullLogger());

        $result = $fetcher->fetchAndExtract('https://unreachable.example.com');

        self::assertNull($result);
    }

    public function testReturnsNullOnEmptyContent(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Empty</title></head><body><nav>Menu</nav></body></html>';

        $mockResponse = new MockResponse($html, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'text/html'],
        ]);

        $client = new MockHttpClient($mockResponse);
        $fetcher = new RemoteContentFetcher($client, new NullLogger());

        $result = $fetcher->fetchAndExtract('https://example.com/empty');

        self::assertNull($result);
    }
}
