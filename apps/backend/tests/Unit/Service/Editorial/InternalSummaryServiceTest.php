<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Service\Editorial\InternalSummaryService;
use App\Service\Ai\Provider\GeminiCliService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class InternalSummaryServiceTest extends TestCase
{
    private InternalSummaryService $service;

    protected function setUp(): void
    {
        // Use a dummy GeminiCliService — tests don't actually call Gemini
        $this->service = new InternalSummaryService(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), new NullLogger());
    }

    public function testGenerateSummaryReturnsNullForEmptyBody(): void
    {
        $result = $this->service->generateSummary('Test Title', '');

        self::assertNull($result);
    }

    public function testGenerateSummaryReturnsNullForShortBody(): void
    {
        $result = $this->service->generateSummary('Test Title', 'Very short text.');

        self::assertNull($result);
    }

    public function testGenerateSummaryReturnsNullForBodyUnderMinLength(): void
    {
        // Body under 100 chars should be skipped
        $shortBody = str_repeat('a', 99);
        $result = $this->service->generateSummary('Test Title', $shortBody);

        self::assertNull($result);
    }

    public function testServiceInstantiatesCorrectly(): void
    {
        self::assertInstanceOf(InternalSummaryService::class, $this->service);
    }
}
