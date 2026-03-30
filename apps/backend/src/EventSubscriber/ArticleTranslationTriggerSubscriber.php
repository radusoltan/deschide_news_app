<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Message\TranslateArticleMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(event: Events::preUpdate)]
final class ArticleTranslationTriggerSubscriber
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Article) {
            return;
        }

        // Path 1: Auto-trigger when status changes to SUBMITTED
        if ($args->hasChangedField('status')) {
            $newStatus = $args->getNewValue('status');

            if ($newStatus === ArticleStatus::SUBMITTED) {
                $locales = $this->resolveLocales($entity, force: false);

                if (!empty($locales)) {
                    $entity->setTranslationStatus('pending');
                    $this->dispatch($entity, $locales, forceRetranslate: false);
                }
            }
        }

        // Path 2: Manual trigger when requestTranslation changes to true
        if ($args->hasChangedField('requestTranslation')) {
            $newValue = $args->getNewValue('requestTranslation');

            if ($newValue === true) {
                $locales = $this->resolveLocales($entity, force: true);
                $entity->setTranslationStatus('pending');
                $this->dispatch($entity, $locales, forceRetranslate: true);
            }
        }
    }

    /**
     * @return string[]
     */
    private function resolveLocales(Article $entity, bool $force): array
    {
        if ($force) {
            return ['ru', 'en'];
        }

        // Check which translations are missing (no title = not translated)
        // Since Gedmo stores translations in ext_translations, we check translationStatus
        // If article has never been translated, translate both locales
        if ($entity->getTranslationStatus() === null || $entity->getTranslationStatus() === 'failed') {
            return ['ru', 'en'];
        }

        return [];
    }

    /**
     * @param string[] $locales
     */
    private function dispatch(Article $entity, array $locales, bool $forceRetranslate): void
    {
        $articleId = $entity->getId();

        if ($articleId === null) {
            return;
        }

        $this->logger->info('Dispatching translation for article', [
            'articleId' => $articleId,
            'locales' => $locales,
            'force' => $forceRetranslate,
        ]);

        $this->messageBus->dispatch(
            new TranslateArticleMessage(
                articleId: $articleId,
                locales: $locales,
                forceRetranslate: $forceRetranslate,
            )
        );
    }
}
