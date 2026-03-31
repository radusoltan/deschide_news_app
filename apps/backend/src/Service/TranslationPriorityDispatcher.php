<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Enum\TranslationPriority;
use App\Message\TranslateArticleMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final readonly class TranslationPriorityDispatcher
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private TranslationPriorityResolver $priorityResolver,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Dispatch a translation message to the correct priority queue.
     *
     * @param string[] $locales
     */
    public function dispatch(
        Article $article,
        array $locales = ['ru', 'en'],
        bool $forceRetranslate = false,
        ?TranslationPriority $overridePriority = null,
    ): void {
        $articleId = $article->getId();

        if ($articleId === null) {
            return;
        }

        $priority = $overridePriority ?? $this->priorityResolver->resolve($article);

        $message = new TranslateArticleMessage(
            articleId: $articleId,
            locales: $locales,
            forceRetranslate: $forceRetranslate,
            priority: $priority,
        );

        $transport = self::queueName($priority);

        $this->messageBus->dispatch(
            new Envelope($message, [new TransportNamesStamp([$transport])])
        );

        $this->logger->info('Translation dispatched with priority', [
            'articleId' => $articleId,
            'priority' => $priority->label(),
            'transport' => $transport,
            'locales' => $locales,
        ]);
    }

    public static function queueName(TranslationPriority $priority): string
    {
        return match ($priority) {
            TranslationPriority::CRITICAL => 'translations_critical',
            TranslationPriority::URGENT => 'translations_urgent',
            TranslationPriority::HIGH => 'translations_high',
            TranslationPriority::NORMAL => 'translations',
        };
    }
}
