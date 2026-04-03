<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ai;

use App\Entity\AiConversation;
use App\Entity\AiMessage;
use App\Entity\AiPromptTemplate;
use App\Entity\User;
use App\Enum\AiAgentType;
use App\Repository\AiConversationRepository;
use App\Repository\AiPromptTemplateRepository;
use App\Repository\ArticleRepository;
use App\Service\Ai\AiMercureService;
use App\Service\Ai\AiOrchestratorService;
use App\Service\Ai\MockAnthropicClient;
use App\Service\Ai\Provider\AiProviderInterface;
use App\Service\Ai\Provider\AiProviderRegistry;
use App\Service\Ai\Provider\AnthropicProvider;
use App\Service\Editorial\DailyBriefingService;
use App\Service\Editorial\WeeklySummaryService;
use App\Service\Search\SearchService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

class AiOrchestratorServiceTest extends TestCase
{
    private MockAnthropicClient $client;
    private AiMercureService $mercure;
    private AiConversationRepository $convRepo;
    private AiPromptTemplateRepository $tplRepo;
    private AiOrchestratorService $orchestrator;
    private User $user;

    protected function setUp(): void
    {
        $this->client = new MockAnthropicClient();
        $em = $this->createStub(EntityManagerInterface::class);
        $this->mercure = $this->createMock(AiMercureService::class);
        $this->convRepo = $this->createMock(AiConversationRepository::class);
        $this->tplRepo = $this->createMock(AiPromptTemplateRepository::class);

        $search = $this->createMock(SearchService::class);
        $search->method('search')->willReturn(['total' => 0, 'hits' => []]);

        $briefing = $this->createMock(DailyBriefingService::class);
        $briefing->method('generateDailyBriefing')->willReturn(null);

        $weekly = $this->createMock(WeeklySummaryService::class);
        $weekly->method('generateWeeklySummary')->willReturn(null);

        $logger = new NullLogger();

        // Build provider registry with both providers
        $anthropicProvider = new AnthropicProvider($this->client, $logger);
        $geminiProvider = $this->createMock(AiProviderInterface::class);
        $geminiProvider->method('supports')->willReturnCallback(
            fn (AiAgentType $type) => \in_array($type, [AiAgentType::TRANSLATION, AiAgentType::SEO], true),
        );
        $geminiProvider->method('chat')->willReturn('[Mock Gemini] Răspuns simulat.');
        $geminiProvider->method('getModelForAgent')->willReturn('gemini-2.5-flash');

        $registry = new AiProviderRegistry([$anthropicProvider, $geminiProvider]);

        $articleRepo = $this->createStub(ArticleRepository::class);
        $messageBus = $this->createStub(MessageBusInterface::class);

        $this->orchestrator = new AiOrchestratorService(
            $this->client,
            $registry,
            $em,
            $this->mercure,
            $logger,
            $this->convRepo,
            $this->tplRepo,
            $articleRepo,
            $search,
            $briefing,
            $weekly,
            $messageBus,
        );

        $this->user = $this->createMock(User::class);
        $this->user->method('getId')->willReturn(1);
        $this->user->method('getUserIdentifier')->willReturn('admin');
    }

    // =========================================================================
    // Conversation Management
    // =========================================================================

    public function testCreatesNewConversationWhenNoIdProvided(): void
    {
        $result = $this->orchestrator->processMessage($this->user, 'Hello world');

        $this->assertInstanceOf(AiConversation::class, $result);
        $this->assertSame($this->user, $result->getUser());
    }

    public function testReusesExistingConversation(): void
    {
        $conv = new AiConversation();
        $conv->setUser($this->user);
        $convId = (string) $conv->getId();

        $this->convRepo->method('find')->willReturn($conv);

        $result = $this->orchestrator->processMessage($this->user, 'Follow up', $convId);

        $this->assertSame($conv, $result);
    }

    public function testThrowsWhenConversationNotFound(): void
    {
        $this->convRepo->method('find')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Conversation not found');

        $this->orchestrator->processMessage($this->user, 'test', Uuid::v7()->toRfc4122());
    }

    public function testThrowsWhenConversationBelongsToOtherUser(): void
    {
        $otherUser = $this->createMock(User::class);
        $otherUser->method('getId')->willReturn(999);

        $conv = new AiConversation();
        $conv->setUser($otherUser);

        $this->convRepo->method('find')->willReturn($conv);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Access denied');

        $this->orchestrator->processMessage($this->user, 'test', (string) $conv->getId());
    }

    // =========================================================================
    // Intent Classification (via MockAnthropicClient)
    // =========================================================================

    public function testClassifiesVaultQuery(): void
    {
        $result = $this->orchestrator->processMessage($this->user, 'Caută dosare despre Sandu');

        $this->assertSame('vault', $result->getAgentType());
    }

    public function testClassifiesTranslationQuery(): void
    {
        $result = $this->orchestrator->processMessage($this->user, 'Traduce articolul în engleză');

        $this->assertSame('translation', $result->getAgentType());
    }

    public function testClassifiesBriefingQuery(): void
    {
        $result = $this->orchestrator->processMessage($this->user, 'Briefing zilnic despre politică');

        $this->assertSame('briefing', $result->getAgentType());
    }

    public function testDefaultsToContentForAmbiguousQuery(): void
    {
        $result = $this->orchestrator->processMessage($this->user, 'Rescrie textul acesta în ton formal');

        $this->assertSame('content', $result->getAgentType());
    }

    // =========================================================================
    // Template Handling
    // =========================================================================

    public function testUsesTemplateAgentTypeDirectly(): void
    {
        $template = new AiPromptTemplate();
        $template->setAgentType('briefing');
        $template->setCategory('briefing');
        $template->setName('Test');
        $template->setPromptTemplate('Briefing pentru {topic}');
        $template->setRequiredFields(['topic']);

        $this->tplRepo->method('find')->willReturn($template);

        $result = $this->orchestrator->processMessage(
            $this->user,
            'user input',
            null,
            (string) $template->getId(),
            ['topic' => 'economie'],
        );

        $this->assertSame('briefing', $result->getAgentType());
    }

    // =========================================================================
    // Message Persistence
    // =========================================================================

    public function testIncrementsMessageCount(): void
    {
        $result = $this->orchestrator->processMessage($this->user, 'Hello');

        // 2 messages: user + assistant
        $this->assertSame(2, $result->getMessageCount());
    }

    public function testSetsAutoTitle(): void
    {
        $result = $this->orchestrator->processMessage($this->user, 'Un mesaj destul de lung care ar trebui trunchiat la 60 de caractere, nu mai mult');

        $this->assertNotNull($result->getTitle());
        $this->assertLessThanOrEqual(65, mb_strlen($result->getTitle()));
    }

    public function testStoresMessagesInConversation(): void
    {
        $result = $this->orchestrator->processMessage($this->user, 'Test message');

        $messages = $result->getMessages();
        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]->getRole());
        $this->assertSame('assistant', $messages[1]->getRole());
    }

    // =========================================================================
    // Mercure Integration
    // =========================================================================

    public function testPublishesTypingEvent(): void
    {
        $this->mercure->expects($this->once())->method('publishTyping');

        $this->orchestrator->processMessage($this->user, 'Test query');
    }

    public function testPublishesMessageEvent(): void
    {
        $this->mercure->expects($this->once())->method('publishMessage');

        $this->orchestrator->processMessage($this->user, 'Test query');
    }

    // =========================================================================
    // Provider Routing (Sprint 21)
    // =========================================================================

    public function testSeoQuerySetsGeminiModel(): void
    {
        $result = $this->orchestrator->processMessage($this->user, 'Optimizează SEO pentru articolul 123');

        $lastMessage = $result->getMessages()->last();
        $this->assertSame('seo', $lastMessage->getAgentType());
        $this->assertSame('gemini-2.5-flash', $lastMessage->getModel());
    }

    public function testTranslationQuerySetsGeminiModel(): void
    {
        $result = $this->orchestrator->processMessage($this->user, 'Traduce articolul în engleză');

        $lastMessage = $result->getMessages()->last();
        $this->assertSame('translation', $lastMessage->getAgentType());
        $this->assertSame('gemini-2.5-flash', $lastMessage->getModel());
    }

    public function testContentQuerySetsClaudeModel(): void
    {
        $result = $this->orchestrator->processMessage($this->user, 'Rescrie textul acesta în ton formal');

        $lastMessage = $result->getMessages()->last();
        $this->assertSame('content', $lastMessage->getAgentType());
        $this->assertSame('claude-sonnet-4-20250514', $lastMessage->getModel());
    }

    public function testVaultQuerySetsClaudeModel(): void
    {
        $result = $this->orchestrator->processMessage($this->user, 'Caută dosare despre Sandu');

        $lastMessage = $result->getMessages()->last();
        $this->assertSame('vault', $lastMessage->getAgentType());
        $this->assertSame('claude-haiku-4-5-20251001', $lastMessage->getModel());
    }

    public function testClassifiesSeoQuery(): void
    {
        $result = $this->orchestrator->processMessage($this->user, 'Optimizează meta tags pentru SEO');

        $this->assertSame('seo', $result->getAgentType());
    }
}
