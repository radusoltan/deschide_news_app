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
 * Dispatches async messages once an Article has been created + flushed.
 *
 * Two entry paths — same downstream pipeline:
 *  1. Editorial PressRelease approval (legacy, Sprint 24+): Article derived
 *     from a PressRelease. Original locale comes from the press release.
 *  2. Editorial AI pipeline signal cluster (Sprint 55, ADR-020): Article
 *     emitted by FlashWriter / DevelopingStoryWriter from a verified
 *     SourceSignal cluster. There is no PressRelease; the writer supplies
 *     the locale directly.
 *
 * The two paths fan out to the exact same async work (translate + ingest),
 * so the only thing that changes is how the original locale is resolved.
 */
class PostApprovalDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param PressRelease|null $pressRelease Null when dispatched from a signal-originated writer
     *                                        (Sprint 55 T55.5 widening).
     * @param string            $originalLocale Used only when $pressRelease is null.
     */
    public function dispatch(
        Article $article,
        ?PressRelease $pressRelease = null,
        string $originalLocale = 'ro',
    ): void {
        $articleId = $article->getId();
        $locale = $pressRelease?->getOriginalLanguage() ?? $originalLocale;

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
            'originLocale' => $locale,
            'source' => $pressRelease !== null ? 'press_release' : 'signal_cluster',
        ]);
    }
}
