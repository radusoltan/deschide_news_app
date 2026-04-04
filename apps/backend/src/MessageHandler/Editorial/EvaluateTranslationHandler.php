<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use App\Message\Editorial\EvaluateTranslationMessage;
use App\Repository\ArticleRepository;
use App\Service\NotificationService;
use App\Service\Translation\TranslationEvaluatorService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class EvaluateTranslationHandler
{
    public function __construct(
        private TranslationEvaluatorService $evaluator,
        private MessageBusInterface $messageBus,
        private ArticleRepository $articleRepository,
        private NotificationService $notificationService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(EvaluateTranslationMessage $message): void
    {
        try {
            $result = $this->evaluator->evaluateAndOptimize(
                $message->articleId,
                $message->targetLang,
                $message->maxIterations,
            );

            $this->logger->info('EvaluateTranslationHandler: completed', [
                'articleId' => $result->articleId,
                'lang' => $result->targetLang,
                'initialScore' => $result->initialScore,
                'finalScore' => $result->finalScore,
                'iterations' => $result->iterations,
                'status' => $result->finalStatus,
            ]);

            // Notify editors about evaluation result
            $article = $this->articleRepository->find($message->articleId);
            $articleTitle = $article?->getTitle() ?? 'Articol #' . $message->articleId;
            $lang = strtoupper($result->targetLang);
            $score = round($result->finalScore * 100);

            $importance = $result->finalStatus === 'complete'
                ? NotificationImportance::LOW
                : NotificationImportance::MEDIUM;

            $title = $result->finalStatus === 'complete'
                ? \sprintf('Evaluare traducere %s finalizată', $lang)
                : \sprintf('Evaluare traducere %s — revizie necesară', $lang);

            $this->notificationService->notify(
                type: NotificationType::ARTICLE_TRANSLATED,
                title: $title,
                message: \sprintf('„%s" — scor calitate: %d%% (%s)', $articleTitle, $score, $result->finalStatus),
                importance: $importance,
                relatedEntityType: 'article',
                relatedEntityId: $message->articleId,
                actionUrl: \sprintf('/admin/articles/%d/edit', $message->articleId),
            );
        } catch (\Throwable $e) {
            $this->logger->error('EvaluateTranslationHandler: failed', [
                'articleId' => $message->articleId,
                'lang' => $message->targetLang,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
