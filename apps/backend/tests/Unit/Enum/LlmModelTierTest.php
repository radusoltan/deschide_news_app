<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\LlmModelTier;
use PHPUnit\Framework\TestCase;

class LlmModelTierTest extends TestCase
{
    public function testAllCases(): void
    {
        $this->assertCount(4, LlmModelTier::cases());
    }

    public function testValues(): void
    {
        $this->assertSame('haiku', LlmModelTier::HAIKU->value);
        $this->assertSame('sonnet', LlmModelTier::SONNET->value);
        $this->assertSame('opus', LlmModelTier::OPUS->value);
        $this->assertSame('gemini_flash', LlmModelTier::GEMINI_FLASH->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(LlmModelTier::HAIKU, LlmModelTier::from('haiku'));
        $this->assertSame(LlmModelTier::SONNET, LlmModelTier::from('sonnet'));
        $this->assertSame(LlmModelTier::OPUS, LlmModelTier::from('opus'));
        $this->assertSame(LlmModelTier::GEMINI_FLASH, LlmModelTier::from('gemini_flash'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        LlmModelTier::from('invalid');
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(LlmModelTier::tryFrom('invalid'));
    }

    public function testToModelString(): void
    {
        $this->assertSame('claude-haiku-4-5-20251001', LlmModelTier::HAIKU->toModelString());
        $this->assertSame('claude-sonnet-4-6', LlmModelTier::SONNET->toModelString());
        $this->assertSame('claude-opus-4-7', LlmModelTier::OPUS->toModelString());
        $this->assertSame('gemini-2.5-flash', LlmModelTier::GEMINI_FLASH->toModelString());
    }

    public function testTransport(): void
    {
        $this->assertSame('claude_cli', LlmModelTier::HAIKU->transport());
        $this->assertSame('claude_cli', LlmModelTier::SONNET->transport());
        $this->assertSame('claude_cli', LlmModelTier::OPUS->transport());
        $this->assertSame('gemini_cli', LlmModelTier::GEMINI_FLASH->transport());
    }
}
