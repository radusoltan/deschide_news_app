<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ai;

use App\Service\Ai\MockAnthropicClient;
use PHPUnit\Framework\TestCase;

class MockAnthropicClientTest extends TestCase
{
    private MockAnthropicClient $client;

    protected function setUp(): void
    {
        $this->client = new MockAnthropicClient();
    }

    public function testChatReturnsResponse(): void
    {
        $response = $this->client->chat(
            [['role' => 'user', 'content' => 'Hello']],
            'claude-sonnet-4-20250514',
        );

        $this->assertStringContainsString('Mock', $response);
        $this->assertStringContainsString('Hello', $response);
    }

    public function testClassificationReturnsJsonForVault(): void
    {
        $system = 'Ești secretarul de redacție al Deschide.md. Clasifici cererea jurnalistului.';

        $response = $this->client->chat(
            [['role' => 'user', 'content' => 'Caută dosare despre Sandu']],
            'claude-haiku-4-5-20251001',
            $system,
        );

        $data = json_decode($response, true);
        $this->assertIsArray($data);
        $this->assertSame('vault', $data['agentType']);
    }

    public function testClassificationReturnsTranslation(): void
    {
        $system = 'Clasifici cererea jurnalistului.';

        $response = $this->client->chat(
            [['role' => 'user', 'content' => 'Traduce articolul în engleză']],
            'claude-haiku-4-5-20251001',
            $system,
        );

        $data = json_decode($response, true);
        $this->assertSame('translation', $data['agentType']);
    }

    public function testClassificationReturnsBriefing(): void
    {
        $system = 'Clasifici cererea jurnalistului.';

        $response = $this->client->chat(
            [['role' => 'user', 'content' => 'Briefing zilnic despre politică']],
            'claude-haiku-4-5-20251001',
            $system,
        );

        $data = json_decode($response, true);
        $this->assertSame('briefing', $data['agentType']);
    }

    public function testClassificationDefaultsToContent(): void
    {
        $system = 'Clasifici cererea jurnalistului.';

        $response = $this->client->chat(
            [['role' => 'user', 'content' => 'Rescrie textul în ton formal']],
            'claude-haiku-4-5-20251001',
            $system,
        );

        $data = json_decode($response, true);
        $this->assertSame('content', $data['agentType']);
    }

    public function testChatStreamYieldsChunks(): void
    {
        $chunks = [];
        foreach ($this->client->chatStream(
            [['role' => 'user', 'content' => 'Test stream']],
            'claude-sonnet-4-20250514',
        ) as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertNotEmpty($chunks);
        $full = implode('', $chunks);
        $this->assertStringContainsString('Mock', $full);
    }
}
