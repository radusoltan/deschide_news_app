<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Article;
use App\Enum\Editorial\VerdictType;
use App\Message\Editorial\WriteDevelopingStoryMessage;
use App\Repository\ArticleRepository;
use App\Repository\Editorial\SourceSignalRepository;
use App\Service\Editorial\Writer\DevelopingStoryWriter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Async handler for {@see WriteDevelopingStoryMessage} (Sprint 55 T55.4)
 * running on the `editorial_flash` transport.
 *
 * Same defensive posture as {@see WriteFlashMessageHandler}: missing Article
 * or primary signal, unknown verdict type all log + no-op so Messenger does
 * not retry stale messages.
 */
#[AsMessageHandler]
class WriteDevelopingStoryMessageHandler
{
    public function __construct(
        private readonly DevelopingStoryWriter $developingStoryWriter,
        private readonly ArticleRepository $articleRepository,
        private readonly SourceSignalRepository $signalRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(WriteDevelopingStoryMessage $message): ?Article
    {
        $article = $this->articleRepository->find($message->articleId);
        if ($article === null) {
            $this->logger->warning('write_developing_article_missing', [
                'article_id' => $message->articleId,
            ]);

            return null;
        }

        $primary = $this->signalRepository->find($message->primarySignalId);
        if ($primary === null) {
            $this->logger->warning('write_developing_primary_signal_missing', [
                'article_id' => $message->articleId,
                'primary_signal_id' => $message->primarySignalId,
            ]);

            return null;
        }

        $supporting = [];
        foreach ($message->supportingSignalIds as $id) {
            $signal = $this->signalRepository->find($id);
            if ($signal !== null) {
                $supporting[] = $signal;
            }
        }

        $verdictType = VerdictType::tryFrom($message->verdictType);
        if ($verdictType === null) {
            $this->logger->error('write_developing_unknown_verdict_type', [
                'verdict_type' => $message->verdictType,
                'article_id' => $message->articleId,
            ]);

            return null;
        }

        $verdict = new VerificationVerdict(
            type: $verdictType,
            reasoning: 'dispatched-from-verify-claim-handler',
            confidence: $this->extractConfidence($primary),
        );

        return $this->developingStoryWriter->write($article, $primary, $supporting, $verdict);
    }

    private function extractConfidence(\App\Entity\Editorial\SourceSignal $signal): float
    {
        $snapshot = $signal->getClaimGraphSnapshot();
        if (!\is_array($snapshot)) {
            return 1.0;
        }
        $confidence = $snapshot['verdict_confidence'] ?? $snapshot['confidence'] ?? null;

        return \is_numeric($confidence) ? (float) $confidence : 1.0;
    }
}
