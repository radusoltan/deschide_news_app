<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Agent;

use App\Dto\Agent\AgentResponse;
use App\Enum\LlmModelTier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AgentResponseTest extends TestCase
{
    #[Test]
    public function constructionPopulatesAllFields(): void
    {
        $response = new AgentResponse(
            content: '{"source_attribution":"potrivit Reuters","source_links_out":[]}',
            agentId: 'source_attribution',
            tier: LlmModelTier::HAIKU,
            model: 'claude-haiku-4-5-20251001',
            attempts: 1,
            invocationId: '01JE0Q9ZXJQ8YHZR3S3M7E2P5H',
            metrics: ['input_tokens' => 123, 'output_tokens' => 45],
        );

        $this->assertSame('{"source_attribution":"potrivit Reuters","source_links_out":[]}', $response->content);
        $this->assertSame('source_attribution', $response->agentId);
        $this->assertSame(LlmModelTier::HAIKU, $response->tier);
        $this->assertSame('claude-haiku-4-5-20251001', $response->model);
        $this->assertSame(1, $response->attempts);
        $this->assertSame('01JE0Q9ZXJQ8YHZR3S3M7E2P5H', $response->invocationId);
        $this->assertSame(['input_tokens' => 123, 'output_tokens' => 45], $response->metrics);
    }

    #[Test]
    public function invocationIdAndMetricsMayBeNullOnPersistFailure(): void
    {
        $response = new AgentResponse(
            content: 'raw body',
            agentId: 'style_guard',
            tier: LlmModelTier::HAIKU,
            model: 'claude-haiku-4-5-20251001',
            attempts: 2,
            invocationId: null,
            metrics: null,
        );

        $this->assertNull($response->invocationId);
        $this->assertNull($response->metrics);
    }

    #[Test]
    public function metricsDefaultsToNullWhenOmitted(): void
    {
        $response = new AgentResponse(
            content: 'body',
            agentId: 'context',
            tier: LlmModelTier::SONNET,
            model: 'claude-sonnet-4-6',
            attempts: 1,
            invocationId: 'abc',
        );

        $this->assertNull($response->metrics);
    }
}
