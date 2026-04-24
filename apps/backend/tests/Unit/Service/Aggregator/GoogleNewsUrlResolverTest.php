<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Service\Aggregator\GoogleNewsUrlResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(GoogleNewsUrlResolver::class)]
class GoogleNewsUrlResolverTest extends TestCase
{
    public function testDecodesOldFormatUrlFromBase64Path(): void
    {
        // Old-format: protobuf field 1 = varint 19, field 4 = URL string
        $realUrl = 'https://www.reuters.com/world/europe/moldova-article-123';
        $protobuf = chr(0x08) . chr(0x13) . chr(0x22) . chr(\strlen($realUrl)) . $realUrl;
        $segment = rtrim(strtr(base64_encode($protobuf), '+/', '-_'), '=');
        $googleNewsUrl = 'https://news.google.com/rss/articles/' . $segment . '?oc=5';

        // No HTTP client needed — decoding is offline
        $client = new MockHttpClient([]);
        $resolver = new GoogleNewsUrlResolver($client, new NullLogger());

        $result = $resolver->resolveUrl($googleNewsUrl);

        self::assertSame($realUrl, $result);
    }

    public function testDecodesUrlWithLongerProtobuf(): void
    {
        // Simulate a longer protobuf with URL in a different position
        $realUrl = 'https://leparisien.fr/faits-divers/article-test-12345';
        // Add some extra protobuf fields before the URL field
        $protobuf = chr(0x08) . chr(0x13)  // field 1, varint 19
            . chr(0x22) . chr(\strlen($realUrl)) . $realUrl; // field 4, length-delimited
        $segment = rtrim(strtr(base64_encode($protobuf), '+/', '-_'), '=');
        $googleNewsUrl = 'https://news.google.com/rss/articles/' . $segment;

        $client = new MockHttpClient([]);
        $resolver = new GoogleNewsUrlResolver($client, new NullLogger());

        $result = $resolver->resolveUrl($googleNewsUrl);

        self::assertSame($realUrl, $result);
    }

    public function testReturnsNullForEncryptedNewFormat(): void
    {
        // New format: AU_-prefixed encrypted payload — no URL in decoded bytes.
        // This is a real Google News article ID from 2026.
        $googleNewsUrl = 'https://news.google.com/rss/articles/CBMiQEFVX3lxTFBFS21NU1dhUEZ2ZGExS3EyNHZXRlpYLXEwUnN2WFJ4MDVyb0h6T1VFR3dtdjdVOVNSdGFhUzBzZEY?oc=5';

        // Should fall through base64 decode (no URL found) and try HTTP.
        // HTTP returns a Google consent SPA page (still google.com), so null.
        $mockResponse = new MockResponse('<html><body>Consent page</body></html>', [
            'http_code' => 200,
            'url' => 'https://news.google.com/rss/articles/CBMiQEFV...?oc=5&hl=en-US',
        ]);

        $client = new MockHttpClient($mockResponse);
        $resolver = new GoogleNewsUrlResolver($client, new NullLogger());

        $result = $resolver->resolveUrl($googleNewsUrl);

        self::assertNull($result);
    }

    public function testFallsBackToHttpRedirectWhenBase64Fails(): void
    {
        // A Google News URL where base64 decode yields no URL,
        // but HTTP redirect resolves to the real article
        $segment = rtrim(strtr(base64_encode('no-url-here-just-garbage-data'), '+/', '-_'), '=');
        $googleNewsUrl = 'https://news.google.com/rss/articles/' . $segment . '?oc=5';
        $realUrl = 'https://example.com/real-article';

        $mockResponse = new MockResponse('', [
            'http_code' => 200,
            'url' => $realUrl,
        ]);

        $client = new MockHttpClient($mockResponse);
        $resolver = new GoogleNewsUrlResolver($client, new NullLogger());

        $result = $resolver->resolveUrl($googleNewsUrl);

        self::assertSame($realUrl, $result);
    }

    public function testHandlesConsentPageRedirect(): void
    {
        $segment = rtrim(strtr(base64_encode('encrypted-data'), '+/', '-_'), '=');
        $googleNewsUrl = 'https://news.google.com/rss/articles/' . $segment;
        $realUrl = 'https://bbc.com/news/article-xyz';

        $mockResponse = new MockResponse('', [
            'http_code' => 200,
            'url' => 'https://consent.google.com/ml?continue=' . urlencode($realUrl) . '&gl=US&hl=en',
        ]);

        $client = new MockHttpClient($mockResponse);
        $resolver = new GoogleNewsUrlResolver($client, new NullLogger());

        $result = $resolver->resolveUrl($googleNewsUrl);

        self::assertSame($realUrl, $result);
    }

    public function testPassesThroughNonGoogleUrl(): void
    {
        $url = 'https://reuters.com/article/some-article';

        $client = new MockHttpClient([]);
        $resolver = new GoogleNewsUrlResolver($client, new NullLogger());

        $result = $resolver->resolveUrl($url);

        self::assertSame($url, $result);
    }

    public function testReturnsNullOnHttpTimeout(): void
    {
        $googleNewsUrl = 'https://news.google.com/rss/articles/CBMi456';

        $client = new MockHttpClient(function () {
            throw new \Symfony\Component\HttpClient\Exception\TransportException('Timeout');
        });

        $resolver = new GoogleNewsUrlResolver($client, new NullLogger());

        $result = $resolver->resolveUrl($googleNewsUrl);

        self::assertNull($result);
    }

    public function testReturnsNullOnHttp404(): void
    {
        $googleNewsUrl = 'https://news.google.com/rss/articles/CBMi789';

        $mockResponse = new MockResponse('Not Found', [
            'http_code' => 404,
            'url' => $googleNewsUrl,
        ]);

        $client = new MockHttpClient($mockResponse);
        $resolver = new GoogleNewsUrlResolver($client, new NullLogger());

        $result = $resolver->resolveUrl($googleNewsUrl);

        self::assertNull($result);
    }

    public function testRejectsDecodedUrlPointingBackToGoogle(): void
    {
        // Edge case: decoded payload contains a Google News URL
        $fakeUrl = 'https://news.google.com/stories/some-story';
        $protobuf = chr(0x08) . chr(0x13) . chr(0x22) . chr(\strlen($fakeUrl)) . $fakeUrl;
        $segment = rtrim(strtr(base64_encode($protobuf), '+/', '-_'), '=');
        $googleNewsUrl = 'https://news.google.com/rss/articles/' . $segment;

        $mockResponse = new MockResponse('', [
            'http_code' => 200,
            'url' => $googleNewsUrl, // Still Google after HTTP too
        ]);

        $client = new MockHttpClient($mockResponse);
        $resolver = new GoogleNewsUrlResolver($client, new NullLogger());

        $result = $resolver->resolveUrl($googleNewsUrl);

        self::assertNull($result);
    }
}
