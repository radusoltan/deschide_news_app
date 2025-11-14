<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Message\PublishScheduledArticles;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PublishScheduledArticlesHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(PublishScheduledArticles $message): void
    {
        // Get current time
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Chisinau'));

        $this->logger->info('Running scheduled article publishing check', [
            'current_time' => $now->format('Y-m-d H:i:s'),
        ]);

        // Find articles that need to be published
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(Article::class, 'a')
            ->where('a.status = :status')
            ->andWhere('a.publishAt IS NOT NULL')
            ->andWhere('a.publishAt <= :now')
            ->setParameter('status', ArticleStatus::SUBMITTED)
            ->setParameter('now', $now);

        $articles = $qb->getQuery()->getResult();

        if (empty($articles)) {
            $this->logger->debug('No articles to publish at this time.');

            return;
        }

        $publishedCount = 0;

        foreach ($articles as $article) {
            try {
                // Publish the article
                $article->setStatus(ArticleStatus::PUBLISHED);
                $article->setPublishedAt(new DateTimeImmutable('now', new DateTimeZone('Europe/Chisinau')));

                $this->entityManager->persist($article);

                $this->logger->info('Published scheduled article', [
                    'article_id' => $article->getId(),
                    'title' => $article->getTitle(),
                    'publish_at' => $article->getPublishAt()?->format('Y-m-d H:i:s'),
                    'published_at' => $article->getPublishedAt()?->format('Y-m-d H:i:s'),
                ]);

                ++$publishedCount;
            } catch (Exception $e) {
                $this->logger->error('Failed to publish scheduled article', [
                    'article_id' => $article->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Flush all changes at once
        $this->entityManager->flush();

        if ($publishedCount > 0) {
            $this->logger->info(\sprintf('Successfully published %d article(s).', $publishedCount));
        }
    }
}
