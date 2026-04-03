<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\AiConversation;
use App\Entity\AiMessage;
use App\Entity\AiPromptTemplate;
use App\Entity\User;
use App\Enum\AiAgentType;
use App\Message\TranslateArticleMessage;
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
use Symfony\Component\Uid\Uuid;

final class AiOrchestratorService
{
    private const CLASSIFIER_SYSTEM_PROMPT = <<<'PROMPT'
        Ești secretarul de redacție al Deschide.md. Clasifici cererea jurnalistului.
        Categorii: vault (cercetare dosare, conexiuni, căutări), content (generare/editare articole, lead-uri, titluri, rescieri), translation (traduceri, evaluări traduceri), briefing (rezumate, briefing-uri, tendințe), seo (optimizare SEO, meta tags, keywords, meta description, titlu SEO).
        Răspunde DOAR cu JSON valid: {"agentType": "vault|content|translation|briefing|seo", "refinedPrompt": "cererea reformulată concis"}
        PROMPT;

    public function __construct(
        private readonly AnthropicClientInterface $anthropicClient,
        private readonly AiProviderRegistry $providerRegistry,
        private readonly EntityManagerInterface $em,
        private readonly AiMercureService $mercureService,
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
     * Process a user message: classify intent, route to agent, return conversation.
     */
    public function processMessage(
        User $user,
        string $message,
        ?string $conversationId = null,
        ?string $templateId = null,
        ?array $templateFields = null,
    ): AiConversation {
        $startTime = microtime(true);

        // Resolve or create conversation
        $conversation = $conversationId !== null
            ? $this->resolveConversation($conversationId, $user)
            : $this->createConversation($user);

        $convId = (string) $conversation->getId();

        // If template provided, resolve and compose message
        $agentType = null;
        if ($templateId !== null) {
            $template = $this->templateRepository->find(Uuid::fromString($templateId));
            if ($template instanceof AiPromptTemplate) {
                $agentType = AiAgentType::from($template->getAgentType());
                $message = $this->resolveTemplate($template, $message, $templateFields ?? []);
            }
        }

        // Save user message
        $userMessage = $this->createMessage($conversation, 'user', $message);
        $this->em->persist($userMessage);
        $conversation->incrementMessageCount();

        // Classify intent if no template
        if ($agentType === null) {
            $agentType = $this->classifyIntent($message);
        }

        // Update conversation agent type
        if ($conversation->getAgentType() === null) {
            $conversation->setAgentType($agentType->value);
        }

        // Publish typing indicator
        $this->mercureService->publishTyping($convId, $agentType->value);

        // Route to agent and get response
        try {
            $responseText = $this->routeToAgent($agentType, $message, $conversation, $templateFields);
        } catch (\Throwable $e) {
            $this->logger->error('AI agent failed', [
                'agentType' => $agentType->value,
                'error' => $e->getMessage(),
                'conversationId' => $convId,
            ]);
            $this->mercureService->publishError($convId, 'Eroare la procesare. Încearcă din nou.');
            $responseText = 'Ne pare rău, a apărut o eroare la procesarea cererii. Te rugăm să încerci din nou.';
        }

        // Save assistant message with provider/model info
        $assistantMessage = $this->createMessage($conversation, 'assistant', $responseText);
        $assistantMessage->setAgentType($agentType->value);
        $assistantMessage->setModel($agentType->getModel());

        // Extract token metrics from CLI client (Anthropic agents only)
        if ($agentType->getProvider() === 'anthropic' && $this->anthropicClient instanceof ClaudeCliClient) {
            $metrics = $this->anthropicClient->getLastMetrics();
            if ($metrics !== null) {
                $totalTokens = $metrics['input_tokens'] + $metrics['output_tokens']
                    + $metrics['cache_read_tokens'] + $metrics['cache_creation_tokens'];
                $assistantMessage->setTokensUsed($totalTokens);
                $assistantMessage->setMetadata([
                    'input_tokens' => $metrics['input_tokens'],
                    'output_tokens' => $metrics['output_tokens'],
                    'cache_read_tokens' => $metrics['cache_read_tokens'],
                    'cache_creation_tokens' => $metrics['cache_creation_tokens'],
                    'cost_usd' => $metrics['cost_usd'],
                    'duration_api_ms' => $metrics['duration_api_ms'] ?? 0,
                    'provider' => 'anthropic',
                ]);
                $conversation->addTokensUsed($totalTokens);
            }
        } elseif ($agentType->getProvider() === 'gemini') {
            $assistantMessage->setMetadata([
                'provider' => 'gemini',
                'model' => 'gemini-2.5-flash',
            ]);
        }

        $this->em->persist($assistantMessage);
        $conversation->incrementMessageCount();

        // Auto-generate title on first exchange
        if ($conversation->getTitle() === null && $conversation->getMessageCount() <= 2) {
            $conversation->setTitle($this->generateTitle($message));
        }

        $this->em->flush();

        // Publish message via Mercure
        $this->mercureService->publishMessage($convId, $assistantMessage);

        $duration = round((microtime(true) - $startTime) * 1000);
        $this->logger->info('AI request processed', [
            'conversationId' => $convId,
            'agentType' => $agentType->value,
            'provider' => $agentType->getProvider(),
            'model' => $agentType->getModel(),
            'duration_ms' => $duration,
            'user' => $user->getUserIdentifier(),
        ]);

        return $conversation;
    }

    private function resolveConversation(string $conversationId, User $user): AiConversation
    {
        $conversation = $this->conversationRepository->find(Uuid::fromString($conversationId));

        if ($conversation === null) {
            throw new \InvalidArgumentException('Conversation not found');
        }

        if ($conversation->getUser()->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('Access denied to conversation');
        }

        return $conversation;
    }

    private function createConversation(User $user): AiConversation
    {
        $conversation = new AiConversation();
        $conversation->setUser($user);
        $this->em->persist($conversation);

        return $conversation;
    }

    private function createMessage(AiConversation $conversation, string $role, string $content): AiMessage
    {
        $message = new AiMessage();
        $message->setRole($role);
        $message->setContent($content);
        $conversation->addMessage($message);

        return $message;
    }

    private function classifyIntent(string $message): AiAgentType
    {
        try {
            $response = $this->anthropicClient->chat(
                messages: [['role' => 'user', 'content' => $message]],
                model: 'claude-haiku-4-5-20251001',
                system: self::CLASSIFIER_SYSTEM_PROMPT,
            );

            $data = json_decode($response, true);
            if (\is_array($data) && isset($data['agentType'])) {
                $agent = AiAgentType::tryFrom($data['agentType']);
                if ($agent !== null) {
                    return $agent;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Intent classification failed, defaulting to content', [
                'error' => $e->getMessage(),
            ]);
        }

        return AiAgentType::CONTENT;
    }

    private function routeToAgent(AiAgentType $agentType, string $message, AiConversation $conversation, ?array $templateFields = null): string
    {
        return match ($agentType) {
            AiAgentType::VAULT => $this->handleVaultQuery($message, $conversation),
            AiAgentType::CONTENT => $this->handleContentRequest($message, $conversation),
            AiAgentType::TRANSLATION => $this->handleTranslationRequest($message, $conversation, $templateFields),
            AiAgentType::BRIEFING => $this->handleBriefingRequest($message, $conversation),
            AiAgentType::SEO => $this->handleSeoRequest($message),
        };
    }

    private function handleVaultQuery(string $message, AiConversation $conversation): string
    {
        // Search Elasticsearch for relevant articles
        $context = '';
        try {
            $results = $this->searchService->search($message, 'ro', 1, 10);
            if ($results['total'] > 0) {
                $context = "Rezultate din vault-ul editorial:\n\n";
                foreach ($results['hits'] as $i => $hit) {
                    $source = $hit['source'] ?? [];
                    $title = $source['title'] ?? 'Fără titlu';
                    $highlight = $hit['highlight']['content'][0] ?? ($source['lead'] ?? '');
                    $context .= sprintf("%d. **%s** (ID: %s)\n   %s\n\n", $i + 1, $title, $hit['id'], $highlight);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Vault search failed', ['error' => $e->getMessage()]);
        }

        $systemPrompt = AiAgentType::VAULT->getSystemPrompt();
        if ($context !== '') {
            $systemPrompt .= "\n\nContext din vault:\n" . $context;
        }

        return $this->anthropicClient->chat(
            messages: $this->buildConversationHistory($conversation, $message),
            model: AiAgentType::VAULT->getModel(),
            system: $systemPrompt,
        );
    }

    private function handleContentRequest(string $message, AiConversation $conversation): string
    {
        return $this->anthropicClient->chat(
            messages: $this->buildConversationHistory($conversation, $message),
            model: AiAgentType::CONTENT->getModel(),
            system: AiAgentType::CONTENT->getSystemPrompt(),
        );
    }

    private function handleTranslationRequest(string $message, AiConversation $conversation, ?array $templateFields = null): string
    {
        // Branch 1: Template with articleId → dispatch async translation via existing pipeline
        $articleId = $templateFields['articleId'] ?? null;
        if ($articleId !== null && is_numeric($articleId)) {
            return $this->dispatchArticleTranslation((int) $articleId, $templateFields['locales'] ?? 'en,ru');
        }

        // Branch 2: Free-form translation → Gemini CLI direct
        $provider = $this->providerRegistry->getProvider(AiAgentType::TRANSLATION);

        return $provider->chat($message, AiAgentType::TRANSLATION->getSystemPrompt());
    }

    private function dispatchArticleTranslation(int $articleId, string $localesStr): string
    {
        $article = $this->articleRepository->find($articleId);
        if ($article === null) {
            return sprintf('Articolul cu ID %d nu a fost găsit în baza de date.', $articleId);
        }

        $locales = array_map('trim', explode(',', $localesStr));
        $locales = array_filter($locales, fn (string $l) => \in_array($l, ['en', 'ru'], true));

        if ($locales === []) {
            return 'Limbi invalide. Limbile suportate sunt: en (engleză), ru (rusă).';
        }

        $this->messageBus->dispatch(new TranslateArticleMessage(
            articleId: $articleId,
            locales: array_values($locales),
            forceRetranslate: true,
        ));

        $localeLabels = array_map(fn (string $l) => match ($l) {
            'en' => 'engleză',
            'ru' => 'rusă',
            default => $l,
        }, $locales);

        return sprintf(
            "Traducerea articolului **#%d** (%s) in %s a fost lansata.\n\n"
            . "Procesul ruleaza asincron prin pipeline-ul Gemini dedicat traducerilor. "
            . "Rezultatele vor fi salvate automat in campurile de traducere ale articolului.\n\n"
            . "Poti verifica statusul in lista de articole (coloana Traduceri).",
            $articleId,
            mb_substr($article->getTitle() ?? '', 0, 60),
            implode(' si ', $localeLabels),
        );
    }

    private function handleBriefingRequest(string $message, AiConversation $conversation): string
    {
        // Augment with real briefing data when available
        $context = '';
        $lowerMessage = mb_strtolower($message);

        try {
            if (str_contains($lowerMessage, 'săptămânal') || str_contains($lowerMessage, 'weekly')) {
                $summary = $this->weeklySummaryService->generateWeeklySummary();
                if ($summary !== null) {
                    $context = "\n\nRezumat săptămânal generat:\n" . $summary;
                }
            } elseif (str_contains($lowerMessage, 'zilnic') || str_contains($lowerMessage, 'briefing')) {
                $briefing = $this->dailyBriefingService->generateDailyBriefing();
                if ($briefing !== null) {
                    $context = "\n\nBriefing zilnic generat:\n" . $briefing;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Briefing data generation failed', ['error' => $e->getMessage()]);
        }

        $systemPrompt = AiAgentType::BRIEFING->getSystemPrompt();
        if ($context !== '') {
            $systemPrompt .= $context;
        }

        return $this->anthropicClient->chat(
            messages: $this->buildConversationHistory($conversation, $message),
            model: AiAgentType::BRIEFING->getModel(),
            system: $systemPrompt,
        );
    }

    private function handleSeoRequest(string $message): string
    {
        $provider = $this->providerRegistry->getProvider(AiAgentType::SEO);

        return $provider->chat($message, AiAgentType::SEO->getSystemPrompt());
    }

    /**
     * Build message history for multi-turn conversation (last 10 messages).
     * Used by Anthropic agents that support the messages array format.
     *
     * @return list<array{role: string, content: string}>
     */
    private function buildConversationHistory(AiConversation $conversation, string $currentMessage): array
    {
        $history = [];

        // Include last 10 messages for context
        $messages = $conversation->getMessages()->slice(-10);
        foreach ($messages as $msg) {
            if (\in_array($msg->getRole(), ['user', 'assistant'], true)) {
                $history[] = [
                    'role' => $msg->getRole(),
                    'content' => $msg->getContent(),
                ];
            }
        }

        // The current message is already saved but we include it explicitly
        // to ensure it's in the API call
        if (empty($history) || $history[array_key_last($history)]['content'] !== $currentMessage) {
            $history[] = [
                'role' => 'user',
                'content' => $currentMessage,
            ];
        }

        return $history;
    }

    /**
     * Build a single prompt string with conversation history.
     * Used by Gemini agents that take a single prompt input.
     */
    private function buildPromptWithHistory(AiConversation $conversation, string $currentMessage): string
    {
        $parts = [];
        $messages = $conversation->getMessages()->slice(-10);

        foreach ($messages as $msg) {
            if (\in_array($msg->getRole(), ['user', 'assistant'], true)) {
                $role = $msg->getRole() === 'user' ? 'Utilizator' : 'Asistent';
                $parts[] = "[{$role}]\n" . $msg->getContent();
            }
        }

        // Add current message if not already last
        $lastMsg = !empty($messages) ? end($messages) : null;
        $lastContent = $lastMsg?->getContent();
        if ($lastContent !== $currentMessage) {
            $parts[] = "[Utilizator]\n" . $currentMessage;
        }

        return implode("\n\n", $parts);
    }

    private function resolveTemplate(AiPromptTemplate $template, string $originalMessage, array $fields): string
    {
        $prompt = $template->getPromptTemplate();

        foreach ($fields as $key => $value) {
            $prompt = str_replace('{' . $key . '}', (string) $value, $prompt);
        }

        // If there's still user text, append it
        if ($originalMessage !== '' && $originalMessage !== $prompt) {
            $prompt .= "\n\nContext suplimentar: " . $originalMessage;
        }

        return $prompt;
    }

    private function generateTitle(string $message): string
    {
        // Simple title: first 60 chars of the message
        $title = mb_substr(strip_tags($message), 0, 60);
        if (mb_strlen($message) > 60) {
            $title .= '…';
        }

        return $title;
    }
}
