<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TranslatePendingBatch;
use App\Repository\ArticleRepository;
use App\Service\TranslationPriorityDispatcher;
use App\Service\TranslationPriorityResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TranslatePendingBatchHandler
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private TranslationPriorityResolver $priorityResolver,
        private TranslationPriorityDispatcher $dispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TranslatePendingBatch $message): void
    {
        $articles = $this->findPendingArticles($message->limit * 3);

        if (empty($articles)) {
            return;
        }

        $dispatched = 0;

        foreach ($articles as $article) {
            $priority = $this->priorityResolver->resolve($article);

            if ($priority !== $message->priority) {
                continue;
            }

            $this->dispatcher->dispatch($article, ['ru', 'en']);
            ++$dispatched;

            if ($dispatched >= $message->limit) {
                break;
            }
        }

        if ($dispatched > 0) {
            $this->logger->info('TranslatePendingBatchHandler: dispatched batch', [
                'priority' => $message->priority->label(),
                'dispatched' => $dispatched,
            ]);
        }
    }

    /**
     * @return \App\Entity\Article[]
     */
    private function findPendingArticles(int $limit): array
    {
        return $this->articleRepository->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->where('a.status = :status')
            ->andWhere('a.translationStatus IS NULL OR a.translationStatus = :pending OR a.translationStatus = :failed')
            ->setParameter('status', 'published')
            ->setParameter('pending', 'pending')
            ->setParameter('failed', 'failed')
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
