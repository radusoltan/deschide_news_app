<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\JsonLogFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class JsonLogFormatterTest extends TestCase
{
    private RequestStack $requestStack;
    private JsonLogFormatter $formatter;

    protected function setUp(): void
    {
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->formatter = new JsonLogFormatter($this->requestStack);
    }

    public function testFormatWithNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: [],
        );

        $result = $this->formatter->format($record);
        $decoded = json_decode($result, true);

        $this->assertIsArray($decoded);
        $this->assertSame('Test message', $decoded['message']);
        $this->assertSame('test', $decoded['channel']);
        $this->assertArrayHasKey('extra', $decoded);
        $this->assertSame('ro', $decoded['extra']['locale']);
        $this->assertNull($decoded['extra']['client_ip']);
        $this->assertNull($decoded['extra']['method']);
        $this->assertNull($decoded['extra']['uri']);
        // request_id should be a 16-char hex string when no request
        $this->assertSame(16, strlen($decoded['extra']['request_id']));
    }

    public function testFormatWithRequest(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $request->headers->set('X-Request-Id', 'test-request-id-123');
        $request->setLocale('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Warning,
            message: 'Warning message',
            context: ['key' => 'value'],
            extra: [],
        );

        $result = $this->formatter->format($record);
        $decoded = json_decode($result, true);

        $this->assertIsArray($decoded);
        $this->assertSame('Warning message', $decoded['message']);
        $this->assertSame('app', $decoded['channel']);
        $this->assertSame('test-request-id-123', $decoded['extra']['request_id']);
        $this->assertSame('en', $decoded['extra']['locale']);
        $this->assertSame('GET', $decoded['extra']['method']);
        $this->assertSame('/api/articles', $decoded['extra']['uri']);
    }

    public function testFormatUsesRequestAttributeRequestIdAsFallback(): void
    {
        $request = Request::create('/api/test', 'POST');
        $request->attributes->set('_request_id', 'attr-request-id');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: [],
        );

        $result = $this->formatter->format($record);
        $decoded = json_decode($result, true);

        $this->assertSame('attr-request-id', $decoded['extra']['request_id']);
    }

    public function testFormatPreservesExistingExtra(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: ['existing_key' => 'existing_value'],
        );

        $result = $this->formatter->format($record);
        $decoded = json_decode($result, true);

        $this->assertSame('existing_value', $decoded['extra']['existing_key']);
        $this->assertArrayHasKey('request_id', $decoded['extra']);
        $this->assertArrayHasKey('locale', $decoded['extra']);
    }

    public function testFormatPreservesContext(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'Error occurred',
            context: ['error' => 'something bad', 'code' => 500],
            extra: [],
        );

        $result = $this->formatter->format($record);
        $decoded = json_decode($result, true);

        $this->assertSame('something bad', $decoded['context']['error']);
        $this->assertSame(500, $decoded['context']['code']);
    }

    public function testFormatOutputIsValidJson(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Debug,
            message: 'Debug message with "quotes" and special chars: <>&',
            context: [],
            extra: [],
        );

        $result = $this->formatter->format($record);
        $decoded = json_decode($result, true);

        $this->assertNotNull($decoded, 'Output should be valid JSON');
    }
}
