<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Escalation;

use App\Agent\AgentDispatcher;
use App\Dto\Agent\AgentResponse;
use App\Enum\Editorial\EscalationCategory;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Editorial\Escalation\EscalationClassifier;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use App\Service\Editorial\Llm\LlmPromptAssembler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * T57.03 follow-up — focused coverage for
 * {@see EscalationClassifier::mapVerdict()}. Returns `'none'` when the
 * classifier decides not to escalate (is_escalation=false, confidence below
 * threshold, or explicit NONE); returns the lowercased constant name
 * otherwise (e.g. `category_1_nuclear_war`).
 */
final class EscalationClassifierVerdictMappingTest extends TestCase
{
    private const INVOCATION_ID = '01JFXXXXXXXXXXXXXXXXXXXXXX';

    private AgentDispatcher&MockObject $dispatcher;
    private AppSettingRepository&MockObject $settings;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private EscalationClassifier $classifier;

    private ?string $capturedVerdict = null;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);

        $this->settings->method('getBool')->willReturn(true);
        $this->settings->method('get')->willReturn('v1');

        $this->invocationLogger
            ->method('attachVerdict')
            ->willReturnCallback(function (string $_invocationId, string $verdict): void {
                $this->capturedVerdict = $verdict;
            });

        $this->classifier = new EscalationClassifier(
            $this->dispatcher,
            $this->settings,
            new LlmPromptAssembler(),
            $this->invocationLogger,
            $this->createMock(LoggerInterface::class),
        );
    }

    #[Test]
    public function verdictNoneWhenCategoryIsNONE(): void
    {
        $this->mockLlmResponse([
            'category' => 'NONE',
            'is_escalation' => false,
            'confidence' => 0.9,
            'rationale' => 'rutină',
        ]);

        $this->classifier->classify('benign claim', 'Wire');

        self::assertSame('none', $this->capturedVerdict);
    }

    #[Test]
    public function verdictNoneWhenIsEscalationFalse(): void
    {
        // LLM declares a category but flags is_escalation=false. Classifier
        // honours the flag and returns 'none'.
        $this->mockLlmResponse([
            'category' => 'CATEGORY_1_NUCLEAR_WAR',
            'is_escalation' => false,
            'confidence' => 0.9,
            'rationale' => 'event rutinier, termenul nuclear apare doar ca context',
        ]);

        $this->classifier->classify('x', 'y');

        self::assertSame('none', $this->capturedVerdict);
    }

    #[Test]
    public function verdictNoneWhenConfidenceBelowThreshold(): void
    {
        $this->mockLlmResponse([
            'category' => 'CATEGORY_3_NBC_ATTACK',
            'is_escalation' => true,
            'confidence' => 0.5, // below 0.7 threshold
            'rationale' => 'probe preliminare',
        ]);

        $this->classifier->classify('x', 'y');

        self::assertSame('none', $this->capturedVerdict);
    }

    #[Test]
    #[DataProvider('categoriesProvider')]
    public function verdictIsLowercasedConstantNameForEachCategory(EscalationCategory $category): void
    {
        // The LLM returns the CONSTANT NAME (e.g. CATEGORY_1_NUCLEAR_WAR),
        // not the short value (e.g. categ_1) — see EscalationClassifier
        // system prompt and ::mapResult(). The verdict stored is the
        // lowercased constant name.
        $this->mockLlmResponse([
            'category' => $category->name,
            'is_escalation' => true,
            'confidence' => 0.95,
            'rationale' => 'clearly fits category ' . $category->name,
        ]);

        $this->classifier->classify('claim matching ' . $category->name, 'Source');

        self::assertSame(strtolower($category->name), $this->capturedVerdict);
    }

    /**
     * @return iterable<string, array{0: EscalationCategory}>
     */
    public static function categoriesProvider(): iterable
    {
        foreach (EscalationCategory::cases() as $case) {
            yield $case->name => [$case];
        }
    }

    /** @param array<string, mixed> $payload */
    private function mockLlmResponse(array $payload): void
    {
        $this->dispatcher->method('dispatch')->willReturn(new AgentResponse(
            content: json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            agentId: 'escalation_classifier',
            tier: LlmModelTier::HAIKU,
            model: 'claude-haiku-4-5-20251001',
            attempts: 1,
            invocationId: self::INVOCATION_ID,
            metrics: null,
        ));
    }
}
