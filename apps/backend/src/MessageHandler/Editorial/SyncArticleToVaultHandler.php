<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Entity\Article;
use App\Message\Editorial\SyncArticleToVaultMessage;
use App\Service\Editorial\DbToVaultSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncArticleToVaultHandler
{
    public function __construct(
        private DbToVaultSyncService $syncService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SyncArticleToVaultMessage $message): void
    {
        if ($message->action === 'archive') {
            $slug = $message->slug ?? 'unknown-' . $message->articleId;
            $createdAt = $message->createdAt !== null
                ? new \DateTimeImmutable($message->createdAt)
                : null;

            $result = $this->syncService->archiveArticleFromVault(
                $message->articleId,
                $slug,
                $createdAt,
            );

            $this->logger->info('SyncArticleToVaultHandler: archive result', [
                'articleId' => $message->articleId,
                'action' => $result->action,
            ]);

            return;
        }

        $article = $this->em->find(Article::class, $message->articleId);

        if ($article === null) {
            $this->logger->warning('SyncArticleToVaultHandler: article not found, skipping', [
                'articleId' => $message->articleId,
            ]);

            return;
        }

        $result = $this->syncService->syncArticleToVault($article);

        $this->logger->info('SyncArticleToVaultHandler: sync result', [
            'articleId' => $message->articleId,
            'action' => $result->action,
            'path' => $result->vaultPath,
        ]);
    }
}
