<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Translation;

use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Translation\GeminiStructuredTranslator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GeminiStructuredTranslatorTest extends TestCase
{
    public function testValidateResponseParsesValidJson(): void
    {
        $translator = new GeminiStructuredTranslator(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), new NullLogger());

        $reflection = new \ReflectionMethod($translator, 'validateResponse');

        $json = json_encode([
            'translated_headline' => 'Government approves reform',
            'translated_body' => 'Full content of the article.',
            'translated_description' => 'Brief description.',
            'suggested_slug' => 'government-approves-reform',
            'seo_description' => 'Government reform article.',
            'entities' => ['Government', 'Parliament'],
            'topics' => ['politics', 'reform'],
            'locations' => ['Chișinău'],
            'editorial_risk_flags' => [],
        ]);

        $result = $reflection->invoke($translator, $json);

        $this->assertNotNull($result);
        $this->assertSame('Government approves reform', $result['translated_headline']);
        $this->assertSame('government-approves-reform', $result['suggested_slug']);
        $this->assertCount(2, $result['entities']);
        $this->assertCount(2, $result['topics']);
    }

    public function testValidateResponseStripsMarkdownCodeBlocks(): void
    {
        $translator = new GeminiStructuredTranslator(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), new NullLogger());

        $reflection = new \ReflectionMethod($translator, 'validateResponse');

        $wrapped = "```json\n" . json_encode([
            'translated_headline' => 'Test',
            'translated_body' => 'Body',
            'translated_description' => 'Desc',
            'suggested_slug' => 'test',
        ]) . "\n```";

        $result = $reflection->invoke($translator, $wrapped);

        $this->assertNotNull($result);
        $this->assertSame('Test', $result['translated_headline']);
    }

    public function testValidateResponseRejectsInvalidJson(): void
    {
        $translator = new GeminiStructuredTranslator(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), new NullLogger());

        $reflection = new \ReflectionMethod($translator, 'validateResponse');

        $result = $reflection->invoke($translator, 'not json at all');

        $this->assertNull($result);
    }

    public function testValidateResponseRejectsMissingRequiredFields(): void
    {
        $translator = new GeminiStructuredTranslator(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), new NullLogger());

        $reflection = new \ReflectionMethod($translator, 'validateResponse');

        $json = json_encode([
            'translated_headline' => 'Test',
            // Missing: translated_body, translated_description, suggested_slug
        ]);

        $result = $reflection->invoke($translator, $json);

        $this->assertNull($result);
    }

    public function testValidateResponseDefaultsOptionalArrayFields(): void
    {
        $translator = new GeminiStructuredTranslator(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), new NullLogger());

        $reflection = new \ReflectionMethod($translator, 'validateResponse');

        $json = json_encode([
            'translated_headline' => 'Test',
            'translated_body' => 'Body',
            'translated_description' => 'Desc',
            'suggested_slug' => 'test',
            // No entities, topics, locations, editorial_risk_flags
        ]);

        $result = $reflection->invoke($translator, $json);

        $this->assertNotNull($result);
        $this->assertSame([], $result['entities']);
        $this->assertSame([], $result['topics']);
        $this->assertSame([], $result['locations']);
        $this->assertSame([], $result['editorial_risk_flags']);
    }

    public function testValidateResponseTruncatesSeoDescription(): void
    {
        $translator = new GeminiStructuredTranslator(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), new NullLogger());

        $reflection = new \ReflectionMethod($translator, 'validateResponse');

        $json = json_encode([
            'translated_headline' => 'Test',
            'translated_body' => 'Body',
            'translated_description' => 'Desc',
            'suggested_slug' => 'test',
            'seo_description' => str_repeat('A', 200), // Over 160 limit
        ]);

        $result = $reflection->invoke($translator, $json);

        $this->assertNotNull($result);
        $this->assertSame(160, mb_strlen($result['seo_description']));
    }

    public function testBuildPromptContainsRequiredElements(): void
    {
        $translator = new GeminiStructuredTranslator(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), new NullLogger());

        $reflection = new \ReflectionMethod($translator, 'buildPrompt');

        $prompt = $reflection->invoke($translator, 'Titlu', 'Conținut articol', 'ro', 'en');

        $this->assertStringContainsString('Romanian', $prompt);
        $this->assertStringContainsString('English', $prompt);
        $this->assertStringContainsString('Titlu', $prompt);
        $this->assertStringContainsString('Conținut articol', $prompt);
        $this->assertStringContainsString('translated_headline', $prompt);
        $this->assertStringContainsString('comma-below', $prompt);
    }
}
