<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\EvaluateTranslationMessage;
use App\Service\Translation\TranslationEvaluatorService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EvaluateTranslationHandler
{
    public function __construct(
        private TranslationEvaluatorService $evaluator,
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
