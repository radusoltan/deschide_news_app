<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\EvaluateTranslationMessage;
use App\Message\Editorial\SyncArticleToVaultMessage;
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

            // Trigger vault sync after translation evaluation/optimization (Sprint 19)
            $this->messageBus->dispatch(new SyncArticleToVaultMessage(
                articleId: $message->articleId,
                action: 'sync',
            ));

            $this->logger->info('EvaluateTranslationHandler: completed', [
                'articleId' => $result->articleId,
                'lang' => $result->targetLang,
                'initialScore' => $result->initialScore,
                'finalScore' => $result->finalScore,
                'iterations' => $result->iterations,
                'status' => $result->finalStatus,
            ]);
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
