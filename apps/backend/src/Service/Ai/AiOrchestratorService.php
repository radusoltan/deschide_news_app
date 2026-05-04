<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\AiConversation;
use App\Entity\AiMessage;
use App\Entity\User;
use App\Enum\AiAgentType;
use App\Repository\AiConversationRepository;
use App\Repository\AiPromptTemplateRepository;
use App\Repository\ArticleRepository;
use App\Service\Ai\Provider\AiProviderRegistry;
use App\Service\Editorial\DailyBriefingService;
use App\Service\Editorial\WeeklySummaryService;
use App\Service\Search\SearchService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class AiOrchestratorService
{
    /**
     * System prompt sent to the classifier model. The string "Clasifici cererea"
     * is the detection trigger used by MockAnthropicClient for test routing.
     */
    public const string CLASSIFIER_SYSTEM_PROMPT = <<<'PROMPT'
        Clasifici cererea utilizatorului în una din categoriile de mai jos și returnezi STRICT un JSON valid.

        Categorii disponibile:
        - "research"     — cercetare dosare, conexiuni între entități, analiză editorială, investigații
        - "content"      — generare sau editare conținut jurnalistic (titluri, lead-uri, articole)
        - "translation"  — traduceri între română, engleză, rusă; evaluare calitate traduceri
        - "briefing"     — briefing-uri zilnice, rezumate săptămânale, tendințe
        - "seo"          — optimizare SEO, meta tags, keywords, meta description

        Răspunde DOAR cu JSON:
        {"agentType": "<categorie>", "refinedPrompt": "<cererea reformulată clar>"}
        PROMPT;

    private const int AUTO_TITLE_MAX_LENGTH = 60;

    public function __construct(
        private readonly AnthropicClientInterface $classifierClient,
        private readonly AiProviderRegistry $providerRegistry,
        private readonly EntityManagerInterface $em,
        private readonly AiMercureService $mercure,
        private readonly LoggerInterface $logger,
        private readonly AiConversationRepository $conversationRepository,
        private readonly AiPromptTemplateRepository $templateRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly SearchService $searchService,
        private readonly DailyBriefingService $dailyBriefingService,
        private readonly WeeklySummaryService $weeklySummaryService,
        private readonly MessageBusInterface $messageBus,
    ) {}

    /**
     * Process a user message: classify intent, route to the correct agent,
     * persist messages, and publish real-time events via Mercure.
     *
     * @param array<string, string> $templateFields
     */
    public function processMessage(
        User $user,
        string $message,
        ?string $conversationId = null,
        ?string $templateId = null,
        array $templateFields = [],
    ): AiConversation {
        $conversation = $this->resolveConversation($user, $conversationId);

        // Determine agent type — from template or via classification
        $agentType = $this->resolveAgentType($message, $templateId);

        $conversation->setAgentType($agentType->value);

        // Build the effective prompt (template-expanded or raw message)
        $effectivePrompt = $this->buildEffectivePrompt($message, $templateId, $templateFields);

        // Add user message
        $userMessage = $this->createMessage($conversation, 'user', $effectivePrompt);

        // Auto-title on first message
        if ($conversation->getMessageCount() === 1 && $conversation->getTitle() === null) {
            $conversation->setTitle($this->generateAutoTitle($effectivePrompt));
        }

        // Publish typing indicator
        $this->mercure->publishTyping(
            (string) $conversation->getId(),
            $agentType->value,
        );

        // Route to the appropriate handler and get a response
        try {
            $responseText = $this->routeToAgent($agentType, $effectivePrompt, $conversation);
        } catch (\Throwable $e) {
            $this->logger->error('AI agent call failed', [
                'agent' => $agentType->value,
                'conversation_id' => (string) $conversation->getId(),
                'error' => $e->getMessage(),
            ]);
            $responseText = 'Eroare internă: ' . $e->getMessage();
        }

        // Resolve the model used via the provider registry
        $provider = $this->providerRegistry->getProvider($agentType);
        $model = $provider->getModelForAgent($agentType);

        // Add assistant message
        $assistantMessage = $this->createMessage(
            $conversation,
            'assistant',
            $responseText,
            $agentType->value,
            $model,
        );

        // Publish the assistant message via Mercure
        $this->mercure->publishMessage(
            (string) $conversation->getId(),
            $assistantMessage->getContent(),
        );

        // Persist
        $this->em->persist($conversation);
        $this->em->flush();

        return $conversation;
    }

    // -------------------------------------------------------------------------
    // Conversation resolution
    // -------------------------------------------------------------------------

    private function resolveConversation(User $user, ?string $conversationId): AiConversation
    {
        if ($conversationId === null) {
            $conversation = new AiConversation();
            $conversation->setUser($user);

            return $conversation;
        }

        $conversation = $this->conversationRepository->find($conversationId);

        if ($conversation === null) {
            throw new \InvalidArgumentException('Conversation not found');
        }

        if ($conversation->getUser()->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('Access denied');
        }

        return $conversation;
    }

    // -------------------------------------------------------------------------
    // Intent classification
    // -------------------------------------------------------------------------

    private function resolveAgentType(string $message, ?string $templateId): AiAgentType
    {
        // If a template is provided, use its agent type directly
        if ($templateId !== null) {
            $template = $this->templateRepository->find($templateId);

            if ($template !== null) {
                return AiAgentType::from($template->getAgentType());
            }
        }

        return $this->classifyIntent($message);
    }

    private function classifyIntent(string $message): AiAgentType
    {
        try {
            $response = $this->classifierClient->chat(
                [['role' => 'user', 'content' => $message]],
                'claude-haiku-4-5-20251001',
                self::CLASSIFIER_SYSTEM_PROMPT,
            );

            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            $agentTypeValue = $decoded['agentType'] ?? 'content';

            return AiAgentType::tryFrom($agentTypeValue) ?? AiAgentType::CONTENT;
        } catch (\Throwable $e) {
            $this->logger->warning('Intent classification failed, defaulting to content', [
                'error' => $e->getMessage(),
            ]);

            return AiAgentType::CONTENT;
        }
    }

    // -------------------------------------------------------------------------
    // Prompt building (template expansion)
    // -------------------------------------------------------------------------

    /**
     * @param array<string, string> $templateFields
     */
    private function buildEffectivePrompt(
        string $message,
        ?string $templateId,
        array $templateFields,
    ): string {
        if ($templateId === null) {
            return $message;
        }

        $template = $this->templateRepository->find($templateId);

        if ($template === null) {
            return $message;
        }

        $prompt = $template->getPromptTemplate();

        foreach ($templateFields as $field => $value) {
            $prompt = str_replace(sprintf('{%s}', $field), $value, $prompt);
        }

        return $prompt;
    }

    // -------------------------------------------------------------------------
    // Agent routing
    // -------------------------------------------------------------------------

    private function routeToAgent(
        AiAgentType $agentType,
        string $prompt,
        AiConversation $conversation,
    ): string {
        return match ($agentType) {
            AiAgentType::RESEARCH => $this->handleResearchQuery($prompt, $conversation),
            AiAgentType::CONTENT => $this->handleContentQuery($prompt, $conversation),
            AiAgentType::TRANSLATION => $this->handleTranslationQuery($prompt, $conversation),
            AiAgentType::BRIEFING => $this->handleBriefingQuery($prompt, $conversation),
            AiAgentType::SEO => $this->handleSeoQuery($prompt, $conversation),
        };
    }

    private function handleResearchQuery(string $prompt, AiConversation $conversation): string
    {
        // Augment with search results when available
        $searchResults = $this->searchService->search($prompt);
        $context = $this->buildSearchContext($searchResults);

        $fullPrompt = $context !== '' ? $context . "\n\n" . $prompt : $prompt;

        $provider = $this->providerRegistry->getProvider(AiAgentType::RESEARCH);

        return $provider->chat($fullPrompt, AiAgentType::RESEARCH->getSystemPrompt());
    }

    private function handleContentQuery(string $prompt, AiConversation $conversation): string
    {
        $provider = $this->providerRegistry->getProvider(AiAgentType::CONTENT);

        return $provider->chat($prompt, AiAgentType::CONTENT->getSystemPrompt());
    }

    private function handleTranslationQuery(string $prompt, AiConversation $conversation): string
    {
        $provider = $this->providerRegistry->getProvider(AiAgentType::TRANSLATION);

        return $provider->chat($prompt, AiAgentType::TRANSLATION->getSystemPrompt());
    }

    private function handleBriefingQuery(string $prompt, AiConversation $conversation): string
    {
        // Try to use the daily briefing service for enrichment
        $briefing = $this->dailyBriefingService->generateDailyBriefing();
        $context = $briefing !== null ? "Briefing-ul zilei:\n" . $briefing . "\n\n" : '';

        $fullPrompt = $context . $prompt;
        $provider = $this->providerRegistry->getProvider(AiAgentType::BRIEFING);

        return $provider->chat($fullPrompt, AiAgentType::BRIEFING->getSystemPrompt());
    }

    private function handleSeoQuery(string $prompt, AiConversation $conversation): string
    {
        $provider = $this->providerRegistry->getProvider(AiAgentType::SEO);

        return $provider->chat($prompt, AiAgentType::SEO->getSystemPrompt());
    }

    // -------------------------------------------------------------------------
    // Message persistence
    // -------------------------------------------------------------------------

    private function createMessage(
        AiConversation $conversation,
        string $role,
        string $content,
        ?string $agentType = null,
        ?string $model = null,
    ): AiMessage {
        $message = new AiMessage();
        $message->setRole($role);
        $message->setContent($content);
        $message->setAgentType($agentType);
        $message->setModel($model);

        $conversation->addMessage($message);
        $conversation->incrementMessageCount();

        return $message;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function generateAutoTitle(string $message): string
    {
        $title = mb_substr(trim($message), 0, self::AUTO_TITLE_MAX_LENGTH);

        if (mb_strlen(trim($message)) > self::AUTO_TITLE_MAX_LENGTH) {
            $title .= '...';
        }

        return $title;
    }

    /**
     * @param array{total: int, hits: list<mixed>} $searchResults
     */
    private function buildSearchContext(array $searchResults): string
    {
        if ($searchResults['total'] === 0 || $searchResults['hits'] === []) {
            return '';
        }

        $lines = ['Rezultate relevante din arhivă:'];

        foreach ($searchResults['hits'] as $hit) {
            $source = $hit['source'] ?? [];
            $title = $source['title_ro'] ?? $source['title'] ?? 'Fără titlu';
            $lines[] = sprintf('- %s', $title);
        }

        return implode("\n", $lines);
    }
}
