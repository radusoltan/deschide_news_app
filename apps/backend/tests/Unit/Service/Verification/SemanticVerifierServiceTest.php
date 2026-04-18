<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Verification;

use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Repository\AppSettingRepository;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Verification\SemanticVerifierService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SemanticVerifierServiceTest extends TestCase
{
    private GeminiCliService&MockObject $geminiCli;
    private AppSettingRepository&MockObject $appSettings;
    private SemanticVerifierService $service;

    protected function setUp(): void
    {
        $this->geminiCli = $this->createMock(GeminiCliService::class);
        $this->appSettings = $this->createMock(AppSettingRepository::class);

        $this->service = new SemanticVerifierService(
            $this->geminiCli,
            $this->appSettings,
            new NullLogger(),
        );
    }

    #[Test]
    public function returnsExpectedPairsFor3PRsWith2Duplicates(): void
    {
        $this->enableVerification();
        $this->setMinConfidence(0.70);

        $topic = $this->createTopic(101, 'Alegeri parlamentare', 'alegeri-parlamentare');
        $prs = [
            $this->createPressRelease(1, 'Maia Sandu anunță noul guvern'),
            $this->createPressRelease(2, 'Sandu prezintă cabinetul de miniștri'),
            $this->createPressRelease(3, 'Meteo: ploi în nordul țării'),
        ];

        $this->geminiCli->expects($this->once())
            ->method('execute')
            ->willReturn('json output');

        $this->geminiCli->method('extractJsonArray')->willReturn([
            ['pr1' => 1, 'pr2' => 2, 'similarity' => 0.95, 'reason' => 'același anunț guvern'],
        ]);

        $pairs = $this->service->findDuplicatePairsInTopicWindow($topic, $prs, 24);

        $this->assertCount(1, $pairs);
        $this->assertSame(1, $pairs[0][0]);
        $this->assertSame(2, $pairs[0][1]);
        $this->assertSame(0.95, $pairs[0][2]);
    }

    #[Test]
    public function returnsEmptyOnLlmTimeout(): void
    {
        $this->enableVerification();
        $this->setMinConfidence(0.70);

        $topic = $this->createTopic(202, 'Război Ucraina', 'razboi-ucraina');
        $prs = [
            $this->createPressRelease(10, 'Atac asupra Kievului'),
            $this->createPressRelease(11, 'Explozii raportate la Kiev'),
        ];

        $this->geminiCli->method('execute')->willThrowException(
            new GeminiCliException('Gemini CLI timed out after 120s', 0, null, isTimeout: true),
        );

        $pairs = $this->service->findDuplicatePairsInTopicWindow($topic, $prs, 24);

        $this->assertSame([], $pairs, 'Timeout must fail-open with empty pairs list');
    }

    #[Test]
    public function returnsEmptyOnInvalidJsonResponse(): void
    {
        $this->enableVerification();
        $this->setMinConfidence(0.70);

        $topic = $this->createTopic(303, 'Sport', 'sport');
        $prs = [
            $this->createPressRelease(20, 'FCSB câștigă derbiul'),
            $this->createPressRelease(21, 'Steaua învinge Dinamo'),
        ];

        $this->geminiCli->method('execute')->willReturn('not a json array at all');
        $this->geminiCli->method('extractJsonArray')->willThrowException(
            new GeminiCliException('Failed to extract JSON array from Gemini response'),
        );

        $pairs = $this->service->findDuplicatePairsInTopicWindow($topic, $prs, 24);

        $this->assertSame([], $pairs, 'Malformed JSON must fail-open with empty pairs list');
    }

    #[Test]
    public function respectsWindowHoursParameter(): void
    {
        $this->enableVerification();
        $this->setMinConfidence(0.70);

        $topic = $this->createTopic(404, 'Economie', 'economie');
        $prs = [
            $this->createPressRelease(30, 'BNM ridică rata dobânzii'),
            $this->createPressRelease(31, 'Dobânda de politică monetară urcă'),
        ];

        $capturedPrompt = null;

        $this->geminiCli->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (string $prompt, array $options) use (&$capturedPrompt): string {
                $capturedPrompt = $prompt;
                return 'json output';
            });

        $this->geminiCli->method('extractJsonArray')->willReturn([]);

        $this->service->findDuplicatePairsInTopicWindow($topic, $prs, 48);

        $this->assertNotNull($capturedPrompt, 'Gemini should have been called');
        $this->assertStringContainsString('ultimele 48 ore', $capturedPrompt, 'windowHours must appear in the prompt');
        $this->assertStringContainsString('Economie', $capturedPrompt, 'topic title must appear in the prompt');
        $this->assertStringContainsString('economie', $capturedPrompt, 'topic slug must appear in the prompt');
    }

    #[Test]
    public function returnsEmptyWhenDisabled(): void
    {
        $this->appSettings->method('getBool')
            ->with('cluster_semantic_verification_enabled', false)
            ->willReturn(false);

        $this->geminiCli->expects($this->never())->method('execute');

        $topic = $this->createTopic(1, 'T', 't');
        $prs = [
            $this->createPressRelease(1, 'A'),
            $this->createPressRelease(2, 'B'),
        ];

        $pairs = $this->service->findDuplicatePairsInTopicWindow($topic, $prs, 24);

        $this->assertSame([], $pairs);
    }

    #[Test]
    public function returnsEmptyWhenFewerThanTwoCandidates(): void
    {
        // No AppSettings calls expected — short-circuit before isEnabled() check.
        $this->geminiCli->expects($this->never())->method('execute');

        $topic = $this->createTopic(1, 'T', 't');

        $this->assertSame([], $this->service->findDuplicatePairsInTopicWindow($topic, [], 24));
        $this->assertSame([], $this->service->findDuplicatePairsInTopicWindow(
            $topic,
            [$this->createPressRelease(1, 'Singleton')],
            24,
        ));
    }

    #[Test]
    public function filtersPairsBelowConfidenceThreshold(): void
    {
        $this->enableVerification();
        $this->setMinConfidence(0.80);

        $topic = $this->createTopic(1, 'T', 't');
        $prs = [
            $this->createPressRelease(50, 'A'),
            $this->createPressRelease(51, 'B'),
            $this->createPressRelease(52, 'C'),
        ];

        $this->geminiCli->method('execute')->willReturn('json');
        $this->geminiCli->method('extractJsonArray')->willReturn([
            ['pr1' => 50, 'pr2' => 51, 'similarity' => 0.95, 'reason' => 'kept'],
            ['pr1' => 51, 'pr2' => 52, 'similarity' => 0.65, 'reason' => 'dropped — below 0.80'],
        ]);

        $pairs = $this->service->findDuplicatePairsInTopicWindow($topic, $prs, 24);

        $this->assertCount(1, $pairs);
        $this->assertSame([50, 51, 0.95], $pairs[0]);
    }

    #[Test]
    public function ignoresPairsWithIdsOutsideCandidateSet(): void
    {
        $this->enableVerification();
        $this->setMinConfidence(0.70);

        $topic = $this->createTopic(1, 'T', 't');
        $prs = [
            $this->createPressRelease(60, 'A'),
            $this->createPressRelease(61, 'B'),
        ];

        $this->geminiCli->method('execute')->willReturn('json');
        $this->geminiCli->method('extractJsonArray')->willReturn([
            ['pr1' => 60, 'pr2' => 999, 'similarity' => 0.99, 'reason' => 'phantom id'],
            ['pr1' => 60, 'pr2' => 61, 'similarity' => 0.85, 'reason' => 'ok'],
        ]);

        $pairs = $this->service->findDuplicatePairsInTopicWindow($topic, $prs, 24);

        $this->assertCount(1, $pairs);
        $this->assertSame([60, 61, 0.85], $pairs[0]);
    }

    #[Test]
    public function dedupesMirroredPairs(): void
    {
        $this->enableVerification();
        $this->setMinConfidence(0.70);

        $topic = $this->createTopic(1, 'T', 't');
        $prs = [
            $this->createPressRelease(70, 'A'),
            $this->createPressRelease(71, 'B'),
        ];

        $this->geminiCli->method('execute')->willReturn('json');
        $this->geminiCli->method('extractJsonArray')->willReturn([
            ['pr1' => 70, 'pr2' => 71, 'similarity' => 0.90, 'reason' => 'dup'],
            ['pr1' => 71, 'pr2' => 70, 'similarity' => 0.92, 'reason' => 'mirrored'],
        ]);

        $pairs = $this->service->findDuplicatePairsInTopicWindow($topic, $prs, 24);

        $this->assertCount(1, $pairs);
        $this->assertSame(70, $pairs[0][0], 'Pair must be normalized to (min, max) order');
        $this->assertSame(71, $pairs[0][1]);
    }

    #[Test]
    public function ignoresSelfPairs(): void
    {
        $this->enableVerification();
        $this->setMinConfidence(0.70);

        $topic = $this->createTopic(1, 'T', 't');
        $prs = [
            $this->createPressRelease(80, 'A'),
            $this->createPressRelease(81, 'B'),
        ];

        $this->geminiCli->method('execute')->willReturn('json');
        $this->geminiCli->method('extractJsonArray')->willReturn([
            ['pr1' => 80, 'pr2' => 80, 'similarity' => 1.0, 'reason' => 'self'],
            ['pr1' => 80, 'pr2' => 81, 'similarity' => 0.85, 'reason' => 'ok'],
        ]);

        $pairs = $this->service->findDuplicatePairsInTopicWindow($topic, $prs, 24);

        $this->assertCount(1, $pairs);
        $this->assertSame([80, 81, 0.85], $pairs[0]);
    }

    private function enableVerification(): void
    {
        $this->appSettings->method('getBool')
            ->with('cluster_semantic_verification_enabled', false)
            ->willReturn(true);
    }

    private function setMinConfidence(float $value): void
    {
        $this->appSettings->method('getFloat')
            ->with('cluster_semantic_min_confidence', 0.70)
            ->willReturn($value);
    }

    private function createTopic(int $id, string $title, string $slug): Topic
    {
        $topic = new Topic();
        $topic->setTitle($title);
        $topic->setSlug($slug);

        $ref = new \ReflectionProperty(Topic::class, 'id');
        $ref->setValue($topic, $id);

        return $topic;
    }

    private function createPressRelease(int $id, string $title): PressRelease
    {
        $pr = new PressRelease();
        $pr->setTitle($title);
        $pr->setContent('content body for ' . $title);
        $pr->setCategorySlug('extern');

        $ref = new \ReflectionProperty(PressRelease::class, 'id');
        $ref->setValue($pr, $id);

        return $pr;
    }
}
