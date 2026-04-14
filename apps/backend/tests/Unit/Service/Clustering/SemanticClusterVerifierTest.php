<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Clustering;

use App\Dto\Clustering\VerificationResult;
use App\Repository\AppSettingRepository;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Clustering\SemanticClusterVerifier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SemanticClusterVerifierTest extends TestCase
{
    private GeminiCliService&MockObject $geminiCli;
    private AppSettingRepository&MockObject $appSettings;
    private SemanticClusterVerifier $verifier;

    protected function setUp(): void
    {
        $this->geminiCli = $this->createMock(GeminiCliService::class);
        $this->appSettings = $this->createMock(AppSettingRepository::class);

        $this->verifier = new SemanticClusterVerifier(
            $this->geminiCli,
            $this->appSettings,
            new NullLogger(),
        );
    }

    #[Test]
    public function disabledVerificationPassesAllCandidates(): void
    {
        $this->appSettings->method('getBool')->willReturn(false);

        $candidates = [
            ['title' => 'Title 1'],
            ['title' => 'Title 2'],
            ['title' => 'Title 3'],
        ];

        $results = $this->verifier->verifyBatch($candidates, 'Cluster Headline');

        $this->assertCount(3, $results);
        foreach ($results as $r) {
            $this->assertTrue($r->sameStory);
            $this->assertSame('Verification disabled', $r->reason);
        }
    }

    #[Test]
    public function isEnabledReadsFromAppSettings(): void
    {
        $this->appSettings->method('getBool')
            ->with('cluster_semantic_verification_enabled', false)
            ->willReturn(true);

        $this->assertTrue($this->verifier->isEnabled());
    }

    #[Test]
    public function isDisabledByDefault(): void
    {
        $this->appSettings->method('getBool')->willReturn(false);

        $this->assertFalse($this->verifier->isEnabled());
    }

    #[Test]
    public function geminiFailureFailsOpen(): void
    {
        $this->appSettings->method('getBool')->willReturn(true);
        $this->geminiCli->method('execute')
            ->willThrowException(new GeminiCliException('Connection refused'));

        $results = $this->verifier->verifyBatch(
            [['title' => 'Test title']],
            'Cluster Headline',
        );

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->sameStory);
        $this->assertTrue($results[0]->failedOpen);
        $this->assertStringContainsString('Connection refused', $results[0]->reason);
    }

    #[Test]
    public function parsesAcceptedGeminiResponse(): void
    {
        $this->appSettings->method('getBool')->willReturn(true);

        $this->geminiCli->method('execute')->willReturn('json output');
        $this->geminiCli->method('extractJsonArray')->willReturn([
            ['index' => 1, 'same_story' => true, 'confidence' => 0.95, 'reason' => 'Ambele despre cutremur'],
        ]);

        $results = $this->verifier->verifyBatch(
            [['title' => 'Cutremur major']],
            'Cutremur de 7.2 în Turcia',
        );

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->sameStory);
        $this->assertSame(0.95, $results[0]->confidence);
        $this->assertSame('Ambele despre cutremur', $results[0]->reason);
    }

    #[Test]
    public function parsesRejectionResponse(): void
    {
        $this->appSettings->method('getBool')->willReturn(true);

        $this->geminiCli->method('execute')->willReturn('json');
        $this->geminiCli->method('extractJsonArray')->willReturn([
            ['index' => 1, 'same_story' => false, 'confidence' => 0.1, 'reason' => 'Subiecte diferite'],
        ]);

        $results = $this->verifier->verifyBatch(
            [['title' => 'Meteo: ploi în Moldova']],
            'EU adoptă sancțiuni noi',
        );

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->sameStory);
        $this->assertSame(0.1, $results[0]->confidence);
    }

    #[Test]
    public function batchVerifyCorrectCountOfResults(): void
    {
        $this->appSettings->method('getBool')->willReturn(true);

        $this->geminiCli->method('execute')->willReturn('json');
        $this->geminiCli->method('extractJsonArray')->willReturn([
            ['index' => 1, 'same_story' => true, 'confidence' => 0.9, 'reason' => 'da'],
            ['index' => 2, 'same_story' => false, 'confidence' => 0.2, 'reason' => 'nu'],
            ['index' => 3, 'same_story' => true, 'confidence' => 0.85, 'reason' => 'da'],
        ]);

        $candidates = [
            ['title' => 'A'],
            ['title' => 'B'],
            ['title' => 'C'],
        ];

        $results = $this->verifier->verifyBatch($candidates, 'Headline');

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]->sameStory);
        $this->assertFalse($results[1]->sameStory);
        $this->assertTrue($results[2]->sameStory);
    }

    #[Test]
    public function missingIndexInResponseFailsOpen(): void
    {
        $this->appSettings->method('getBool')->willReturn(true);

        $this->geminiCli->method('execute')->willReturn('json');
        $this->geminiCli->method('extractJsonArray')->willReturn([
            ['index' => 1, 'same_story' => true, 'confidence' => 0.9, 'reason' => 'ok'],
            // Missing index 2
        ]);

        $candidates = [
            ['title' => 'A'],
            ['title' => 'B'],
        ];

        $results = $this->verifier->verifyBatch($candidates, 'Headline');

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->sameStory);
        $this->assertTrue($results[1]->sameStory); // fail-open
        $this->assertTrue($results[1]->failedOpen);
    }

    #[Test]
    public function emptyBatchReturnsEmpty(): void
    {
        $results = $this->verifier->verifyBatch([], 'Headline');

        $this->assertSame([], $results);
    }

    #[Test]
    public function singleVerifyDelegatesToBatch(): void
    {
        $this->appSettings->method('getBool')->willReturn(true);

        $this->geminiCli->method('execute')->willReturn('json');
        $this->geminiCli->method('extractJsonArray')->willReturn([
            ['index' => 1, 'same_story' => true, 'confidence' => 0.88, 'reason' => 'Same event'],
        ]);

        $result = $this->verifier->verify('Breaking: earthquake', 'Major earthquake hits Turkey');

        $this->assertTrue($result->sameStory);
        $this->assertSame(0.88, $result->confidence);
    }

    #[Test]
    public function jsonParseFailureFailsOpen(): void
    {
        $this->appSettings->method('getBool')->willReturn(true);

        $this->geminiCli->method('execute')->willReturn('not json');
        $this->geminiCli->method('extractJsonArray')
            ->willThrowException(new GeminiCliException('No JSON found'));

        $results = $this->verifier->verifyBatch(
            [['title' => 'A'], ['title' => 'B']],
            'Headline',
        );

        $this->assertCount(2, $results);
        foreach ($results as $r) {
            $this->assertTrue($r->sameStory);
            $this->assertTrue($r->failedOpen);
        }
    }
}
