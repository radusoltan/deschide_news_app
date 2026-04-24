<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Agent;

use App\Dto\Agent\AgentRequest;
use App\Enum\LlmModelTier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AgentRequestTest extends TestCase
{
    #[Test]
    public function constructionPopulatesAllFields(): void
    {
        $request = new AgentRequest(
            agentId: 'source_attribution',
            messages: [['role' => 'user', 'content' => 'analyze this']],
            tier: LlmModelTier::HAIKU,
            systemPrompt: 'You are an analyst.',
            tierVariant: null,
        );

        $this->assertSame('source_attribution', $request->agentId);
        $this->assertSame([['role' => 'user', 'content' => 'analyze this']], $request->messages);
        $this->assertSame(LlmModelTier::HAIKU, $request->tier);
        $this->assertSame('You are an analyst.', $request->systemPrompt);
        $this->assertNull($request->tierVariant);
    }

    #[Test]
    public function optionalFieldsDefaultToNull(): void
    {
        $request = new AgentRequest(
            agentId: 'signal_aggregator',
            messages: [],
            tier: LlmModelTier::SONNET,
        );

        $this->assertNull($request->systemPrompt);
        $this->assertNull($request->tierVariant);
    }

    #[Test]
    public function tierVariantCarriesVerificationGateDualVariantKey(): void
    {
        $request = new AgentRequest(
            agentId: 'verification_gate',
            messages: [['role' => 'user', 'content' => 'verdict input']],
            tier: LlmModelTier::SONNET,
            tierVariant: 'model_tier_conflict',
        );

        $this->assertSame('model_tier_conflict', $request->tierVariant);
    }
}
