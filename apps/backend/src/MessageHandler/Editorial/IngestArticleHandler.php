<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Entity\Article;
use App\Message\Editorial\IngestArticleMessage;
use App\Service\Editorial\ArticleIngestionService;
use App\Service\Editorial\ConnectionDetectionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IngestArticleHandler
{
    public function __construct(
        private ArticleIngestionService $ingestionService,
        private ConnectionDetectionService $connectionService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private string $vaultPath = '',
    ) {}

    public function __invoke(IngestArticleMessage $message): void
    {
        $article = $this->em->getRepository(Article::class)->find($message->articleId);

        if ($article === null) {
            $this->logger->warning('IngestArticleHandler: article not found', [
                'articleId' => $message->articleId,
            ]);

            return;
        }

        // Skip if already ingested (prevents double-processing)
        if ($article->getIngestedAt() !== null) {
            $this->logger->debug('IngestArticleHandler: already ingested, skipping', [
                'articleId' => $article->getId(),
            ]);

            return;
        }

        $this->logger->info('IngestArticleHandler: starting ingestion', [
            'articleId' => $article->getId(),
            'title' => mb_substr($article->getTitle() ?? '', 0, 80),
        ]);

        // Step 1: Extract entities via Gemini
        $entities = $this->ingestionService->extractEntities($article);

        if (!$entities->hasEntities()) {
            $this->logger->info('IngestArticleHandler: no entities extracted', [
                'articleId' => $article->getId(),
            ]);

            return;
        }

        // Step 2: Create atomic notes in vault
        if ($this->vaultPath !== '') {
            $this->ingestionService->createAtomicNotes($entities, $article, $this->vaultPath);
        }

        // Step 3: Update MOCs
        if ($this->vaultPath !== '') {
            $this->ingestionService->updateMOCs($article, $entities, $this->vaultPath);
        }

        // Step 4: Detect new entity connections via Elasticsearch
        $connections = $this->connectionService->detectNewConnections($article, $entities);
        if ($connections !== [] && $this->vaultPath !== '') {
            $this->connectionService->saveConnectionAlerts($connections, $article, $this->vaultPath);
        }

        // Step 5: Feed to NotebookLM (graceful — skipped if unavailable)
        $this->ingestionService->feedNotebookLM($article);

        // Mark as ingested
        $article->setIngestedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->logger->info('IngestArticleHandler: ingestion complete', [
            'articleId' => $article->getId(),
            'entitiesTotal' => $entities->totalCount(),
            'connections' => \count($connections),
            'topics' => $entities->topics,
        ]);
    }
}
