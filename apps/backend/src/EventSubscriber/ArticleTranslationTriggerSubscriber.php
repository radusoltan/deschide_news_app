<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Service\TranslationPriorityDispatcher;
use App\Service\TranslationPriorityResolver;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsDoctrineListener(event: Events::preUpdate)]
final class ArticleTranslationTriggerSubscriber
{
    public function __construct(
        private readonly TranslationPriorityDispatcher $dispatcher,
        private readonly TranslationPriorityResolver $priorityResolver,
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
                    $priority = $this->priorityResolver->resolve($entity);

                    if ($priority->isInstant()) {
                        // P0/P1: dispatch immediately to priority queue
                        $entity->setTranslationStatus('pending');
                        $this->dispatcher->dispatch($entity, $locales);
                    } else {
                        // P2/P3: mark as pending for batch processing
                        $entity->setTranslationStatus('pending');
                        $this->logger->info('Article marked pending for batch translation', [
                            'articleId' => $entity->getId(),
                            'priority' => $priority->label(),
                        ]);
                    }
                }
            }
        }

        // Path 2: Manual trigger when requestTranslation changes to true
        if ($args->hasChangedField('requestTranslation')) {
            $newValue = $args->getNewValue('requestTranslation');

            if ($newValue === true) {
                $locales = $this->resolveLocales($entity, force: true);
                $entity->setTranslationStatus('pending');
                // Manual requests always dispatch immediately
                $this->dispatcher->dispatch($entity, $locales, forceRetranslate: true);
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

        if ($entity->getTranslationStatus() === null || $entity->getTranslationStatus() === 'failed') {
            return ['ru', 'en'];
        }

        return [];
    }
}
