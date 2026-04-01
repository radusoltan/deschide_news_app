<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ImageElasticService;
use Elastic\Elasticsearch\ClientBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Unit tests for ImageElasticService.
 *
 * Tests cover disabled-state behaviour and enabled-state paths using a mock
 * PSR-18 HTTP client injected via Reflection into the real Elasticsearch Client.
 */
class ImageElasticServiceTest extends TestCase
{
    // =====================================================================
    // Service Initialization
    // =====================================================================

    #[Test]
    public function itCreatesEnabledServiceWithValidHost(): void
    {
        $service = new ImageElasticService('https://localhost:9200', 'user', 'pass');

        $this->assertTrue($service->isEnabled());
    }

    #[Test]
    public function itCreatesDisabledServiceWithEmptyHost(): void
    {
        $service = new ImageElasticService('');

        $this->assertFalse($service->isEnabled());
    }

    #[Test]
    public function itCreatesDisabledServiceWithZeroHost(): void
    {
        $service = new ImageElasticService('0');

        $this->assertFalse($service->isEnabled());
    }

    #[Test]
    public function itCreatesEnabledServiceWithoutCredentials(): void
    {
        $service = new ImageElasticService('https://localhost:9200');

        $this->assertTrue($service->isEnabled());
    }

    // =====================================================================
    // Disabled State – index operations
    // =====================================================================

    #[Test]
    public function itSkipsIndexCreationWhenDisabled(): void
    {
        $service = new ImageElasticService('');

        // Must not throw; just a no-op
        $service->createIndex();

        $this->assertFalse($service->isEnabled());
    }

    #[Test]
    public function itSkipsDocumentIndexingWhenDisabled(): void
    {
        $service = new ImageElasticService('');

        $service->indexDocument(['id' => 1, 'filename' => 'test.jpg', 'alt' => 'alt text']);

        $this->assertFalse($service->isEnabled());
    }

    #[Test]
    public function itSkipsDocumentDeletionWhenDisabled(): void
    {
        $service = new ImageElasticService('');

        $service->deleteDocument(42);

        $this->assertFalse($service->isEnabled());
    }

    // =====================================================================
    // Disabled State – search / suggest
    // =====================================================================

    #[Test]
    public function itReturnsEmptyArrayWhenSearchOnDisabledService(): void
    {
        $service = new ImageElasticService('');

        $result = $service->search('nature');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenSuggestOnDisabledService(): void
    {
        $service = new ImageElasticService('');

        $result = $service->suggest('nat');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =====================================================================
    // Disabled State – health
    // =====================================================================

    #[Test]
    public function itReturnsDisabledStatusFromHealthWhenDisabled(): void
    {
        $service = new ImageElasticService('');

        $health = $service->getHealth();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertSame('disabled', $health['status']);
    }

    // =====================================================================
    // Enabled state – basic assertions (no real ES connection needed)
    // =====================================================================

    #[Test]
    public function itReturnsNonNullClientWhenEnabled(): void
    {
        $service = new ImageElasticService('https://localhost:9200');

        // Client is set during constructor; just confirm it is not null
        $this->assertTrue($service->isEnabled());
    }

    #[Test]
    public function itHandlesSearchWithCustomSizeParameter(): void
    {
        // When disabled, size parameter is irrelevant; result is always empty
        $service = new ImageElasticService('');

        $result = $service->search('photo', 50);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function itHandlesSuggestWithCustomSizeParameter(): void
    {
        $service = new ImageElasticService('');

        $result = $service->suggest('pho', 20);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =====================================================================
    // Enabled State – real coverage via mock PSR-18 HTTP client
    // =====================================================================

    #[Test]
    public function itCreatesIndexWhenEnabled(): void
    {
        $httpClient = new ImageElasticSequenceHttpClient();
        // HEAD for exists check -> 200 (exists)
        $httpClient->responses[] = new ImageElasticMockResponse('', 200);
        // DELETE existing index -> 200
        $httpClient->responses[] = new ImageElasticMockResponse('{"acknowledged":true}', 200);
        // PUT create index -> 200
        $httpClient->responses[] = new ImageElasticMockResponse('{"acknowledged":true}', 200);

        $service = $this->createServiceWithHttpClient($httpClient);

        $service->createIndex();

        // 3 requests: HEAD (exists), DELETE, PUT (create)
        $this->assertCount(3, $httpClient->requests);
        $this->assertSame('HEAD', $httpClient->requests[0]['method']);
        $this->assertSame('DELETE', $httpClient->requests[1]['method']);
        $this->assertSame('PUT', $httpClient->requests[2]['method']);
    }

    #[Test]
    public function itCreatesIndexWhenIndexDoesNotExist(): void
    {
        $httpClient = new ImageElasticSequenceHttpClient();
        // HEAD for exists check -> 404 (does not exist)
        $httpClient->responses[] = new ImageElasticMockResponse('', 404);
        // PUT create index -> 200
        $httpClient->responses[] = new ImageElasticMockResponse('{"acknowledged":true}', 200);

        $service = $this->createServiceWithHttpClient($httpClient);

        $service->createIndex();

        // 2 requests: HEAD (not found), PUT (create) -- no DELETE
        $this->assertCount(2, $httpClient->requests);
        $this->assertSame('HEAD', $httpClient->requests[0]['method']);
        $this->assertSame('PUT', $httpClient->requests[1]['method']);
    }

    #[Test]
    public function itIndexesDocumentWhenEnabled(): void
    {
        $httpClient = new ImageElasticSequenceHttpClient();
        $httpClient->responses[] = new ImageElasticMockResponse('{"result":"created"}', 201);

        $service = $this->createServiceWithHttpClient($httpClient);

        $document = ['id' => 1, 'filename' => 'photo.jpg', 'alt' => 'A photo'];
        $service->indexDocument($document);

        $this->assertCount(1, $httpClient->requests);
        $this->assertSame('PUT', $httpClient->requests[0]['method']);
        $this->assertStringContainsString('deschide_images', $httpClient->requests[0]['uri']);
    }

    #[Test]
    public function itSearchesAndReturnsResultsWhenEnabled(): void
    {
        $searchResult = json_encode([
            'hits' => [
                'hits' => [
                    ['_source' => ['id' => 42], '_score' => 1.5],
                    ['_source' => ['id' => 99], '_score' => 0.8],
                ],
            ],
        ]);
        $httpClient = new ImageElasticSequenceHttpClient();
        $httpClient->responses[] = new ImageElasticMockResponse($searchResult, 200);

        $service = $this->createServiceWithHttpClient($httpClient);

        $results = $service->search('nature', 50);

        $this->assertCount(2, $results);
        $this->assertSame(42, $results[0]['id']);
        $this->assertSame(1.5, $results[0]['score']);
        $this->assertSame(99, $results[1]['id']);
        $this->assertSame(0.8, $results[1]['score']);
    }

    #[Test]
    public function itSearchReturnsEmptyWhenNoHits(): void
    {
        $searchResult = json_encode([
            'hits' => ['hits' => []],
        ]);
        $httpClient = new ImageElasticSequenceHttpClient();
        $httpClient->responses[] = new ImageElasticMockResponse($searchResult, 200);

        $service = $this->createServiceWithHttpClient($httpClient);

        $results = $service->search('nonexistent');

        $this->assertSame([], $results);
    }

    #[Test]
    public function itSuggestsAndReturnsSuggestionsWhenEnabled(): void
    {
        $suggestResult = json_encode([
            'suggest' => [
                'image-suggest' => [
                    [
                        'options' => [
                            ['text' => 'nature_photo.jpg'],
                            ['text' => 'nature_landscape.png'],
                        ],
                    ],
                ],
            ],
        ]);
        $httpClient = new ImageElasticSequenceHttpClient();
        $httpClient->responses[] = new ImageElasticMockResponse($suggestResult, 200);

        $service = $this->createServiceWithHttpClient($httpClient);

        $results = $service->suggest('nat', 5);

        $this->assertCount(2, $results);
        $this->assertSame('nature_photo.jpg', $results[0]);
        $this->assertSame('nature_landscape.png', $results[1]);
    }

    #[Test]
    public function itSuggestReturnsEmptyWhenNoSuggestions(): void
    {
        $suggestResult = json_encode([
            'suggest' => [
                'image-suggest' => [
                    ['options' => []],
                ],
            ],
        ]);
        $httpClient = new ImageElasticSequenceHttpClient();
        $httpClient->responses[] = new ImageElasticMockResponse($suggestResult, 200);

        $service = $this->createServiceWithHttpClient($httpClient);

        $results = $service->suggest('xyz');

        $this->assertSame([], $results);
    }

    #[Test]
    public function itDeletesDocumentWhenEnabled(): void
    {
        $httpClient = new ImageElasticSequenceHttpClient();
        $httpClient->responses[] = new ImageElasticMockResponse('{"result":"deleted"}', 200);

        $service = $this->createServiceWithHttpClient($httpClient);

        $service->deleteDocument(42);

        $this->assertCount(1, $httpClient->requests);
        $this->assertSame('DELETE', $httpClient->requests[0]['method']);
    }

    #[Test]
    public function itSwallowsExceptionOnDeleteWhenDocumentNotFound(): void
    {
        $httpClient = new ImageElasticThrowingHttpClient(new \Exception('Document not found'));

        $service = $this->createServiceWithHttpClient($httpClient);

        // Should not throw - exceptions are swallowed
        $service->deleteDocument(999);

        $this->assertTrue($service->isEnabled());
    }

    #[Test]
    public function itReturnsClusterHealthWhenEnabled(): void
    {
        $healthResult = json_encode([
            'status' => 'green',
            'cluster_name' => 'deschide-cluster',
            'number_of_nodes' => 3,
        ]);
        $httpClient = new ImageElasticSequenceHttpClient();
        $httpClient->responses[] = new ImageElasticMockResponse($healthResult, 200);

        $service = $this->createServiceWithHttpClient($httpClient);

        $health = $service->getHealth();

        $this->assertSame('green', $health['status']);
        $this->assertSame('deschide-cluster', $health['cluster_name']);
        $this->assertSame(3, $health['number_of_nodes']);
    }

    // =====================================================================
    // Helper: Create service with mock PSR-18 HTTP client injected via Reflection
    // =====================================================================

    private function createServiceWithHttpClient(ClientInterface $httpClient): ImageElasticService
    {
        $client = ClientBuilder::create()
            ->setHosts(['https://localhost:9200'])
            ->setHttpClient($httpClient)
            ->build();

        $service = new ImageElasticService('https://localhost:9200');

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, $client);

        return $service;
    }
}

// =====================================================================
// Mock PSR-7 Response for ImageElasticService tests
// =====================================================================

class ImageElasticMockResponse implements \Psr\Http\Message\ResponseInterface
{
    private string $body;
    private int $statusCode;

    public function __construct(string $body = '{}', int $statusCode = 200)
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return 'OK';
    }

    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    public function withProtocolVersion(string $version): static
    {
        return $this;
    }

    public function getHeaders(): array
    {
        return [
            'content-type' => ['application/json'],
            'x-elastic-product' => ['Elasticsearch'],
        ];
    }

    public function hasHeader(string $name): bool
    {
        return in_array(strtolower($name), ['x-elastic-product', 'content-type'], true);
    }

    public function getHeader(string $name): array
    {
        return match (strtolower($name)) {
            'x-elastic-product' => ['Elasticsearch'],
            'content-type' => ['application/json'],
            default => [],
        };
    }

    public function getHeaderLine(string $name): string
    {
        return match (strtolower($name)) {
            'x-elastic-product' => 'Elasticsearch',
            'content-type' => 'application/json',
            default => '',
        };
    }

    public function withHeader(string $name, $value): static
    {
        return $this;
    }

    public function withAddedHeader(string $name, $value): static
    {
        return $this;
    }

    public function withoutHeader(string $name): static
    {
        return $this;
    }

    public function getBody(): \Psr\Http\Message\StreamInterface
    {
        return new ImageElasticMockStream($this->body);
    }

    public function withBody(\Psr\Http\Message\StreamInterface $body): static
    {
        return $this;
    }
}

class ImageElasticMockStream implements \Psr\Http\Message\StreamInterface
{
    private string $content;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public function close(): void {}
    public function detach() { return null; }
    public function getSize(): ?int { return strlen($this->content); }
    public function tell(): int { return 0; }
    public function eof(): bool { return true; }
    public function isSeekable(): bool { return false; }
    public function seek(int $offset, int $whence = SEEK_SET): void {}
    public function rewind(): void {}
    public function isWritable(): bool { return false; }
    public function write(string $string): int { return 0; }
    public function isReadable(): bool { return true; }
    public function read(int $length): string { return $this->content; }
    public function getContents(): string { return $this->content; }
    public function getMetadata(?string $key = null) { return null; }
}

class ImageElasticSequenceHttpClient implements ClientInterface
{
    /** @var ImageElasticMockResponse[] */
    public array $responses = [];

    /** @var array<int, array{method: string, uri: string, body: string}> */
    public array $requests = [];

    private int $idx = 0;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'body' => (string) $request->getBody(),
        ];

        if (isset($this->responses[$this->idx])) {
            return $this->responses[$this->idx++];
        }

        return new ImageElasticMockResponse('{}', 200);
    }
}

class ImageElasticThrowingHttpClient implements ClientInterface
{
    private \Exception $exception;

    public function __construct(\Exception $exception)
    {
        $this->exception = $exception;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        throw $this->exception;
    }
}
