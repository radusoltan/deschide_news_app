<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Clustering;

use App\Entity\PressRelease;
use App\Entity\StoryCluster;
use App\Service\Clustering\ClusterSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\NullLogger;

class ClusterSummaryServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    #[Test]
    public function emptyClusterReturnsFalse(): void
    {
        $service = new ClusterSummaryService(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), $this->em, new NullLogger());

        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Test');

        $result = $service->summarize($cluster);

        $this->assertFalse($result);
    }

    #[Test]
    public function parseResponseHandlesValidJson(): void
    {
        $service = new ClusterSummaryService(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), $this->em, new NullLogger());

        $json = json_encode([
            'summary_short' => 'Short summary',
            'summary_medium' => 'Medium summary text',
            'why_it_matters' => 'It matters because...',
            'key_facts' => ['Fact 1', 'Fact 2', 'Fact 3'],
        ]);

        $ref = new \ReflectionMethod($service, 'parseResponse');
        $result = $ref->invoke($service, $json);

        $this->assertNotNull($result);
        $this->assertSame('Short summary', $result['summary_short']);
        $this->assertSame('Medium summary text', $result['summary_medium']);
        $this->assertCount(3, $result['key_facts']);
    }

    #[Test]
    public function parseResponseStripsMarkdownCodeBlock(): void
    {
        $service = new ClusterSummaryService(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), $this->em, new NullLogger());

        $raw = '```json
{
  "summary_short": "Summary",
  "summary_medium": "Medium",
  "why_it_matters": "Matters",
  "key_facts": ["Fact"]
}
```';

        $ref = new \ReflectionMethod($service, 'parseResponse');
        $result = $ref->invoke($service, $raw);

        $this->assertNotNull($result);
        $this->assertSame('Summary', $result['summary_short']);
    }

    #[Test]
    public function parseResponseReturnsNullForInvalidJson(): void
    {
        $service = new ClusterSummaryService(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), $this->em, new NullLogger());

        $ref = new \ReflectionMethod($service, 'parseResponse');

        $this->assertNull($ref->invoke($service, 'not json'));
        $this->assertNull($ref->invoke($service, '{}'));
        $this->assertNull($ref->invoke($service, '{"summary_short": "only one field"}'));
    }

    #[Test]
    public function parseResponseReturnsNullWhenKeyFactsNotArray(): void
    {
        $service = new ClusterSummaryService(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), $this->em, new NullLogger());

        $json = json_encode([
            'summary_short' => 'Short',
            'summary_medium' => 'Medium',
            'why_it_matters' => 'Matters',
            'key_facts' => 'not an array',
        ]);

        $ref = new \ReflectionMethod($service, 'parseResponse');
        $result = $ref->invoke($service, $json);

        $this->assertNull($result);
    }

    #[Test]
    public function buildPromptContainsClusterData(): void
    {
        $service = new ClusterSummaryService(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), $this->em, new NullLogger());

        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('EU Sanctions Update');

        $pr = new PressRelease();
        $pr->setTitle('EU imposes new sanctions');
        $pr->setContent('Full content about sanctions');
        $pr->setCategorySlug('extern');
        $cluster->addPressRelease($pr);
        $cluster->recalculateCounts();

        $ref = new \ReflectionMethod($service, 'buildPrompt');
        $prompt = $ref->invoke($service, $cluster);

        $this->assertStringContainsString('EU Sanctions Update', $prompt);
        $this->assertStringContainsString('EU imposes new sanctions', $prompt);
        $this->assertStringContainsString('summary_short', $prompt);
        $this->assertStringContainsString('key_facts', $prompt);
    }
}
