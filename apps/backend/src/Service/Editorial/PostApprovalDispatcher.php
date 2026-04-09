<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Entity\PressRelease;
use App\Message\Editorial\IngestArticleMessage;
use App\Message\TranslateArticleMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatches async messages after a PressRelease is approved and
 * an Article has been created + flushed (so IDs are available).
 */
class PostApprovalDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function dispatch(Article $article, PressRelease $pressRelease): void
    {
        $articleId = $article->getId();
        $locale = $pressRelease->getOriginalLanguage() ?? 'ro';

        // Translate to non-original locales
        $targetLocales = array_values(array_diff(['ro', 'en', 'ru'], [$locale]));
        if ($targetLocales !== []) {
            $this->messageBus->dispatch(new TranslateArticleMessage(
                articleId: $articleId,
                locales: $targetLocales,
            ));
        }

        // AI ingestion
        $this->messageBus->dispatch(new IngestArticleMessage(
            articleId: $articleId,
        ));

        $this->logger->info('Post-approval messages dispatched', [
            'articleId' => $articleId,
            'translateLocales' => $targetLocales,
        ]);
    }
}
