<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Article;
use App\Entity\ShortLink;
use App\Enum\ArticleStatus;
use App\Service\ShortCodeGenerator;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Automatically generates webcode and creates ShortLink when an article is published.
 *
 * This subscriber listens to Doctrine events and:
 * 1. Generates a unique webcode for the article
 * 2. Creates a corresponding ShortLink entity
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class ArticleWebcodeSubscriber
{
    public function __construct(
        private readonly ShortCodeGenerator $shortCodeGenerator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(string:APP_URL)%')]
        private readonly string $appUrl = 'https://deschide.md'
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Article) {
            return;
        }

        // Generate webcode for newly published articles
        if ($entity->getStatus() === ArticleStatus::PUBLISHED && !$entity->getWebcode()) {
            $this->generateWebcodeAndShortLink($entity, $args->getObjectManager());
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Article) {
            return;
        }

        // Only process if status changed to PUBLISHED and article doesn't have a webcode yet
        if ($args->hasChangedField('status')) {
            $newStatus = $args->getNewValue('status');

            if ($newStatus === ArticleStatus::PUBLISHED && !$entity->getWebcode()) {
                $this->generateWebcodeAndShortLink($entity, $args->getObjectManager());
            }
        }
    }

    private function generateWebcodeAndShortLink(Article $article, $entityManager): void
    {
        try {
            // Generate unique webcode
            // Use a temporary ID if not yet persisted
            $articleId = $article->getId() ?? random_int(100000, 999999);
            $webcode = $this->shortCodeGenerator->generateWebcode($articleId);

            // Set webcode on article
            $article->setWebcode($webcode);

            // Build the original URL for the article
            $locale = $article->getLocale() ?? 'ro';
            $slug = $article->getSlug();
            $originalUrl = sprintf('%s/%s/%s', $this->appUrl, $locale, $slug);

            // Create ShortLink entity
            $shortLink = new ShortLink();
            $shortLink->setCode($webcode);
            $shortLink->setOriginalUrl($originalUrl);
            $shortLink->setTitle($article->getTitle());
            $shortLink->setArticle($article);

            $entityManager->persist($shortLink);

            $this->logger->info('Generated webcode for article', [
                'article_id' => $article->getId(),
                'webcode' => $webcode,
                'short_url' => '/s/' . $webcode,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate webcode for article', [
                'article_id' => $article->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
