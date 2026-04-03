<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Ai\AiChatRequest;
use App\Dto\Ai\AiChatResponse;
use App\Entity\AiConversation;
use App\Repository\AiConversationRepository;
use App\Repository\AiMessageRepository;
use App\Repository\AiPromptTemplateRepository;
use App\Security\Voter\AiConversationVoter;
use App\Service\Ai\AiOrchestratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/ai')]
#[IsGranted('ROLE_USER')]
final class AiChatController extends AbstractController
{
    public function __construct(
        private readonly AiOrchestratorService $orchestrator,
        private readonly AiConversationRepository $conversationRepository,
        private readonly AiMessageRepository $messageRepository,
        private readonly AiPromptTemplateRepository $templateRepository,
        private readonly RateLimiterFactoryInterface $aiChatLimiter,
    ) {}

    #[Route('/chat', methods: ['POST'])]
    public function chat(#[MapRequestPayload] AiChatRequest $request): JsonResponse
    {
        $user = $this->getUser();

        // Rate limiting
        $limiter = $this->aiChatLimiter->create($user->getUserIdentifier());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(
                ['error' => 'Prea multe cereri. Încearcă din nou mai târziu.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        // Sanitize input
        $message = strip_tags($request->message);

        $conversation = $this->orchestrator->processMessage(
            user: $user,
            message: $message,
            conversationId: $request->conversationId,
            templateId: $request->templateId,
            templateFields: $request->templateFields,
        );

        // Get the last assistant message
        $lastMessage = $conversation->getMessages()->last();

        return $this->json(new AiChatResponse(
            conversationId: (string) $conversation->getId(),
            messageId: (string) $lastMessage->getId(),
            content: $lastMessage->getContent(),
            agentType: $lastMessage->getAgentType() ?? $conversation->getAgentType() ?? 'content',
            model: $lastMessage->getModel(),
            tokensUsed: $lastMessage->getTokensUsed(),
        ));
    }

    #[Route('/conversations', methods: ['GET'])]
    public function listConversations(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(1, $request->query->getInt('limit', 20)));

        $conversations = $this->conversationRepository->findBy(
            ['user' => $user, 'status' => 'active'],
            ['createdAt' => 'DESC'],
            $limit,
            ($page - 1) * $limit,
        );

        $data = array_map(fn (AiConversation $c) => [
            'id' => (string) $c->getId(),
            'title' => $c->getTitle(),
            'agentType' => $c->getAgentType(),
            'status' => $c->getStatus(),
            'messageCount' => $c->getMessageCount(),
            'createdAt' => $c->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $c->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ], $conversations);

        return $this->json(['items' => $data, 'page' => $page]);
    }

    #[Route('/conversations/{id}/messages', methods: ['GET'])]
    public function conversationMessages(string $id): JsonResponse
    {
        $conversation = $this->conversationRepository->find(Uuid::fromString($id));

        if ($conversation === null) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(AiConversationVoter::VIEW, $conversation);

        $messages = $this->messageRepository->findBy(
            ['conversation' => $conversation],
            ['createdAt' => 'ASC'],
        );

        $data = array_map(fn ($m) => [
            'id' => (string) $m->getId(),
            'role' => $m->getRole(),
            'content' => $m->getContent(),
            'agentType' => $m->getAgentType(),
            'model' => $m->getModel(),
            'tokensUsed' => $m->getTokensUsed(),
            'metadata' => $m->getMetadata(),
            'createdAt' => $m->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ], $messages);

        return $this->json(['items' => $data]);
    }

    #[Route('/templates', methods: ['GET'])]
    public function listTemplates(): JsonResponse
    {
        $templates = $this->templateRepository->findBy(
            ['isActive' => true],
            ['sortOrder' => 'ASC', 'name' => 'ASC'],
        );

        $grouped = [];
        foreach ($templates as $t) {
            $category = $t->getCategory();
            $grouped[$category][] = [
                'id' => (string) $t->getId(),
                'name' => $t->getName(),
                'description' => $t->getDescription(),
                'agentType' => $t->getAgentType(),
                'promptTemplate' => $t->getPromptTemplate(),
                'requiredFields' => $t->getRequiredFields(),
            ];
        }

        return $this->json(['categories' => $grouped]);
    }
}
